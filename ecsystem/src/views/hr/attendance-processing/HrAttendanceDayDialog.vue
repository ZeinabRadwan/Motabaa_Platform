<script setup>
import HrInfoGrid from '@/components/hr/HrInfoGrid.vue'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrAttendanceDaysApi } from '@/plugins/apis/hrRequest'
import { formatHrDateTime } from '@/utils/hrFormat'
import { ATTENDANCE_DAY_STATUS_COLORS, formatDays } from '@/utils/hrLeave'
import { formatMinutes } from '@/utils/hrShifts'
import { useI18n } from 'vue-i18n'

// One processed attendance day: the computed result, why it got it (rest day, holiday, leave, exceptions,
// review flags) and the correction history. HR (edit_hr-attendance-processing) can correct the status and
// late / early minutes of working days, confirm an exception as reviewed, or reset a correction — always with
// a reason. Days of an approved payroll period are locked.

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  dayId: { type: Number, default: null },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'changed'])

const { t, locale } = useI18n()
const { lookups } = useHrLookups()

const day = ref(null)
const loading = ref(false)
const saving = ref(false)

const form = ref({ status: null, late_minutes: null, early_departure_minutes: null, reason: '' })

const load = () => {
  if (!props.dayId)
    return
  loading.value = true
  hrAttendanceDaysApi.show(props.dayId).then(response => {
    day.value = response.data.data
    form.value = {
      status: day.value.override?.status ?? null,
      late_minutes: day.value.override?.late_minutes ?? null,
      early_departure_minutes: day.value.override?.early_departure_minutes ?? null,
      reason: '',
    }
  }).catch(() => {
    day.value = null
  }).finally(() => {
    loading.value = false
  })
}

watch(() => [props.modelValue, props.dayId], ([open]) => {
  if (open)
    load()
  else
    day.value = null
})

const editable = computed(() => props.canEdit && day.value && !day.value.is_locked
  && (day.value.can_correct_values || day.value.exception_reasons?.length))

const reasonValid = computed(() => form.value.reason.trim().length >= 5)

const minutesOrNull = value => (value === null || value === undefined || value === '' ? null : Number(value))

const notify = message => window.Snackbar?.value?.exposevisibleSnackbar?.(message, 'success')

const afterSave = response => {
  notify(response.data?.message || t('saved_successfully'))
  emit('changed')
  load()
}

const submit = (valuesIncluded = true) => {
  if (!reasonValid.value)
    return
  saving.value = true

  // A correction replaces the previous one, so confirming as reviewed keeps the values already corrected.
  const source = valuesIncluded ? form.value : (day.value.override ?? {})
  const payload = { reason: form.value.reason.trim() }
  if (day.value.can_correct_values) {
    payload.status = source.status || null
    payload.late_minutes = minutesOrNull(source.late_minutes)
    payload.early_departure_minutes = minutesOrNull(source.early_departure_minutes)
  }

  hrAttendanceDaysApi.correct(day.value.id, payload).then(afterSave).catch(() => {}).finally(() => {
    saving.value = false
  })
}

const reset = () => {
  if (!reasonValid.value)
    return
  saving.value = true
  hrAttendanceDaysApi.reset(day.value.id, form.value.reason.trim()).then(afterSave).catch(() => {}).finally(() => {
    saving.value = false
  })
}

const minutes = value => (value ? formatMinutes(value, t) : '—')

