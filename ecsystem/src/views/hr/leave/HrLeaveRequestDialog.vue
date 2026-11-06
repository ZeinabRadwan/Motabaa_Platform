<script setup>
import { hrLeaveRequestsApi, hrMyLeaveApi } from '@/plugins/apis/hrRequest'
import { formatDays } from '@/utils/hrLeave'
import { useI18n } from 'vue-i18n'

// New leave request. HR mode (hr-leave): any employee of the user's branches, optionally recorded as already
// approved (admin). Self mode (my leave): the signed-in employee. The preview shows the days the leave consumes
// (rest days and holidays excluded unless the type counts calendar days), the balance after it and the first
// problem the server would refuse it for.

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  mode: { type: String, default: 'hr' },
  leaveTypes: { type: Array, default: () => [] },
  canRecord: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const MAX_FILES = 5
const MAX_FILE_MB = 5

const { t } = useI18n()
const api = computed(() => (props.mode === 'self' ? hrMyLeaveApi : hrLeaveRequestsApi))

const emptyForm = () => ({
  employee_id: null,
  leave_type_id: null,
  start_date: null,
  end_date: null,
  reason: '',
  record_as_approved: false,
})

const form = ref(emptyForm())
const files = ref([])
const saving = ref(false)
const preview = ref(null)
const previewLoading = ref(false)

const typeItems = computed(() => props.leaveTypes
  .filter(type => type.is_active !== false)
  .map(type => ({ ...type, value: type.value ?? type.id, title: type.title ?? type.name })))

const selectedType = computed(() => typeItems.value.find(type => type.value === form.value.leave_type_id) ?? null)
const requiresAttachment = computed(() => preview.value?.leave_type?.requires_attachment ?? selectedType.value?.requires_attachment ?? false)

const searchEmployees = query => hrLeaveRequestsApi.employees({ q: query.q || undefined, ids: query.ids })

watch(() => props.modelValue, open => {
  if (!open)
    return
  form.value = emptyForm()
  files.value = []
  preview.value = null
})

const previewReady = computed(() => form.value.leave_type_id && form.value.start_date && form.value.end_date
  && (props.mode === 'self' || form.value.employee_id))

let previewTimer = null
let previewSeq = 0

const loadPreview = () => {
  clearTimeout(previewTimer)
  if (!previewReady.value) {
    preview.value = null

    return
  }

  previewTimer = setTimeout(() => {
    const seq = ++previewSeq

    previewLoading.value = true
    api.value.preview({
      employee_id: props.mode === 'self' ? undefined : form.value.employee_id,
      leave_type_id: form.value.leave_type_id,
      start_date: form.value.start_date,
      end_date: form.value.end_date,
    }).then(({ data }) => {
      if (seq === previewSeq)
        preview.value = data.data
    }).catch(() => {
      if (seq === previewSeq)
        preview.value = null
    }).finally(() => {
      if (seq === previewSeq)
        previewLoading.value = false
    })
  }, 300)
}

watch(() => [form.value.employee_id, form.value.leave_type_id, form.value.start_date, form.value.end_date], loadPreview)

const fileError = computed(() => {
  if (files.value.length > MAX_FILES)
    return t('hr.leave.too_many_files', { max: MAX_FILES })
  if (files.value.some(file => file.size > MAX_FILE_MB * 1024 * 1024))
    return t('hr.documents.file_too_large', { size: MAX_FILE_MB })

  return ''
})

const canSubmit = computed(() => previewReady.value && form.value.reason.trim().length >= 3 && !fileError.value
  && (!requiresAttachment.value || files.value.length > 0))

