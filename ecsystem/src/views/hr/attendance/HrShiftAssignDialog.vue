<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrEmployeesApi, hrShiftsApi } from '@/plugins/apis/hrRequest'
import { todayIso } from '@/utils/hrShifts'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  shift: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'assigned'])

const { t } = useI18n()
const { lookups } = useHrLookups()

const form = ref({})
const errors = ref({})
const saving = ref(false)
const result = ref(null)

watch(() => props.modelValue, visible => {
  if (!visible)
    return
  form.value = {
    target: 'employees',
    employee_ids: [],
    branch_id: props.shift?.branch_id ?? null,
    set_as_branch_default: false,
    effective_from: todayIso(),
    rotation_offset: 0,
    reason: '',
  }
  errors.value = {}
  result.value = null
})

const close = () => emit('update:modelValue', false)

const branchItems = computed(() => lookups.value.branches.filter(option => !props.shift?.branch_id || option.value === props.shift.branch_id))

const offsetItems = computed(() => (props.shift?.rotation_slots ?? []).map((slot, index) => ({
  value: index,
  title: `${t('hr.shifts.cycle_day', { day: index + 1 })} — ${slot.is_rest ? t('hr.shifts.rest_day') : `${slot.code} (${slot.time_range})`}`,
})))

const searchEmployees = params => hrEmployeesApi.list({
  q: params.q || undefined,
  ids: params.ids,
  employed_only: 1,
  branch_id: props.shift?.branch_id || undefined,
  options: { page: 1, itemsPerPage: 20 },
})

const save = () => {
  const data = {
    effective_from: form.value.effective_from,
    reason: form.value.reason || null,
    rotation_offset: props.shift?.is_rotating ? form.value.rotation_offset : null,
  }

  if (form.value.target === 'branch') {
    data.branch_id = form.value.branch_id
    data.set_as_branch_default = !props.shift?.is_rotating && form.value.set_as_branch_default
  }
  else {
    data.employee_ids = form.value.employee_ids
  }

  saving.value = true
  errors.value = {}
  hrShiftsApi.assign(props.shift.id, data).then(response => {
    result.value = response.data?.data ?? null
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), result.value?.failed?.length ? 'warning' : 'success')
    emit('assigned')
    if (!result.value?.failed?.length)
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
    max-width="620"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      v-if="shift"
      :title="$t('hr.shifts.assign_title', { shift: shift.name })"
      :subtitle="shift.time_range"
    >
      <VCardText>
        <VBtnToggle
          v-model="form.target"
          mandatory
          divided
          variant="outlined"
          color="primary"
          density="comfortable"
          class="mb-4"
        >
          <VBtn value="employees">
            <VIcon
              icon="tabler-users"
              start
            />
            {{ $t('hr.shifts.assign_to_employees') }}
          </VBtn>
          <VBtn value="branch">
            <VIcon
              icon="tabler-building"
              start
            />
            {{ $t('hr.shifts.assign_to_branch') }}
          </VBtn>
        </VBtnToggle>

        <VRow>
          <VCol
            v-if="form.target === 'employees'"
            cols="12"
          >
            <AppAutocomplete
              v-model="form.employee_ids"
              :server-search="searchEmployees"
              preload
              multiple
              chips
              closable-chips
              item-title="name"
              item-value="id"
              :label="`${$t('hr.employees.title')} *`"
              :error-messages="errorsOf('employee_ids')"
            />
          </VCol>
          <template v-else>
            <VCol cols="12">
              <AppSelect
                v-model="form.branch_id"
                :items="branchItems"
                :label="`${$t('hr.fields.branch')} *`"
                :hint="$t('hr.shifts.assign_branch_hint')"
                persistent-hint
                :error-messages="errorsOf('branch_id')"
              />
            </VCol>
            <VCol
              v-if="!shift.is_rotating"
              cols="12"
            >
              <VCheckbox
                v-model="form.set_as_branch_default"
                :label="$t('hr.shifts.set_as_branch_default')"
                hide-details
              />
            </VCol>
          </template>
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.effective_from"
              :label="`${$t('hr.fields.effective_from')} *`"
              :error-messages="errorsOf('effective_from')"
            />
          </VCol>
          <VCol
            v-if="shift.is_rotating"
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.rotation_offset"
              :items="offsetItems"
              :label="$t('hr.shifts.starts_on_cycle_day')"
              :error-messages="errorsOf('rotation_offset')"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="form.reason"
              :label="$t('hr.fields.change_reason')"
            />
          </VCol>
        </VRow>

        <VAlert
          v-if="result?.failed?.length"
          type="warning"
          variant="tonal"
          class="mt-4"
        >
          <div class="mb-2">
            {{ $t('hr.shifts.assign_partial', { assigned: result.assigned, failed: result.failed.length }) }}
          </div>
          <ul class="ps-4">
            <li
              v-for="failure in result.failed"
              :key="failure.employee_id"
            >
              <strong>{{ failure.name }}</strong>
              <span
                class="hr-page__muted"
                dir="ltr"
              > ({{ failure.employee_number }})</span>: {{ failure.message }}
            </li>
          </ul>
        </VAlert>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="close"
        >
          {{ $t(result ? 'Close' : 'Cancel') }}
        </VBtn>
        <VBtn
          :loading="saving"
          prepend-icon="tabler-user-check"
          @click="save"
        >
          {{ $t('hr.shifts.assign') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