const info = computed(() => {
  const value = day.value
  if (!value)
    return []

  return [
    { label: t('hr.fields.employee'), value: value.employee?.name },
    { label: t('hr.attendance_processing.date'), value: value.attendance_date, ltr: true },
    { label: t('hr.fields.branch'), value: value.branch_name },
    { label: t('hr.fields.shift'), value: value.shift ? `${value.shift.name} (${value.scheduled_start}–${value.scheduled_end})` : null },
    { label: t('hr.attendance_processing.grace'), value: value.shift ? t('hr.shifts.minutes_short', { minutes: value.late_grace_minutes ?? 0 }) : null },
    { label: t('hr.attendance_processing.check_in'), value: value.check_in, ltr: true },
    { label: t('hr.attendance_processing.check_out'), value: value.check_out, ltr: true },
    { label: t('hr.attendance_processing.worked'), value: minutes(value.worked_minutes) },
    { label: t('hr.attendance_processing.expected'), value: minutes(value.expected_minutes) },
    { label: t('hr.attendance_processing.computed_status'), value: value.computed_status_label },
    { label: t('hr.attendance_processing.computed_late'), value: minutes(value.computed_late_minutes) },
    { label: t('hr.attendance_processing.computed_early'), value: minutes(value.computed_early_departure_minutes) },
    { label: t('hr.attendance_processing.absence_days'), value: formatDays(value.absence_days) },
    { label: t('hr.attendance_processing.reason'), value: value.status_reason_label },
    { label: t('hr.attendance_processing.leave'), value: value.leave?.leave_type },
    { label: t('hr.attendance_processing.holiday'), value: value.holiday?.name },
  ]
})

const CHANGE_KEYS = ['status', 'late_minutes', 'early_departure_minutes', 'absence_days', 'review_status']

