<script setup>
import { hrAdditionalDaysApi } from '@/plugins/apis/hrRequest'
import { todayIso } from '@/utils/hrShifts'
import { useI18n } from 'vue-i18n'

// Adds an additional working day (one date, whole or half day) for one or more employees, as a draft or submitted
// for approval at once; or edits a draft / pending record (editing a pending record restarts its approval).
// Payroll pays days only: (gross salary / 30) × days, never overtime hours.

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  record: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const { t } = useI18n()
const isEdit = computed(() => !!props.record)

const DAY_ITEMS = computed(() => [
  { value: 1, title: t('hr.additional_days.full_day') },
  { value: 0.5, title: t('hr.additional_days.half_day') },
])

const emptyForm = () => ({ employee_ids: [], work_date: null, days: 1, reason: '', submit: false })

const form = ref(emptyForm())
const saving = ref(false)

watch(() => props.modelValue, open => {
  if (!open)
    return
  form.value = props.record
    ? { employee_ids: [props.record.employee?.id], work_date: props.record.work_date, days: Number(props.record.days), reason: props.record.reason, submit: false }
    : emptyForm()
})

const searchEmployees = query => hrAdditionalDaysApi.employees({ q: query.q || undefined, ids: query.ids })

const canSave = computed(() => form.value.work_date && form.value.reason.trim().length >= 3
  && [1, 0.5].includes(Number(form.value.days))
  && (isEdit.value || form.value.employee_ids.length > 0))

const save = () => {
  saving.value = true

  const payload = { work_date: form.value.work_date, days: Number(form.value.days), reason: form.value.reason.trim() }

  const request = isEdit.value
    ? hrAdditionalDaysApi.update(props.record.id, payload)
    : hrAdditionalDaysApi.create({ ...payload, employee_ids: form.value.employee_ids, submit: form.value.submit })

  request.then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    emit('update:modelValue', false)
    emit('saved', response.data?.data)
  }).catch(() => {}).finally(() => {
    saving.value = false
  })
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="680"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard :title="$t(isEdit ? 'hr.additional_days.edit' : 'hr.additional_days.add')">
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ $t('hr.additional_days.days_only_hint') }}
        </VAlert>
        <VAlert
          v-if="isEdit && record?.status === 'pending'"
          type="warning"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ $t('hr.additional_days.edit_pending_hint') }}
        </VAlert>
        <VRow>
          <VCol cols="12">
            <AppAutocomplete
              v-if="!isEdit"
              v-model="form.employee_ids"
              :server-search="searchEmployees"
              item-title="title"
              item-value="id"
              multiple
              chips
              closable-chips
              :label="$t('hr.additional_days.employees')"
              :hint="$t('hr.additional_days.employees_hint')"
              persistent-hint
            />
            <AppTextField
              v-else
              :model-value="`${record.employee?.name ?? ''} (${record.employee?.employee_number ?? ''})`"
              :label="$t('hr.fields.employee')"
              readonly
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.work_date"
              :label="$t('hr.additional_days.work_date')"
              :config="{ maxDate: todayIso() }"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppSelect
              v-model="form.days"
              :items="DAY_ITEMS"
              :label="$t('hr.additional_days.days')"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.reason"
              :label="$t('hr.additional_days.reason')"
              rows="3"
              maxlength="1000"
              counter
            />
          </VCol>
          <VCol
            v-if="!isEdit"
            cols="12"
          >
            <VSwitch
              v-model="form.submit"
              :label="$t('hr.additional_days.submit_now')"
              :hint="$t('hr.additional_days.submit_now_hint')"
              persistent-hint
            />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          :loading="saving"
          :disabled="!canSave"
          @click="save"
        >
          {{ $t(!isEdit && form.submit ? 'hr.additional_days.save_and_submit' : 'Save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
