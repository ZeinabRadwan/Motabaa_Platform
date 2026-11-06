<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrAttendanceRecordsApi } from '@/plugins/apis/hrRequest'
import { ATTENDANCE_STATUS_COLORS, ENTRY_REASONS } from '@/utils/hrAttendanceRecords'
import { dayAppearance, formatMinutes } from '@/utils/hrShifts'
import { useI18n } from 'vue-i18n'

/**
 * Manual attendance form: a new record, or the correction of any record (manual or electronic). The server
 * resolves the times (overnight shifts included), checks every rule and calculates the result; the preview shows
 * that before saving. Electronic records keep their method and shift; their recorded times can only be corrected.
 */
const props = defineProps({
  modelValue: { type: Boolean, default: false },

  // Record to correct (AttendanceRecordResource); null for a new record.
  record: { type: Object, default: null },

  // `limits` from the summary endpoint (today, oldest_date, can_enter_historical...).
  limits: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const { lookups } = useHrLookups()

const form = ref({})
const errors = ref({})
const saving = ref(false)
const preview = ref(null)
const previewError = ref(null)
const previewing = ref(false)
const employeeOptions = ref({})

const isEdit = computed(() => !!props.record)
const isElectronic = computed(() => isEdit.value && props.record.attendance_method === 'electronic')

watch(() => props.modelValue, visible => {
  if (!visible)
    return
  const record = props.record

  form.value = {
    employee_id: record?.employee?.id ?? null,
    attendance_date: record?.attendance_date ?? props.limits.today ?? null,
    shift_id: record?.shift_overridden ? record.shift?.id ?? null : null,
    check_in: record?.check_in?.time ?? '',
    check_out: record?.check_out?.time ?? '',
    set_dates: false,
    check_in_date: record?.check_in?.date ?? record?.attendance_date ?? null,
    check_out_date: record?.check_out?.date ?? record?.attendance_date ?? null,
    notes: record?.notes ?? '',
    entry_reason: record ? record.entry_reason : 'manual_attendance_method',
    entry_reason_notes: record?.entry_reason_notes ?? '',
    reason: '',
  }
  if (record?.employee)
    employeeOptions.value[record.employee.id] = { ...record.employee, branch_id: record.employee.branch?.id ?? null }
  errors.value = {}
  preview.value = null
  previewError.value = null
})

const close = () => emit('update:modelValue', false)

const employee = computed(() => employeeOptions.value[form.value.employee_id] ?? null)

const searchEmployees = params => hrAttendanceRecordsApi.employees({
  q: params.q || undefined,
  ids: params.ids,
  date: form.value.attendance_date || undefined,
}).then(response => {
  (response.data?.data ?? []).forEach(option => {
    employeeOptions.value[option.id] = option
  })

  return response
})

const shiftItems = computed(() => lookups.value.shifts.filter(option => !option.is_rotating
  && (!option.branch_id || !employee.value?.branch_id || option.branch_id === employee.value.branch_id)))

const reasonItems = computed(() => ENTRY_REASONS.map(value => ({ value, title: t(`hr.attendance_records.entry_reasons.${value}`) })))

const dateConfig = computed(() => ({
  maxDate: props.limits.today,
  minDate: props.limits.can_enter_historical ? null : props.limits.oldest_date,
}))

/** Employee, date, shift and times: what the calculation depends on. */
const timesPayload = () => {
  const data = {
    check_in: form.value.check_in || null,
    check_out: form.value.check_out || null,
  }

  if (!isEdit.value) {
    data.employee_id = form.value.employee_id
    data.attendance_date = form.value.attendance_date
  }
  if (!isElectronic.value)
    data.shift_id = form.value.shift_id || null
  if (form.value.set_dates) {
    data.check_in_date = data.check_in ? form.value.check_in_date : null
    data.check_out_date = data.check_out ? form.value.check_out_date : null
  }

  return data
}

const previewPayload = () => (isEdit.value ? { ...timesPayload(), record_id: props.record.id } : timesPayload())

const savePayload = () => {
  const data = { ...timesPayload(), notes: form.value.notes || null }

  if (!isElectronic.value) {
    data.entry_reason = form.value.entry_reason
    data.entry_reason_notes = form.value.entry_reason === 'other' ? form.value.entry_reason_notes || null : null
  }
  if (isEdit.value)
    data.reason = form.value.reason

  return data
}

let previewTimer = null
let previewSeq = 0

const refreshPreview = () => {
  clearTimeout(previewTimer)
  if (!props.modelValue || (!isEdit.value && (!form.value.employee_id || !form.value.attendance_date)))
    return

  previewTimer = setTimeout(() => {
    const seq = ++previewSeq

    previewing.value = true
    hrAttendanceRecordsApi.preview(previewPayload()).then(response => {
      if (seq !== previewSeq)
        return
      preview.value = response.data.data
      previewError.value = null
    }).catch(error => {
      if (seq !== previewSeq)
        return
      const messages = Object.values(error?.response?.data?.errors ?? {}).flat()

      preview.value = null
      previewError.value = messages[0] ?? t('hr.attendance_records.preview_failed')
    }).finally(() => {
      if (seq === previewSeq)
        previewing.value = false
    })
  }, 400)
}

watch(() => [
  props.modelValue,
  form.value.employee_id,
  form.value.attendance_date,
  form.value.shift_id,
  form.value.check_in,
  form.value.check_out,
  form.value.set_dates,
  form.value.check_in_date,
  form.value.check_out_date,
], refreshPreview)

onBeforeUnmount(() => clearTimeout(previewTimer))

const save = () => {
  saving.value = true
  errors.value = {}

  const request = isEdit.value
    ? hrAttendanceRecordsApi.update(props.record.id, savePayload())
    : hrAttendanceRecordsApi.create(savePayload())

  request.then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    emit('saved', response.data?.data)
    close()
  }).catch(error => {
    errors.value = error?.response?.data?.errors ?? {}
  }).finally(() => {
    saving.value = false
  })
}

