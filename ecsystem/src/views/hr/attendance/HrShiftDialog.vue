<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrShiftsApi } from '@/plugins/apis/hrRequest'
import { SHIFT_COLOR_PRESETS, WEEKDAYS, formatMinutes, isOvernight, shiftColor, spanMinutes, todayIso } from '@/utils/hrShifts'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },

  // Shift being edited (ShiftResource) or null to create one.
  shift: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const DEFAULT_WEEKEND = [5, 6]

const CYCLE_PRESETS = [
  { key: 'two_two', length: 4, work: 2 },
  { key: 'four_four', length: 8, work: 4 },
  { key: 'five_two', length: 7, work: 5 },
  { key: 'six_one', length: 7, work: 6 },
]

// Rest slot in the editor (VSelect treats null as "nothing selected"); sent to the API as null.
const REST = 0

const { t } = useI18n()
const { lookups } = useHrLookups()

const allBranches = computed(() => can('access_hr-all-branches', 'access_hr-all-branches'))

const form = ref({})
const errors = ref({})
const saving = ref(false)
const restTouched = ref(false)

const isEdit = computed(() => !!props.shift?.id)
const isRotating = computed(() => form.value.shift_type === 'rotating')

const emptyForm = () => ({
  code: '',
  shift_type: 'morning',
  name_ar: '',
  name_en: '',
  color: null,
  branch_id: allBranches.value ? null : (lookups.value.branch_scope?.branch_ids?.[0] ?? null),
  start_time: '08:00',
  end_time: '17:00',
  break_minutes: 60,
  break_start_time: '',
  fixed_break: false,
  expected_hours: '',
  grace_minutes: '',
  early_leave_grace_minutes: '',
  working_days: [0, 1, 2, 3, 4],
  rest_days: [...DEFAULT_WEEKEND],
  rotation_pattern: [REST, REST, REST, REST],
  is_active: true,
  effective_from: todayIso(),
  reason: '',
})

const fromShift = shift => ({
  ...emptyForm(),
  code: shift.code,
  shift_type: shift.shift_type,
  name_ar: shift.name_ar,
  name_en: shift.name_en ?? '',
  color: shift.color,
  branch_id: shift.branch_id,
  start_time: shift.start_time ?? '08:00',
  end_time: shift.end_time ?? '17:00',
  break_minutes: shift.break_minutes ?? 0,
  break_start_time: shift.break_start_time ?? '',
  fixed_break: !!shift.break_start_time,
  expected_hours: shift.expected_minutes ? +(shift.expected_minutes / 60).toFixed(2) : '',
  grace_minutes: shift.grace_minutes ?? '',
  early_leave_grace_minutes: shift.early_leave_grace_minutes ?? '',
  working_days: [...(shift.working_days ?? [])],
  rest_days: [...(shift.rest_days ?? [])],
  rotation_pattern: shift.rotation_pattern?.length ? shift.rotation_pattern.map(slot => slot ?? REST) : [REST, REST, REST, REST],
  is_active: shift.is_active,
})

watch(() => props.modelValue, visible => {
  if (!visible)
    return
  form.value = props.shift ? fromShift(props.shift) : emptyForm()
  errors.value = {}
  restTouched.value = isEdit.value
})

const close = () => emit('update:modelValue', false)

const typeItems = computed(() => (lookups.value.shift_types ?? []).filter(option => !isEdit.value || props.shift.employees_count === 0
  || (option.value === 'rotating') === (props.shift.shift_type === 'rotating')))

const branchItems = computed(() => lookups.value.branches)

// Fixed shifts usable as rotation days: same branch or center-wide.
const slotItems = computed(() => [
  { value: REST, title: t('hr.shifts.rest_day') },
  ...lookups.value.shifts
    .filter(option => !option.is_rotating && option.value !== props.shift?.id
      && (!option.branch_id || option.branch_id === form.value.branch_id))
    .map(option => ({ value: option.value, title: option.title.replace(/\s*\(.*\)$/, ''), time_range: option.time_range, color: option.color, shift_type: option.shift_type })),
])

const inUse = computed(() => (props.shift?.employees_count ?? 0) > 0)

const slotShift = id => slotItems.value.find(option => option.value === id)

const span = computed(() => spanMinutes(form.value.start_time, form.value.end_time))
const overnight = computed(() => isOvernight(form.value.start_time, form.value.end_time))
const defaultExpected = computed(() => (span.value === null ? null : Math.max(0, span.value - (Number(form.value.break_minutes) || 0))))

