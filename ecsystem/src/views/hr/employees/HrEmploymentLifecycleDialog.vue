<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'

const props = defineProps({
  modelValue: { type: Boolean, default: false },

  // List row or profile: needs id, name and is_employed.
  employee: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { lookups } = useHrLookups()

const today = () => new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Riyadh' })

const form = ref({})
const saving = ref(false)

const deactivating = computed(() => !!props.employee?.is_employed)

watch(() => props.modelValue, visible => {
  if (!visible)
    return
  form.value = deactivating.value
    ? { end_date: today(), termination_reason: null, notes: '' }
    : { start_date: today(), employment_status: 'active', reason: '' }
})

const close = () => emit('update:modelValue', false)

const submit = () => {
  saving.value = true

  const request = deactivating.value
    ? hrEmployeesApi.deactivate(props.employee.id, form.value)
    : hrEmployeesApi.activate(props.employee.id, form.value)

  request.then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message, 'success')
    close()
    emit('saved', response.data?.data)
  }).catch(() => {}).finally(() => {
    saving.value = false
  })
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="560"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard :title="`${$t(deactivating ? 'hr.employees.deactivate' : 'hr.employees.activate')} — ${employee?.name ?? ''}`">
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ $t(deactivating ? 'hr.employees.deactivate_hint' : 'hr.employees.activate_hint') }}
        </VAlert>
        <VRow v-if="deactivating">
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.end_date"
              :label="$t('hr.fields.end_of_service_date')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.termination_reason"
              :items="lookups.termination_reasons"
              :label="$t('hr.fields.termination_reason')"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.notes"
              :label="$t('Notes')"
              rows="2"
              auto-grow
            />
          </VCol>
        </VRow>
        <VRow v-else>
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.start_date"
              :label="$t('hr.fields.employment_start_date')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.employment_status"
              :items="lookups.employment_statuses.filter(option => ['active', 'probation'].includes(option.value))"
              :label="$t('hr.fields.employment_status')"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.reason"
              :label="$t('hr.fields.change_reason')"
              rows="2"
              auto-grow
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
          :color="deactivating ? 'error' : 'success'"
          :loading="saving"
          @click="submit"
        >
          {{ $t(deactivating ? 'hr.employees.deactivate' : 'hr.employees.activate') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