const submit = () => {
  saving.value = true

  const fields = {
    employee_id: props.mode === 'self' ? undefined : form.value.employee_id,
    leave_type_id: form.value.leave_type_id,
    start_date: form.value.start_date,
    end_date: form.value.end_date,
    reason: form.value.reason.trim(),
    record_as_approved: props.mode === 'hr' && props.canRecord ? form.value.record_as_approved : undefined,
  }

  api.value.create(fields, files.value).then(response => {
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
    max-width="720"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard :title="$t(mode === 'self' ? 'hr.my_leave.new_request' : 'hr.leave.new_request')">
      <VCardText>
        <VRow>
          <VCol
            v-if="mode === 'hr'"
            cols="12"
          >
            <AppAutocomplete
              v-model="form.employee_id"
              :server-search="searchEmployees"
              item-title="title"
              item-value="id"
              :label="$t('hr.fields.employee')"
            />
          </VCol>
          <VCol cols="12">
            <AppSelect
              v-model="form.leave_type_id"
              :items="typeItems"
              :label="$t('hr.leave.leave_type')"
            >
              <template #item="{ props: itemProps, item }">
                <VListItem v-bind="itemProps">
                  <template #prepend>
                    <VIcon
                      icon="tabler-circle-filled"
                      size="12"
                      :color="item.raw.color || 'primary'"
                      class="me-2"
                    />
                  </template>
                </VListItem>
              </template>
            </AppSelect>
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.start_date"
              :label="$t('hr.leave.start_date')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
          >
            <AppDateTimePicker
              v-model="form.end_date"
              :label="$t('hr.leave.end_date')"
            />
          </VCol>

          <VCol
            v-if="previewLoading || preview"
            cols="12"
          >
            <VProgressLinear
              v-if="previewLoading"
              indeterminate
              class="mb-2"
            />
            <div
              v-if="preview"
              class="hr-leave-preview"
            >
              <div class="d-flex flex-wrap gap-4">
                <div>
                  <div class="text-caption hr-page__muted">
                    {{ $t('hr.leave.days') }}
                  </div>
                  <div class="text-h6">
                    {{ preview.days }}
                    <span class="text-caption hr-page__muted">{{ $t('hr.leave.of_calendar_days', { days: preview.calendar_days }) }}</span>
                  </div>
                </div>
                <template v-if="preview.balance">
                  <div>
                    <div class="text-caption hr-page__muted">
                      {{ $t('hr.leave.available') }}
                    </div>
                    <div class="text-h6">
                      {{ formatDays(preview.balance.available) }}
                    </div>
                  </div>
                  <div>
                    <div class="text-caption hr-page__muted">
                      {{ $t('hr.leave.balance_after') }}
                    </div>
                    <div
                      class="text-h6"
                      :class="preview.balance.after < 0 ? 'text-error' : ''"
                    >
                      {{ formatDays(preview.balance.after) }}
                    </div>
                  </div>
                </template>
                <div v-else>
                  <div class="text-caption hr-page__muted">
                    {{ $t('hr.leave.balance') }}
                  </div>
                  <div class="text-body-1">
                    {{ $t('hr.leave.no_balance') }}
                  </div>
                </div>
              </div>
              <div
                v-if="preview.leave_type?.day_counting === 'working_days'"
                class="text-caption hr-page__muted mt-2"
              >
                {{ $t('hr.leave.working_days_hint') }}
              </div>
              <VAlert
                v-if="preview.problem"
                type="warning"
                variant="tonal"
                density="compact"
                class="mt-3"
              >
                {{ preview.problem.message }}
              </VAlert>
            </div>
          </VCol>

          <VCol cols="12">
            <AppTextarea
              v-model="form.reason"
              :label="$t('hr.leave.reason')"
              rows="3"
              maxlength="1000"
              counter
            />
          </VCol>
          <VCol cols="12">
            <VFileInput
              v-model="files"
              :label="$t(requiresAttachment ? 'hr.leave.attachments_required' : 'hr.leave.attachments')"
              :hint="$t('hr.leave.attachments_hint', { max: MAX_FILES, size: MAX_FILE_MB })"
              persistent-hint
              accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
              multiple
              chips
              prepend-icon=""
              prepend-inner-icon="tabler-paperclip"
              :error-messages="fileError"
            />
          </VCol>
          <VCol
            v-if="mode === 'hr' && canRecord"
            cols="12"
          >
            <VSwitch
              v-model="form.record_as_approved"
              :label="$t('hr.leave.record_as_approved')"
              :hint="$t('hr.leave.record_as_approved_hint')"
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
          :disabled="!canSubmit"
          @click="submit"
        >
          {{ $t(form.record_as_approved && mode === 'hr' ? 'hr.leave.record' : 'hr.leave.submit') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.hr-leave-preview {
  padding: 12px 16px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}
</style>