const toggleWorkingDay = day => {
  const working = form.value.working_days.includes(day)
    ? form.value.working_days.filter(value => value !== day)
    : [...form.value.working_days, day]

  form.value.working_days = working.sort()
  form.value.rest_days = form.value.rest_days.filter(value => !working.includes(value))
  if (!restTouched.value) {
    const weekend = DEFAULT_WEEKEND.filter(value => !working.includes(value))

    form.value.rest_days = weekend.length ? weekend : WEEKDAYS.filter(value => !working.includes(value))
  }
}

const toggleRestDay = day => {
  if (form.value.working_days.includes(day))
    return
  restTouched.value = true
  form.value.rest_days = form.value.rest_days.includes(day)
    ? form.value.rest_days.filter(value => value !== day)
    : [...form.value.rest_days, day].sort()
}

const cycleLength = computed({
  get: () => form.value.rotation_pattern?.length ?? 0,
  set: value => {
    const length = Math.min(56, Math.max(1, Number(value) || 1))
    const pattern = [...form.value.rotation_pattern]

    form.value.rotation_pattern = Array.from({ length }, (_, index) => pattern[index] ?? REST)
  },
})

const applyPreset = preset => {
  const first = slotItems.value.find(option => option.value !== REST)?.value ?? REST

  form.value.rotation_pattern = Array.from({ length: preset.length }, (_, index) => (index < preset.work ? first : REST))
}

const restSlots = computed(() => form.value.rotation_pattern.filter(slot => slot === REST).length)

const toNumberOrNull = value => (value === '' || value === null || value === undefined ? null : Number(value))

const fixedTiming = () => {
  const expectedHours = toNumberOrNull(form.value.expected_hours)

  return {
    start_time: form.value.start_time,
    end_time: form.value.end_time,
    break_minutes: Number(form.value.break_minutes) || 0,
    break_start_time: form.value.fixed_break && form.value.break_start_time ? form.value.break_start_time : null,
    expected_minutes: expectedHours === null ? null : Math.round(expectedHours * 60),
    working_days: form.value.working_days,
    rest_days: form.value.rest_days,
  }
}

const payload = () => {
  const data = {
    code: form.value.code,
    shift_type: form.value.shift_type,
    name_ar: form.value.name_ar,
    name_en: form.value.name_en || null,
    color: form.value.color || null,
    branch_id: form.value.branch_id,
    grace_minutes: toNumberOrNull(form.value.grace_minutes),
    early_leave_grace_minutes: toNumberOrNull(form.value.early_leave_grace_minutes),
  }

  if (isRotating.value)
    data.rotation_pattern = form.value.rotation_pattern.map(slot => (slot === REST ? null : slot))
  else
    Object.assign(data, fixedTiming())

  if (isEdit.value) {
    data.effective_from = form.value.effective_from || null
    data.reason = form.value.reason || null
  }
  else {
    data.is_active = form.value.is_active
  }

  return data
}

