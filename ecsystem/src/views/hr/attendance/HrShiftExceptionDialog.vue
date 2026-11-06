<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrEmployeesApi, hrShiftExceptionsApi } from '@/plugins/apis/hrRequest'
import { todayIso } from '@/utils/hrShifts'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },

  // Prefill: { scope, employee_id, employee_name, branch_id, date_from, date_to, lock_target }
  preset: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const { lookups } = useHrLookups()

const allBranches = computed(() => can('access_hr-all-branches', 'access_hr-all-branches'))

const form = ref({})
const errors = ref({})
const saving = ref(false)

watch(() => props.modelValue, visible => {
  if (!visible)
    return
  const date = props.preset.date_from ?? todayIso()

  form.value = {
    scope: props.preset.scope ?? 'employee',
    employee_id: props.preset.employee_id ?? null,
    branch_id: props.preset.branch_id ?? null,
    exception_type: props.preset.exception_type ?? 'day_off',
    shift_id: null,
    start_time: '08:00',
    end_time: '14:00',
    break_minutes: 0,
    date_from: date,
    date_to: props.preset.date_to ?? date,
    reason: '',
  }
  errors.value = {}
})

const close = () => emit('update:modelValue', false)

const scopeItems = computed(() => [
  { value: 'employee', title: t('hr.exceptions.scope.employee') },
  { value: 'branch', title: t('hr.exceptions.scope.branch') },
  ...(allBranches.value ? [{ value: 'center', title: t('hr.exceptions.scope.center') }] : []),
])

const targetBranch = computed(() => (form.value.scope === 'branch' ? form.value.branch_id : props.preset.employee_branch_id ?? null))

const shiftItems = computed(() => lookups.value.shifts.filter(option => !option.is_rotating
  && (!option.branch_id || !targetBranch.value || option.branch_id === targetBranch.value)))

const searchEmployees = params => hrEmployeesApi.list({
  q: params.q || undefined,
  ids: params.ids,
  employed_only: 1,
  options: { page: 1, itemsPerPage: 20 },
})

const save = () => {
  const data = {
    scope: form.value.scope,
    employee_id: form.value.scope === 'employee' ? form.value.employee_id : null,
    branch_id: form.value.scope === 'branch' ? form.value.branch_id : null,
    exception_type: form.value.exception_type,
    date_from: form.value.date_from,
    date_to: form.value.date_to,
    reason: form.value.reason || null,
  }

  if (data.exception_type === 'shift')
    data.shift_id = form.value.shift_id
  if (data.exception_type === 'custom_hours') {
    data.start_time = form.value.start_time
    data.end_time = form.value.end_time
    data.break_minutes = Number(form.value.break_minutes) || 0
  }

  saving.value = true
  errors.value = {}
  hrShiftExceptionsApi.create(data).then(response => {
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
    max-width="640"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      :title="$t('hr.exceptions.add')"
      :subtitle="preset.employee_name || ''"
    >
      <VCardText>
        <VRow>
          <VCol
            v-if="!preset.lock_target"
            cols="12"
            sm="5"
          >
            <AppSelect
              v-model="form.scope"
              :items="scopeItems"
              :label="`${$t('hr.exceptions.applies_to')} *`"
              :error-messages="errorsOf('scope')"
            />
          </VCol>
          <VCol
            v-if="!preset.lock_target && form.scope === 'employee'"
            cols="12"
            sm="7"
          >
            <AppAutocomplete
              v-model="form.employee_id"
              :server-search="searchEmployees"
              preload
              item-title="name"
              item-value="id"
              :label="`${$t('hr.fields.employee')} *`"
              :error-messages="errorsOf('employee_id')"
            />
          </VCol>
          <VCol
            v-if="!preset.lock_target && form.scope === 'branch'"
            cols="12"
            sm="7"
          >
            <AppSelect
              v-model="form.branch_id"
              :items="lookups.branches"
              :label="`${$t('hr.fields.branch')} *`"
              :error-messages="errorsOf('branch_id')"
            />
          </VCol>
          <VCol cols="12">
            <VBtnToggle
              v-model="form.exception_type"
              mandatory
              divided
              variant="outlined"
              color="primary"
              density="comfortable"
              class="flex-wrap"
            >
              <VBtn value="day_off">
                <VIcon
                  icon="tabler-beach"
                  start
                />
                {{ $t('hr.exceptions.types.day_off') }}
              </VBtn>
              <VBtn value="shift">
                <VIcon
                  icon="tabler-switch-horizontal"
                  start
                />
                {{ $t('hr.exceptions.types.shift') }}
              </VBtn>
              <VBtn value="custom_hours">
                <VIcon
                  icon="tabler-clock-edit"
                  start
                />
                {{ $t('hr.exceptions.types.custom_hours') }}
              </VBtn>
            </VBtnToggle>
          </VCol>
          <VCol
            v-if="form.exception_type === 'shift'"
            cols="12"
          >
            <AppSelect
              v-model="form.shift_id"
              :items="shiftItems"
              :label="`${$t('hr.fields.shift')} *`"
              :error-messages="errorsOf('shift_id')"
            />
          </VCol>
          <template v-if="form.exception_type === 'custom_hours'">
            <VCol cols="4">
              <AppTextField
                v-model="form.start_time"
                type="time"
                :label="`${$t('hr.attendance_setup.start_time')} *`"
                :error-messages="errorsOf('start_time')"
                dir="ltr"
              />
            </VCol>
            <VCol cols="4">
              <AppTextField
                v-model="form.end_time"
                type="time"
                :label="`${$t('hr.attendance_setup.end_time')} *`"
                :hint="form.end_time && form.start_time && form.end_time <= form.start_time ? $t('hr.attendance_setup.overnight_hint') : ''"
                persistent-hint
                :error-messages="errorsOf('end_time')"
                dir="ltr"
              />
            </VCol>
            <VCol cols="4">
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
          </template>
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.date_from"
              :label="`${$t('hr.exceptions.date_from')} *`"
              :error-messages="errorsOf('date_from')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.date_to"
              :label="`${$t('hr.exceptions.date_to')} *`"
              :error-messages="errorsOf('date_to')"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="form.reason"
              :label="$t('hr.exceptions.reason')"
              :error-messages="errorsOf('reason')"
            />
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
