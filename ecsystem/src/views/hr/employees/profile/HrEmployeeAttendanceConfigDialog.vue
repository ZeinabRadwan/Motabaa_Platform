<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'
import { todayIso } from '@/utils/hrShifts'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },

  // EmployeeProfileResource
  employee: { type: Object, required: true },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const MODE_ICONS = {
  electronic: 'tabler-device-laptop',
  manual: 'tabler-clipboard-text',
  exempt: 'tabler-user-off',
}

const { t } = useI18n()
const { lookups } = useHrLookups()

const form = ref({})
const errors = ref({})
const saving = ref(false)
const formKey = ref(0)

const attendance = computed(() => props.employee.attendance ?? {})

const reset = () => {
  const config = attendance.value

  form.value = {
    attendance_mode: config.mode ?? 'manual',
    gps_policy: config.mode === 'electronic' && config.gps_policy ? config.gps_policy : 'policy',
    shift_id: config.current_shift?.shift_id ?? null,
    work_location_id: config.work_location?.id ?? null,
    attendance_policy_id: config.attendance_policy_id ?? null,
    grace_minutes: config.grace_minutes ?? '',
    effective_from: todayIso(),
    reason: '',
  }
  errors.value = {}
  formKey.value++
}

watch(() => props.modelValue, visible => {
  if (visible)
    reset()
})

const close = () => emit('update:modelValue', false)

const isElectronic = computed(() => form.value.attendance_mode === 'electronic')

const modeCards = computed(() => lookups.value.attendance_modes.map(option => ({
  value: option.value,
  title: option.title,
  desc: t(`hr.attendance_config.mode_hints.${option.value}`),
  icon: { icon: MODE_ICONS[option.value] ?? 'tabler-clock', size: 28 },
})))

// Keep the employee's current (possibly inactive) shift, location and policy selectable.
const withCurrent = (options, current) => (current && !options.some(option => option.value === current.value) ? [current, ...options] : options)

const shiftOptions = computed(() => {
  const current = attendance.value.current_shift

  return withCurrent(
    lookups.value.shifts.filter(option => !option.branch_id || option.branch_id === props.employee.branch_id),
    current ? { value: current.shift_id, title: `${current.shift_name} (${current.time_range})` } : null,
  )
})

const locationOptions = computed(() => {
  const current = attendance.value.work_location

  return withCurrent(
    lookups.value.work_locations.filter(option => !props.employee.branch_id || option.branch_id === props.employee.branch_id),
    current ? { value: current.id, title: current.name, has_coordinates: current.has_coordinates } : null,
  )
})

const policyOptions = computed(() => {
  const current = attendance.value.attendance_policy

  return withCurrent(
    lookups.value.attendance_policies,
    current
      ? {
        value: current.id,
        title: current.name,
        gps_requirement: current.gps_requirement,
        allow_manual_attendance: current.allow_manual_attendance,
        late_grace_minutes: current.late_grace_minutes,
      }
      : null,
  )
})

const selectedPolicy = computed(() => policyOptions.value.find(option => option.value === form.value.attendance_policy_id) ?? null)
const selectedLocation = computed(() => locationOptions.value.find(option => option.value === form.value.work_location_id) ?? null)

// Rules of the selected policy, or the center defaults without one.
const policyRules = computed(() => selectedPolicy.value ?? lookups.value.attendance_defaults ?? {})

const gpsLabel = value => lookups.value.attendance_rule_options?.gps_requirements?.find(option => option.value === value)?.title ?? value

const gpsOptions = computed(() => lookups.value.gps_policies.map(option => (option.value === 'policy'
  ? { ...option, title: t('hr.attendance_config.gps_follow_policy', { requirement: gpsLabel(policyRules.value.gps_requirement) }) }
  : option)))

const resolvedGps = computed(() => {
  if (!isElectronic.value)
    return 'none'

  return form.value.gps_policy === 'policy' || !form.value.gps_policy ? policyRules.value.gps_requirement : form.value.gps_policy
})

const manualBlocked = computed(() => form.value.attendance_mode === 'manual' && policyRules.value.allow_manual_attendance === false)
const gpsNeedsLocation = computed(() => resolvedGps.value === 'required' && !selectedLocation.value?.has_coordinates)

const graceSummary = computed(() => {
  if (form.value.grace_minutes !== '' && form.value.grace_minutes !== null)
    return t('hr.employees.grace_custom', { minutes: form.value.grace_minutes })
  if (policyRules.value.late_grace_minutes === undefined || policyRules.value.late_grace_minutes === null)
    return '—'

  return t('hr.attendance_config.grace_inherited', { minutes: policyRules.value.late_grace_minutes })
})

const isScheduled = computed(() => !!form.value.effective_from && form.value.effective_from > todayIso())