const save = () => {
  saving.value = true
  errors.value = {}

  const request = isEdit.value ? hrShiftsApi.update(props.shift.id, payload()) : hrShiftsApi.create(payload())

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
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="860"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard :title="$t(isEdit ? 'hr.attendance_setup.edit_shift' : 'hr.attendance_setup.add_shift')">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="4"
          >
            <AppTextField
              v-model="form.code"
              :label="`${$t('hr.fields.code')} *`"
              :error-messages="errorsOf('code')"
              dir="ltr"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="form.shift_type"
              :items="typeItems"
              :label="`${$t('hr.shifts.type')} *`"
              :error-messages="errorsOf('shift_type')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <AppSelect
              v-model="form.branch_id"
              :items="branchItems"
              :label="allBranches ? $t('hr.fields.branch') : `${$t('hr.fields.branch')} *`"
              :placeholder="$t('hr.attendance_setup.all_branches')"
              :clearable="allBranches"
              clear-icon="tabler-x"
              :error-messages="errorsOf('branch_id')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="form.name_ar"
              :label="`${$t('hr.fields.name_ar')} *`"
              :error-messages="errorsOf('name_ar')"
              dir="rtl"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="form.name_en"
              :label="$t('hr.fields.name_en')"
              dir="ltr"
            />
          </VCol>
          <VCol cols="12">
            <div class="text-body-2 mb-1">
              {{ $t('hr.shifts.color') }}
            </div>
            <div class="d-flex flex-wrap gap-2 align-center">
              <button
                v-for="color in SHIFT_COLOR_PRESETS"
                :key="color"
                type="button"
                class="hr-shift-swatch"
                :class="{ 'hr-shift-swatch--active': form.color === color }"
                :style="{ backgroundColor: color }"
                :aria-label="color"
                @click="form.color = color"
              />
              <VBtn
                size="small"
                variant="text"
                :color="form.color ? 'secondary' : 'primary'"
                @click="form.color = null"
              >
                <span
                  class="hr-shift-swatch hr-shift-swatch--inline me-2"
                  :style="{ backgroundColor: shiftColor(null, form.shift_type) }"
                />
                {{ $t('hr.shifts.color_by_type') }}
              </VBtn>
            </div>
          </VCol>
        </VRow>

        <!-- Fixed hours -->
        <template v-if="!isRotating">
          <h4 class="hr-page__section-title mt-6">
            {{ $t('hr.shifts.hours') }}
          </h4>
          <VRow>
            <VCol
              cols="6"
              sm="3"
            >
              <AppTextField
                v-model="form.start_time"
                type="time"
                :label="`${$t('hr.attendance_setup.start_time')} *`"
                :error-messages="errorsOf('start_time')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="6"
              sm="3"
            >
              <AppTextField
                v-model="form.end_time"
                type="time"
                :label="`${$t('hr.attendance_setup.end_time')} *`"
                :hint="overnight ? $t('hr.attendance_setup.overnight_hint') : ''"
                persistent-hint
                :error-messages="errorsOf('end_time')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="6"
              sm="3"
            >
              <AppTextField
                v-model="form.break_minutes"
                type="number"
                min="0"
                max="480"
                :label="$t('hr.attendance_setup.break_minutes')"
                :error-messages="errorsOf('break_minutes')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="6"
              sm="3"
            >
              <AppTextField
                v-model="form.expected_hours"
                type="number"
                min="0.25"
                max="24"
                step="0.25"
                :label="$t('hr.shifts.expected_hours')"
                :placeholder="defaultExpected === null ? '' : String(+(defaultExpected / 60).toFixed(2))"
                :hint="defaultExpected === null ? '' : $t('hr.shifts.expected_default', { value: formatMinutes(defaultExpected, t) })"
                persistent-hint
                :error-messages="errorsOf('expected_minutes')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              class="d-flex align-center gap-4"
            >
              <VSwitch
                v-model="form.fixed_break"
                :label="$t('hr.shifts.fixed_break')"
                :disabled="!Number(form.break_minutes)"
                hide-details
              />
              <AppTextField
                v-if="form.fixed_break"
                v-model="form.break_start_time"
                type="time"
                :label="$t('hr.shifts.break_starts_at')"
                :error-messages="errorsOf('break_start_time')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VAlert
                density="compact"
                variant="tonal"
                color="info"
              >
                {{ form.fixed_break ? $t('hr.shifts.fixed_break_hint') : $t('hr.shifts.floating_break_hint') }}
              </VAlert>
            </VCol>
          </VRow>

          <h4 class="hr-page__section-title mt-6">
            {{ $t('hr.shifts.days') }}
          </h4>
          <div class="text-body-2 mb-1">
            {{ $t('hr.fields.working_days') }} *
          </div>
          <div class="d-flex flex-wrap gap-2 mb-1">
            <VChip
              v-for="day in WEEKDAYS"
              :key="`w${day}`"
              :color="form.working_days.includes(day) ? 'primary' : undefined"
              :variant="form.working_days.includes(day) ? 'flat' : 'outlined'"
              @click="toggleWorkingDay(day)"
            >
              {{ $t(`hr.weekdays.${day}`) }}
            </VChip>
          </div>
          <div
            v-if="errorsOf('working_days').length"
            class="text-error text-caption mb-2"
          >
            {{ errorsOf('working_days')[0] }}
          </div>
          <div class="text-body-2 mb-1 mt-3">
            {{ $t('hr.shifts.rest_days') }} *
          </div>
          <div class="d-flex flex-wrap gap-2 mb-1">
            <VChip
              v-for="day in WEEKDAYS"
              :key="`r${day}`"
              :disabled="form.working_days.includes(day)"
              :color="form.rest_days.includes(day) ? 'warning' : undefined"
              :variant="form.rest_days.includes(day) ? 'flat' : 'outlined'"
              @click="toggleRestDay(day)"
            >
              {{ $t(`hr.weekdays.${day}`) }}
            </VChip>
          </div>
          <div class="hr-page__muted">
            {{ errorsOf('rest_days')[0] || $t('hr.shifts.rest_days_hint') }}
          </div>
        </template>

        <!-- Rotation cycle -->
        <template v-else>
          <h4 class="hr-page__section-title mt-6">
            {{ $t('hr.shifts.rotation_cycle') }}
          </h4>
          <VRow class="mb-2">
            <VCol
              cols="12"
              sm="3"
            >
              <AppTextField
                v-model="cycleLength"
                type="number"
                min="1"
                max="56"
                :label="$t('hr.shifts.cycle_length')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="9"
              class="d-flex flex-wrap align-center gap-2"
            >
              <span class="hr-page__muted">{{ $t('hr.shifts.presets') }}:</span>
              <VBtn
                v-for="preset in CYCLE_PRESETS"
                :key="preset.key"
                size="small"
                variant="tonal"
                @click="applyPreset(preset)"
              >
                {{ $t(`hr.shifts.preset.${preset.key}`) }}
              </VBtn>
            </VCol>
          </VRow>
          <div class="hr-rotation-grid">
            <div
              v-for="(slot, index) in form.rotation_pattern"
              :key="index"
              class="hr-rotation-grid__day"
              :style="slot !== REST ? { borderInlineStartColor: shiftColor(slotShift(slot)?.color, slotShift(slot)?.shift_type) } : {}"
            >
              <div class="hr-page__muted mb-1">
                {{ $t('hr.shifts.cycle_day', { day: index + 1 }) }}
              </div>
              <AppSelect
                v-model="form.rotation_pattern[index]"
                :items="slotItems"
                density="compact"
                hide-details
              />
              <div
                v-if="slot !== REST && slotShift(slot)"
                class="hr-page__muted mt-1"
                dir="ltr"
              >
                {{ slotShift(slot).time_range }}
              </div>
            </div>
          </div>
          <div
            class="mt-2"
            :class="errorsOf('rotation_pattern').length ? 'text-error text-caption' : 'hr-page__muted'"
          >
            {{ errorsOf('rotation_pattern')[0] || $t('hr.shifts.rotation_hint', { rest: restSlots, days: cycleLength }) }}
          </div>
        </template>

        <h4 class="hr-page__section-title mt-6">
          {{ $t('hr.shifts.grace') }}
        </h4>
        <VRow>
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="form.grace_minutes"
              type="number"
              min="0"
              max="240"
              :label="$t('hr.attendance_setup.late_grace')"
              :hint="$t('hr.shifts.grace_inherit_hint')"
              persistent-hint
              :disabled="isRotating"
              :error-messages="errorsOf('grace_minutes')"
              dir="ltr"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="form.early_leave_grace_minutes"
              type="number"
              min="0"
              max="240"
              :label="$t('hr.attendance_setup.early_leave_grace')"
              :hint="$t('hr.shifts.grace_inherit_hint')"
              persistent-hint
              :disabled="isRotating"
              :error-messages="errorsOf('early_leave_grace_minutes')"
              dir="ltr"
            />
          </VCol>
        </VRow>

        <template v-if="isEdit">
          <VAlert
            v-if="inUse"
            class="mt-6"
            variant="tonal"
            color="warning"
            icon="tabler-calendar-time"
          >
            {{ $t('hr.shifts.effective_change_hint') }}
          </VAlert>
          <VRow :class="inUse ? 'mt-2' : 'mt-4'">
            <VCol
              cols="12"
              sm="5"
            >
              <AppDateTimePicker
                v-model="form.effective_from"
                :label="$t('hr.shifts.timing_effective_from')"
                :error-messages="errorsOf('effective_from')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="7"
            >
              <AppTextField
                v-model="form.reason"
                :label="$t('hr.fields.change_reason')"
                :error-messages="errorsOf('reason')"
              />
            </VCol>
          </VRow>
        </template>
        <VSwitch
          v-else
          v-model="form.is_active"
          class="mt-4"
          :label="$t('Active')"
          hide-details
        />
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
