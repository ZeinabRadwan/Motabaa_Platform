<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import HrInfoGrid from '@/components/hr/HrInfoGrid.vue'
import { useHrEmployeeRecords } from '@/composables/useHrEmployeeRecords'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'
import { dayAppearance, formatMinutes, shiftColor, WEEKDAYS } from '@/utils/hrShifts'
import HrEmployeeAttendanceConfigDialog from '@/views/hr/employees/profile/HrEmployeeAttendanceConfigDialog.vue'
import HrEmployeeAttendanceConfigHistory from '@/views/hr/employees/profile/HrEmployeeAttendanceConfigHistory.vue'
import HrEmployeeAttendanceLog from '@/views/hr/employees/profile/HrEmployeeAttendanceLog.vue'
import HrEmployeeScheduleCalendar from '@/views/hr/employees/profile/HrEmployeeScheduleCalendar.vue'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  employee: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const { t } = useI18n()
const { lookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-employees', 'edit_hr-employees'))

const attendance = computed(() => props.employee.attendance ?? {})

const shiftLabel = assignment => (assignment ? `${assignment.shift_name} (${assignment.time_range})` : null)

const today = computed(() => attendance.value.today ?? null)

const todaySummary = computed(() => {
  const day = today.value
  if (!day)
    return null
  if (day.status !== 'working')
    return t(dayAppearance(day).labelKey)
  const span = `${day.start_time} - ${day.end_time}${day.is_overnight ? ` (${t('hr.shifts.next_day')})` : ''}`

  return `${day.shift_name || t('hr.exceptions.types.custom_hours')} · ${span} · ${t('hr.shifts.expected_hours')}: ${formatMinutes(day.expected_minutes, t)}`
})

const gpsSummary = config => {
  if (config.mode !== 'electronic')
    return config.gps_requirement_label
  const suffix = config.gps_policy === 'policy' ? ` (${t('hr.attendance_config.from_policy')})` : ''

  return `${config.gps_requirement_label}${suffix}`
}

const configItems = computed(() => {
  const config = attendance.value
  const policy = config.attendance_policy

  return [
    { label: t('hr.fields.attendance_mode'), value: config.mode_label },
    { label: t('hr.attendance_config.gps_verification'), value: gpsSummary(config) },
    { label: t('hr.fields.work_location'), value: config.work_location?.name },
    { label: t('hr.fields.shift'), value: shiftLabel(config.current_shift) },
    { label: t('hr.fields.attendance_policy'), value: policy?.name ?? t('hr.attendance_config.center_defaults') },
    {
      label: t('hr.fields.grace_minutes'),
      value: config.effective_grace_minutes === null || config.effective_grace_minutes === undefined
        ? null
        : t(config.grace_minutes === null ? 'hr.employees.grace_from_policy' : 'hr.employees.grace_custom', { minutes: config.effective_grace_minutes }),
    },
    { label: t('hr.attendance_config.effective_start'), value: config.current_config?.effective_from },
  ]
})

const ruleLabel = (group, value) => lookups.value.attendance_rule_options?.[group]?.find(option => option.value === value)?.title ?? value

const minutesLabel = value => t('hr.attendance_setup.minutes', { minutes: value })

// Rules of the policy version in force today, as resolved for this employee.
const ruleItems = computed(() => {
  const rules = attendance.value.rules
  if (!rules)
    return []

  return [
    {
      label: t('hr.policies.check_in_window'),
      value: t('hr.policies.window_summary', {
        before: minutesLabel(rules.check_in_before_minutes),
        after: rules.check_in_after_minutes === null ? t('hr.policies.until_shift_end') : minutesLabel(rules.check_in_after_minutes),
      }),
    },
    {
      label: t('hr.policies.check_out_window'),
      value: t('hr.policies.window_summary', {
        before: rules.check_out_before_minutes === null ? t('hr.policies.any_time') : minutesLabel(rules.check_out_before_minutes),
        after: minutesLabel(rules.check_out_after_minutes),
      }),
    },
    { label: t('hr.policies.sections.late'), value: `${ruleLabel('counting_modes', rules.late_counting)} · ${ruleLabel('deviation_actions', rules.late_action)}` },
    { label: t('hr.policies.sections.early'), value: `${ruleLabel('counting_modes', rules.early_leave_counting)} · ${ruleLabel('deviation_actions', rules.early_leave_action)}` },
    { label: t('hr.policies.missing_checkout'), value: ruleLabel('missing_checkout_actions', rules.missing_checkout_action) },
    { label: t('hr.policies.duplicate_punch'), value: `${ruleLabel('duplicate_actions', rules.duplicate_punch_action)} (${minutesLabel(rules.duplicate_window_minutes)})` },
  ]
})

const nextConfigSummary = computed(() => {
  const next = attendance.value.next_config
  if (!next)
    return null

  return t('hr.attendance_config.next_change', {
    date: next.effective_from,
    mode: next.attendance_mode_label,
    policy: next.attendance_policy?.name ?? t('hr.attendance_config.center_defaults'),
  })
})

// Attendance configuration
const canConfigure = computed(() => !!attendance.value.can_configure)
const isConfigVisible = ref(false)
const configHistory = ref(null)

const onConfigChanged = () => {
  configHistory.value?.refresh()
  shifts.fetchRows()
  calendar.value?.refresh()
  emit('changed')
}

// Shift assignments (effective-dated)
const calendar = ref(null)

const shifts = useHrEmployeeRecords(hrEmployeesApi.shifts, () => props.employee.id, {
  emptyForm: () => ({ shift_id: null, effective_from: null, reason: '', rotation_offset: 0, custom_days: false, working_days: [], rest_days: [] }),
  toPayload: form => {
    const option = lookups.value.shifts.find(item => item.value === form.shift_id)
    const payload = { shift_id: form.shift_id, effective_from: form.effective_from, reason: form.reason || null }

    if (option?.is_rotating)
      payload.rotation_offset = Number(form.rotation_offset) || 0
    else if (form.custom_days) {
      payload.working_days = form.working_days
      payload.rest_days = form.rest_days
    }

    return payload
  },
  onChanged: () => {
    calendar.value?.refresh()
    emit('changed')
  },
})

const shiftOptions = computed(() => lookups.value.shifts.filter(option => !option.branch_id || option.branch_id === props.employee.branch_id))

const selectedShift = computed(() => lookups.value.shifts.find(option => option.value === shifts.form.value.shift_id) ?? null)

watch(selectedShift, option => {
  const form = shifts.form.value

  form.rotation_offset = 0
  form.working_days = [...(option?.working_days ?? [])]
  form.rest_days = [...(option?.rest_days ?? [])]
})

const toggleDay = (day, field) => {
  const form = shifts.form.value
  const other = field === 'working_days' ? 'rest_days' : 'working_days'

  form[field] = form[field].includes(day) ? form[field].filter(item => item !== day) : [...form[field], day].sort()
  form[other] = form[other].filter(item => item !== day)
}

const rotationDays = computed(() => Array.from({ length: selectedShift.value?.cycle_length ?? 0 }, (_, index) => ({ value: index, title: t('hr.shifts.cycle_day', { day: index + 1 }) })))

const assignmentDays = row => {
  if (row.is_rotating)
    return t('hr.shifts.cycle_summary', { days: row.cycle_length }) + (row.rotation_offset ? ` · ${t('hr.shifts.starts_on_cycle_day')} ${row.rotation_offset + 1}` : '')

  return row.working_days.map(day => t(`hr.weekdays_short.${day}`)).join('، ')
}

onMounted(() => {
  shifts.fetchRows()
})

const rowState = row => {
  if (row.is_scheduled)
    return { color: 'info', label: t('hr.records.scheduled') }
  if (row.is_current)
    return { color: 'success', label: t('hr.records.current') }

  return { color: 'secondary', label: t('hr.records.past') }
}
</script>

<template>
  <div>
    <VCard class="mb-4">
      <VCardItem>
        <VCardTitle>{{ $t('hr.employees.attendance_configuration') }}</VCardTitle>
        <template #append>
          <VBtn
            v-if="canConfigure && employee.is_employed"
            size="small"
            variant="tonal"
            prepend-icon="tabler-settings"
            @click="isConfigVisible = true"
          >
            {{ $t('hr.employees.edit_attendance') }}
          </VBtn>
        </template>
      </VCardItem>
      <VCardText>
        <HrInfoGrid :items="configItems" />
        <VAlert
          v-for="warning in attendance.warnings ?? []"
          :key="warning.key"
          type="warning"
          variant="tonal"
          density="compact"
          class="mt-4"
        >
          {{ warning.message }}
        </VAlert>
        <VAlert
          v-if="nextConfigSummary"
          type="info"
          variant="tonal"
          density="compact"
          class="mt-4"
          icon="tabler-calendar-time"
        >
          {{ nextConfigSummary }}
        </VAlert>
        <div
          v-if="ruleItems.length"
          class="mt-6"
        >
          <div class="text-body-1 font-weight-medium mb-3">
            {{ $t('hr.attendance_config.rules_in_force') }}
          </div>
          <HrInfoGrid :items="ruleItems" />
        </div>
        <div
          v-if="today && employee.is_employed"
          class="hr-today-shift mt-4"
          :style="today.status === 'working' ? { borderInlineStartColor: shiftColor(today.color, today.shift_type) } : {}"
        >
          <VIcon
            :icon="today.status === 'working' ? 'tabler-clock-play' : (dayAppearance(today).icon || 'tabler-calendar')"
            size="20"
          />
          <div>
            <div class="text-body-2 hr-page__muted">
              {{ $t('hr.schedule.today_schedule') }}
              <VChip
                v-if="today.source === 'exception'"
                size="x-small"
                color="info"
                label
                class="ms-1"
              >
                {{ $t('hr.schedule.exception') }}
              </VChip>
            </div>
            <div
              class="font-weight-medium"
              :dir="today.status === 'working' ? 'auto' : undefined"
            >
              {{ todaySummary }}
            </div>
          </div>
        </div>
        <VAlert
          v-if="attendance.next_shift"
          type="info"
          variant="tonal"
          density="compact"
          class="mt-4"
        >
          {{ $t('hr.employees.next_shift', { shift: shiftLabel(attendance.next_shift), date: attendance.next_shift.effective_from }) }}
        </VAlert>
      </VCardText>
    </VCard>

    <HrEmployeeAttendanceConfigHistory
      ref="configHistory"
      :employee="employee"
      :can-configure="canConfigure"
      class="mb-4"
      @changed="onConfigChanged"
    />

    <VCard>
      <VCardItem>
        <VCardTitle>{{ $t('hr.employees.shift_assignments') }}</VCardTitle>
        <template #append>
          <VBtn
            v-if="canEdit && employee.is_employed"
            size="small"
            prepend-icon="tabler-plus"
            @click="shifts.openDialog"
          >
            {{ $t('hr.records.assign_shift') }}
          </VBtn>
        </template>
      </VCardItem>
      <VTable
        density="compact"
        class="text-no-wrap"
      >
        <thead>
          <tr>
            <th>{{ $t('hr.fields.effective_from') }}</th>
            <th>{{ $t('hr.fields.effective_to') }}</th>
            <th>{{ $t('hr.fields.shift') }}</th>
            <th>{{ $t('hr.fields.working_days') }}</th>
            <th>{{ $t('hr.fields.change_reason') }}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in shifts.rows.value"
            :key="row.id"
          >
            <td dir="ltr">
              {{ row.effective_from }}
            </td>
            <td dir="ltr">
              {{ row.effective_to || '—' }}
            </td>
            <td>
              <div class="d-flex align-center gap-2">
                <span
                  class="hr-shift-swatch hr-shift-swatch--inline"
                  :style="{ backgroundColor: shiftColor(row.color, row.shift_type) }"
                />
                {{ shiftLabel(row) }}
              </div>
            </td>
            <td>
              {{ assignmentDays(row) }}
              <VChip
                v-if="row.has_day_overrides"
                size="x-small"
                label
                color="warning"
                class="ms-1"
              >
                {{ $t('hr.schedule.custom_days') }}
              </VChip>
            </td>
            <td>{{ row.reason || '—' }}</td>
            <td>
              <div class="d-flex align-center gap-1">
                <VChip
                  size="x-small"
                  label
                  :color="rowState(row).color"
                >
                  {{ rowState(row).label }}
                </VChip>
                <VBtn
                  v-if="canEdit && row.is_scheduled && row === shifts.rows.value[0]"
                  icon
                  size="x-small"
                  variant="text"
                  color="error"
                  @click="shifts.askCancel(row)"
                >
                  <VIcon icon="tabler-calendar-x" />
                  <VTooltip activator="parent">
                    {{ $t('hr.records.cancel_scheduled') }}
                  </VTooltip>
                </VBtn>
              </div>
            </td>
          </tr>
          <tr v-if="!shifts.rows.value.length && !shifts.loading.value">
            <td
              colspan="6"
              class="text-center hr-page__muted"
            >
              {{ $t('hr.records.no_shift') }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <HrEmployeeScheduleCalendar
      ref="calendar"
      :employee="employee"
      class="mt-4"
    />

    <HrEmployeeAttendanceLog
      :employee="employee"
      class="mt-4"
    />

    <HrEmployeeAttendanceConfigDialog
      v-model="isConfigVisible"
      :employee="employee"
      @saved="onConfigChanged"
    />

    <VDialog
      v-model="shifts.isDialogVisible.value"
      max-width="560"
    >
      <VCard :title="$t('hr.records.assign_shift')">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppSelect
                v-model="shifts.form.value.shift_id"
                :items="shiftOptions"
                :label="`${$t('hr.fields.shift')} *`"
                :error-messages="shifts.errorsOf('shift_id')"
              />
            </VCol>
            <VCol
              v-if="selectedShift?.is_rotating"
              cols="12"
            >
              <AppSelect
                v-model="shifts.form.value.rotation_offset"
                :items="rotationDays"
                :label="$t('hr.shifts.starts_on_cycle_day')"
                :hint="$t('hr.schedule.rotation_offset_hint')"
                persistent-hint
                :error-messages="shifts.errorsOf('rotation_offset')"
              />
            </VCol>
            <VCol
              v-else-if="selectedShift"
              cols="12"
            >
              <VSwitch
                v-model="shifts.form.value.custom_days"
                :label="$t('hr.schedule.custom_days_toggle')"
                hide-details
              />
              <template v-if="shifts.form.value.custom_days">
                <div class="text-body-2 mt-3 mb-1">
                  {{ $t('hr.fields.working_days') }}
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    v-for="day in WEEKDAYS"
                    :key="`w${day}`"
                    :color="shifts.form.value.working_days.includes(day) ? 'primary' : undefined"
                    :variant="shifts.form.value.working_days.includes(day) ? 'flat' : 'outlined'"
                    size="small"
                    @click="toggleDay(day, 'working_days')"
                  >
                    {{ $t(`hr.weekdays_short.${day}`) }}
                  </VChip>
                </div>
                <div class="text-body-2 mt-3 mb-1">
                  {{ $t('hr.shifts.rest_days') }}
                </div>
                <div class="d-flex flex-wrap gap-1">
                  <VChip
                    v-for="day in WEEKDAYS"
                    :key="`r${day}`"
                    :color="shifts.form.value.rest_days.includes(day) ? 'secondary' : undefined"
                    :variant="shifts.form.value.rest_days.includes(day) ? 'flat' : 'outlined'"
                    size="small"
                    @click="toggleDay(day, 'rest_days')"
                  >
                    {{ $t(`hr.weekdays_short.${day}`) }}
                  </VChip>
                </div>
                <div
                  v-for="field in ['working_days', 'rest_days']"
                  :key="`e${field}`"
                  class="text-error text-caption"
                >
                  {{ shifts.errorsOf(field).join(' ') }}
                </div>
              </template>
            </VCol>
            <VCol cols="12">
              <AppDateTimePicker
                v-model="shifts.form.value.effective_from"
                :label="`${$t('hr.fields.effective_from')} *`"
                :error-messages="shifts.errorsOf('effective_from')"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="shifts.form.value.reason"
                :label="$t('hr.fields.change_reason')"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="shifts.isDialogVisible.value = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            :loading="shifts.saving.value"
            @click="shifts.save"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <HrConfirmDialog
      :model-value="!!shifts.cancelTarget.value"
      :message="$t('hr.records.confirm_cancel')"
      :confirm-label="$t('hr.records.cancel_scheduled')"
      @update:model-value="shifts.cancelTarget.value = null"
      @confirm="shifts.confirmCancel"
    />
  </div>
</template>