const errorsOf = field => errors.value[field] ?? []

const dayLabel = day => {
  if (!day)
    return '—'
  if (day.status === 'working')
    return `${day.shift_name ?? ''} · ${day.start_time} - ${day.end_time}`

  return t(dayAppearance(day).labelKey)
}

const momentLabel = (moment, date) => {
  if (!moment)
    return '—'

  return moment.date === date ? moment.time : `${moment.time} (${moment.date})`
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="860"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      :title="isEdit ? $t('hr.attendance_records.edit_title') : $t('hr.attendance_records.add_title')"
      :subtitle="isEdit ? `${record.employee?.name ?? ''} · ${record.attendance_date}` : $t('hr.attendance_records.add_subtitle')"
    >
      <VCardText>
        <VAlert
          v-if="errorsOf('error').length"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ errorsOf('error')[0] }}
        </VAlert>
        <VAlert
          v-if="isElectronic"
          type="info"
          variant="tonal"
          density="compact"
          icon="tabler-map-pin-check"
          class="mb-4"
        >
          {{ $t('hr.attendance_records.electronic_correction_note') }}
        </VAlert>

        <VRow>
          <VCol
            cols="12"
            md="7"
          >
            <VRow>
              <VCol
                v-if="!isEdit"
                cols="12"
              >
                <AppAutocomplete
                  v-model="form.employee_id"
                  :server-search="searchEmployees"
                  :preload-key="form.attendance_date"
                  item-title="title"
                  item-value="id"
                  :label="`${$t('hr.fields.employee')} *`"
                  :error-messages="errorsOf('employee_id')"
                />
                <div
                  v-if="employee?.attendance_mode"
                  class="text-caption hr-page__muted mt-1"
                >
                  {{ $t('hr.fields.attendance_mode') }}: {{ $t(`hr.attendance_records.modes.${employee.attendance_mode}`) }}
                </div>
              </VCol>
              <VCol
                v-if="!isEdit"
                cols="12"
                sm="6"
              >
                <AppDateTimePicker
                  v-model="form.attendance_date"
                  :config="dateConfig"
                  :label="`${$t('hr.attendance_records.attendance_date')} *`"
                  :error-messages="errorsOf('attendance_date')"
                />
              </VCol>
              <VCol
                cols="12"
                :sm="isEdit ? 12 : 6"
              >
                <AppSelect
                  v-model="form.shift_id"
                  :items="shiftItems"
                  :label="$t('hr.attendance_records.assigned_shift')"
                  :placeholder="$t('hr.attendance_records.scheduled_shift')"
                  :disabled="isElectronic"
                  :hint="isElectronic ? $t('hr.attendance_records.shift_locked') : $t('hr.attendance_records.shift_hint')"
                  persistent-hint
                  clearable
                  clear-icon="tabler-x"
                  :error-messages="errorsOf('shift_id')"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.check_in"
                  type="time"
                  :label="$t('hr.my_attendance.check_in_time')"
                  :error-messages="errorsOf('check_in')"
                  clearable
                  clear-icon="tabler-x"
                  dir="ltr"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.check_out"
                  type="time"
                  :label="$t('hr.my_attendance.check_out_time')"
                  :error-messages="errorsOf('check_out')"
                  clearable
                  clear-icon="tabler-x"
                  dir="ltr"
                />
              </VCol>
              <VCol
                cols="12"
                class="py-0"
              >
                <VCheckbox
                  v-model="form.set_dates"
                  :label="$t('hr.attendance_records.set_dates')"
                  density="compact"
                  hide-details
                />
              </VCol>
              <template v-if="form.set_dates">
                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppDateTimePicker
                    v-model="form.check_in_date"
                    :label="$t('hr.attendance_records.check_in_date')"
                    :disabled="!form.check_in"
                    :error-messages="errorsOf('check_in_date')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <AppDateTimePicker
                    v-model="form.check_out_date"
                    :label="$t('hr.attendance_records.check_out_date')"
                    :disabled="!form.check_out"
                    :error-messages="errorsOf('check_out_date')"
                  />
                </VCol>
              </template>
              <template v-if="!isElectronic">
                <VCol
                  cols="12"
                  :sm="form.entry_reason === 'other' ? 6 : 12"
                >
                  <AppSelect
                    v-model="form.entry_reason"
                    :items="reasonItems"
                    :label="`${$t('hr.attendance_records.entry_reason')}${isEdit ? '' : ' *'}`"
                    :error-messages="errorsOf('entry_reason')"
                  />
                </VCol>
                <VCol
                  v-if="form.entry_reason === 'other'"
                  cols="12"
                  sm="6"
                >
                  <AppTextField
                    v-model="form.entry_reason_notes"
                    :label="`${$t('hr.attendance_records.entry_reason_notes')} *`"
                    :error-messages="errorsOf('entry_reason_notes')"
                  />
                </VCol>
              </template>
              <VCol cols="12">
                <AppTextarea
                  v-model="form.notes"
                  :label="$t('hr.attendance_records.notes')"
                  rows="2"
                  auto-grow
                  :error-messages="errorsOf('notes')"
                />
              </VCol>
              <VCol
                v-if="isEdit"
                cols="12"
              >
                <AppTextarea
                  v-model="form.reason"
                  :label="`${$t('hr.attendance_records.modification_reason')} *`"
                  :hint="$t('hr.attendance_records.modification_reason_hint')"
                  persistent-hint
                  rows="2"
                  auto-grow
                  :error-messages="errorsOf('reason')"
                />
              </VCol>
            </VRow>
          </VCol>

          <VCol
            cols="12"
            md="5"
          >
            <VCard
              variant="tonal"
              color="secondary"
              class="hr-manual-preview"
            >
              <VCardItem class="pb-2">
                <VCardTitle class="text-body-1 d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-calculator"
                    size="20"
                  />
                  {{ $t('hr.attendance_records.calculation') }}
                  <VProgressCircular
                    v-if="previewing"
                    indeterminate
                    size="16"
                    width="2"
                  />
                </VCardTitle>
              </VCardItem>
              <VCardText>
                <VAlert
                  v-if="previewError"
                  type="warning"
                  variant="tonal"
                  density="compact"
                  class="mb-3"
                >
                  {{ previewError }}
                </VAlert>
                <div
                  v-if="!preview && !previewError"
                  class="text-body-2 hr-page__muted"
                >
                  {{ $t('hr.attendance_records.preview_empty') }}
                </div>
                <template v-if="preview">
                  <dl class="hr-manual-preview__list">
                    <dt>{{ $t('hr.attendance_records.scheduled_shift') }}</dt>
                    <dd dir="auto">
                      {{ dayLabel(preview.scheduled_day) }}
                    </dd>
                    <template v-if="preview.shift_overridden">
                      <dt>{{ $t('hr.attendance_records.assigned_shift') }}</dt>
                      <dd>{{ dayLabel(preview.day) }}</dd>
                    </template>
                    <template v-if="preview.day.status === 'working'">
                      <dt>{{ $t('hr.attendance_records.grace') }}</dt>
                      <dd>{{ $t('hr.attendance_records.grace_value', { late: preview.day.late_grace_minutes ?? 0, early: preview.day.early_leave_grace_minutes ?? 0 }) }}</dd>
                    </template>
                    <dt>{{ $t('hr.my_attendance.check_in_time') }}</dt>
                    <dd dir="ltr">
                      {{ momentLabel(preview.check_in, preview.attendance_date) }}
                    </dd>
                    <dt>{{ $t('hr.my_attendance.check_out_time') }}</dt>
                    <dd dir="ltr">
                      {{ momentLabel(preview.check_out, preview.attendance_date) }}
                    </dd>
                    <dt>{{ $t('hr.my_attendance.working_duration') }}</dt>
                    <dd>{{ formatMinutes(preview.duration_minutes, t) }}</dd>
                    <dt>{{ $t('hr.my_attendance.worked_in_shift') }}</dt>
                    <dd>{{ formatMinutes(preview.worked_minutes, t) }}</dd>
                    <dt>{{ $t('hr.attendance_records.late_minutes') }}</dt>
                    <dd>{{ formatMinutes(preview.late_minutes, t) }}</dd>
                    <dt>{{ $t('hr.attendance_records.early_departure_minutes') }}</dt>
                    <dd>{{ formatMinutes(preview.early_departure_minutes, t) }}</dd>
                  </dl>
                  <div class="d-flex align-center gap-2 mt-3">
                    <span class="text-body-2">{{ $t('hr.attendance_log.status') }}:</span>
                    <VChip
                      v-if="preview.attendance_status"
                      size="small"
                      label
                      :color="ATTENDANCE_STATUS_COLORS[preview.attendance_status]"
                    >
                      {{ $t(`hr.attendance_records.statuses.${preview.attendance_status}`) }}
                    </VChip>
                    <span v-else>—</span>
                  </div>
                  <VAlert
                    v-for="warning in preview.warnings"
                    :key="warning"
                    type="info"
                    variant="text"
                    density="compact"
                    class="px-0 py-1 mt-1 text-body-2"
                  >
                    {{ $t(`hr.attendance_records.warnings.${warning}`) }}
                  </VAlert>
                </template>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="close"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          :loading="saving"
          @click="save"
        >
          {{ $t('Save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.hr-manual-preview__list {
  display: grid;
  gap: 6px 12px;
  grid-template-columns: max-content 1fr;
  margin: 0;

  dt {
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    font-size: 0.8125rem;
  }

  dd {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 500;
  }
}
</style>