const payload = () => ({
  attendance_mode: form.value.attendance_mode,
  gps_policy: isElectronic.value ? form.value.gps_policy : null,
  shift_id: form.value.shift_id,
  work_location_id: form.value.work_location_id,
  attendance_policy_id: form.value.attendance_policy_id,
  grace_minutes: form.value.grace_minutes === '' || form.value.grace_minutes === null ? null : Number(form.value.grace_minutes),
  effective_from: form.value.effective_from || null,
  reason: form.value.reason || null,
})

const save = () => {
  saving.value = true
  errors.value = {}
  hrEmployeesApi.updateAttendance(props.employee.id, payload()).then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    emit('saved')
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
    max-width="820"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      :title="$t('hr.employees.edit_attendance')"
      :subtitle="$t('hr.attendance_config.dialog_hint')"
    >
      <VCardText>
        <div class="text-body-1 font-weight-medium mb-2">
          {{ $t('hr.fields.attendance_mode') }} *
        </div>
        <CustomRadiosWithIcon
          :key="formKey"
          v-model:selected-radio="form.attendance_mode"
          :radio-content="modeCards"
          :grid-column="{ cols: '12', sm: '4' }"
        />
        <div
          v-for="message in errorsOf('attendance_mode')"
          :key="message"
          class="text-error text-caption"
        >
          {{ message }}
        </div>

        <VRow class="mt-2">
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.shift_id"
              :items="shiftOptions"
              :label="$t('hr.fields.shift')"
              :hint="$t('hr.attendance_config.shift_hint')"
              persistent-hint
              :error-messages="errorsOf('shift_id')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.work_location_id"
              :items="locationOptions"
              :label="$t('hr.fields.work_location')"
              clearable
              :error-messages="errorsOf('work_location_id')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.attendance_policy_id"
              :items="policyOptions"
              :label="$t('hr.fields.attendance_policy')"
              :hint="selectedPolicy ? '' : $t('hr.attendance_config.no_policy_hint')"
              persistent-hint
              clearable
              :error-messages="errorsOf('attendance_policy_id')"
            />
          </VCol>
          <VCol
            v-if="isElectronic"
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.gps_policy"
              :items="gpsOptions"
              :label="$t('hr.attendance_config.gps_verification')"
              :error-messages="errorsOf('gps_policy')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppTextField
              v-model="form.grace_minutes"
              type="number"
              min="0"
              max="240"
              :label="$t('hr.fields.grace_minutes')"
              :hint="$t('hr.employees.grace_hint')"
              persistent-hint
              dir="ltr"
              :error-messages="errorsOf('grace_minutes')"
            />
          </VCol>
        </VRow>

        <VAlert
          v-if="!isElectronic"
          class="mt-4"
          variant="tonal"
          color="info"
          density="compact"
          icon="tabler-map-pin-off"
        >
          {{ $t(`hr.attendance_config.no_gps_${form.attendance_mode}`) }}
        </VAlert>
        <VAlert
          v-if="manualBlocked"
          class="mt-4"
          variant="tonal"
          color="error"
          density="compact"
        >
          {{ $t(selectedPolicy ? 'hr.attendance_config.manual_blocked_policy' : 'hr.attendance_config.manual_blocked_center') }}
        </VAlert>
        <VAlert
          v-if="gpsNeedsLocation"
          class="mt-4"
          variant="tonal"
          color="error"
          density="compact"
        >
          {{ $t('hr.attendance_config.gps_needs_location') }}
        </VAlert>

        <div class="hr-attendance-summary mt-4">
          <div>
            <span class="hr-page__muted">{{ $t('hr.attendance_config.gps_verification') }}:</span>
            {{ gpsLabel(resolvedGps) }}
          </div>
          <div>
            <span class="hr-page__muted">{{ $t('hr.fields.grace_minutes') }}:</span>
            {{ graceSummary }}
          </div>
        </div>

        <VRow class="mt-4">
          <VCol
            cols="12"
            sm="5"
          >
            <AppDateTimePicker
              v-model="form.effective_from"
              :label="`${$t('hr.attendance_config.effective_start')} *`"
              :hint="employee.changes_locked_until ? $t('hr.records.locked_until', { date: employee.changes_locked_until }) : ''"
              persistent-hint
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
        <VAlert
          class="mt-4"
          variant="tonal"
          :color="isScheduled ? 'warning' : 'secondary'"
          density="compact"
          icon="tabler-history"
        >
          {{ $t(isScheduled ? 'hr.attendance_config.scheduled_hint' : 'hr.attendance_config.history_hint') }}
        </VAlert>
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
.hr-attendance-summary {
  display: flex;
  flex-wrap: wrap;
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.04);
  gap: 0.5rem 2rem;
  padding-block: 0.75rem;
  padding-inline: 1rem;
}
</style>