const changeText = correction => CHANGE_KEYS
  .filter(key => key in (correction.new_values ?? {}))
  .map(key => `${t(`hr.attendance_processing.change_keys.${key}`)}: ${correction.old_values?.[key] ?? '—'} → ${correction.new_values[key] ?? '—'}`)
  .join(' · ')
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="900"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard :title="$t('hr.attendance_processing.day_details')">
      <VCardText>
        <VProgressLinear
          v-if="loading && !day"
          indeterminate
        />
        <template v-if="day">
          <div class="d-flex flex-wrap align-center gap-2 mb-4">
            <VChip
              label
              :color="ATTENDANCE_DAY_STATUS_COLORS[day.status] || 'secondary'"
            >
              {{ day.status_label }}
            </VChip>
            <VChip
              v-if="day.override"
              label
              color="info"
              prepend-icon="tabler-edit-circle"
            >
              {{ $t('hr.attendance_processing.corrected') }}
            </VChip>
            <VChip
              v-if="day.is_locked"
              label
              color="secondary"
              prepend-icon="tabler-lock"
            >
              {{ $t('hr.attendance_processing.locked') }}
            </VChip>
            <VChip
              v-if="day.review_status"
              label
              :color="day.review_status === 'pending' ? 'warning' : 'success'"
            >
              {{ $t(`hr.attendance_processing.review.${day.review_status}`) }}
            </VChip>
          </div>

          <HrInfoGrid :items="info" />

          <div
            v-if="day.exception_reasons?.length || day.review_flags?.length"
            class="mt-4"
          >
            <div class="text-subtitle-2 mb-2">
              {{ $t('hr.attendance_processing.exceptions_and_flags') }}
            </div>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                v-for="reason in day.exception_reasons"
                :key="reason.key"
                size="small"
                label
                color="error"
                prepend-icon="tabler-alert-triangle"
              >
                {{ reason.label }}
              </VChip>
              <VChip
                v-for="flag in day.review_flags"
                :key="flag.key"
                size="small"
                label
                color="warning"
                prepend-icon="tabler-flag"
              >
                {{ flag.label }}
              </VChip>
            </div>
          </div>

          <VAlert
            v-if="day.override"
            class="mt-4"
            type="info"
            variant="tonal"
            density="compact"
          >
            {{ $t('hr.attendance_processing.override_by', { name: day.override.by?.name ?? '—', at: formatHrDateTime(day.override.at, locale) }) }}
            <div>{{ day.override.reason }}</div>
          </VAlert>

          <template v-if="editable">
            <VDivider class="my-4" />
            <div class="text-subtitle-2 mb-2">
              {{ $t('hr.attendance_processing.correction') }}
            </div>
            <VAlert
              v-if="!day.can_correct_values"
              type="warning"
              variant="tonal"
              density="compact"
              class="mb-3"
            >
              {{ $t('hr.attendance_processing.review_only_hint') }}
            </VAlert>
            <VRow>
              <template v-if="day.can_correct_values">
                <VCol
                  cols="12"
                  sm="4"
                >
                  <AppSelect
                    v-model="form.status"
                    :items="lookups.attendance_override_statuses"
                    :label="$t('hr.attendance_processing.override_status')"
                    :placeholder="$t('hr.attendance_processing.keep_computed')"
                    clearable
                    clear-icon="tabler-x"
                  />
                </VCol>
                <VCol
                  cols="6"
                  sm="4"
                >
                  <AppTextField
                    v-model="form.late_minutes"
                    type="number"
                    min="0"
                    max="1440"
                    :label="$t('hr.attendance_processing.late_minutes')"
                    :placeholder="String(day.computed_late_minutes ?? 0)"
                    clearable
                  />
                </VCol>
                <VCol
                  cols="6"
                  sm="4"
                >
                  <AppTextField
                    v-model="form.early_departure_minutes"
                    type="number"
                    min="0"
                    max="1440"
                    :label="$t('hr.attendance_processing.early_minutes')"
                    :placeholder="String(day.computed_early_departure_minutes ?? 0)"
                    clearable
                  />
                </VCol>
              </template>
              <VCol cols="12">
                <AppTextarea
                  v-model="form.reason"
                  :label="$t('hr.attendance_processing.correction_reason')"
                  :hint="$t('hr.attendance_processing.correction_reason_hint')"
                  persistent-hint
                  rows="2"
                  counter="1000"
                />
              </VCol>
            </VRow>
          </template>

          <template v-if="day.corrections?.length">
            <VDivider class="my-4" />
            <div class="text-subtitle-2 mb-2">
              {{ $t('hr.attendance_processing.corrections_history') }}
            </div>
            <VTimeline
              density="compact"
              side="end"
              truncate-line="both"
            >
              <VTimelineItem
                v-for="correction in day.corrections"
                :key="correction.id"
                size="x-small"
                :dot-color="correction.action === 'reset' ? 'secondary' : 'info'"
              >
                <div class="d-flex flex-wrap justify-space-between gap-2">
                  <span class="font-weight-medium">{{ $t(`hr.attendance_processing.correction_actions.${correction.action}`) }} — {{ correction.by?.name ?? $t('hr.history.system') }}</span>
                  <span class="text-caption hr-page__muted">{{ formatHrDateTime(correction.at, locale) }}</span>
                </div>
                <div class="text-body-2">
                  {{ correction.reason }}
                </div>
                <div
                  v-if="changeText(correction)"
                  class="text-caption hr-page__muted"
                >
                  {{ changeText(correction) }}
                </div>
              </VTimelineItem>
            </VTimeline>
          </template>
        </template>
      </VCardText>
      <VCardActions class="flex-wrap">
        <template v-if="editable">
          <VBtn
            v-if="day.override"
            color="secondary"
            variant="tonal"
            :disabled="!reasonValid"
            :loading="saving"
            @click="reset"
          >
            {{ $t('hr.attendance_processing.reset') }}
          </VBtn>
          <VBtn
            v-if="day.review_status === 'pending'"
            color="success"
            variant="tonal"
            :disabled="!reasonValid"
            :loading="saving"
            @click="submit(false)"
          >
            {{ $t('hr.attendance_processing.mark_reviewed') }}
          </VBtn>
        </template>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Close') }}
        </VBtn>
        <VBtn
          v-if="editable && day.can_correct_values"
          :disabled="!reasonValid"
          :loading="saving"
          @click="submit(true)"
        >
          {{ $t('hr.attendance_processing.save_correction') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
