<script setup>
import HrInfoGrid from '@/components/hr/HrInfoGrid.vue'
import { hrLeaveRequestsApi, hrMyLeaveApi } from '@/plugins/apis/hrRequest'
import { APPROVAL_STATUS_COLORS, formatHrDateTime } from '@/utils/hrFormat'
import { LEAVE_STATUS_COLORS, downloadLeaveAttachment, formatDays } from '@/utils/hrLeave'
import { useI18n } from 'vue-i18n'

// Leave request details: dates, days, reason, supporting documents and the approval history (each step with
// its approver, decision, date and comment). Pending requests can be cancelled with a reason; approved leave
// only by leave administrators (HR mode, canCancelApproved).

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  leave: { type: Object, default: null },
  mode: { type: String, default: 'hr' },
  canCancel: { type: Boolean, default: false },
  canCancelApproved: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'changed'])

const { t, locale } = useI18n()

const cancelling = ref(false)
const cancelReason = ref('')
const saving = ref(false)

watch(() => props.modelValue, open => {
  if (open) {
    cancelling.value = false
    cancelReason.value = ''
  }
})

const cancellable = computed(() => props.leave && props.canCancel
  && (props.leave.status === 'pending' || (props.leave.status === 'approved' && props.canCancelApproved)))

const info = computed(() => {
  const leave = props.leave
  if (!leave)
    return []

  return [
    ...(props.mode === 'hr' ? [{ label: t('hr.fields.employee'), value: leave.employee?.name }] : []),
    { label: t('hr.leave.leave_type'), value: leave.leave_type?.name },
    { label: t('hr.leave.paid'), value: t(leave.leave_type?.is_paid ? 'hr.leave.paid_yes' : 'hr.leave.paid_no') },
    { label: t('hr.leave.start_date'), value: leave.start_date, ltr: true },
    { label: t('hr.leave.end_date'), value: leave.end_date, ltr: true },
    { label: t('hr.leave.days'), value: `${formatDays(leave.days)} (${t('hr.leave.of_calendar_days', { days: leave.calendar_days })})` },
    { label: t('hr.leave.source'), value: leave.source_label },
    { label: t('hr.approvals.requested_by'), value: leave.requested_by?.name },
    { label: t('hr.approvals.submitted_at'), value: formatHrDateTime(leave.requested_at, locale.value) },
    ...(leave.decided_at ? [{ label: t('hr.leave.decided'), value: `${leave.decided_by?.name ?? '—'} · ${formatHrDateTime(leave.decided_at, locale.value)}` }] : []),
    ...(leave.cancelled_at ? [{ label: t('hr.leave.cancelled'), value: `${leave.cancelled_by?.name ?? '—'} · ${formatHrDateTime(leave.cancelled_at, locale.value)}` }] : []),
  ]
})

const confirmCancel = () => {
  const api = props.mode === 'self' ? hrMyLeaveApi : hrLeaveRequestsApi

  saving.value = true
  api.cancel(props.leave.id, cancelReason.value.trim()).then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    emit('update:modelValue', false)
    emit('changed')
  }).catch(() => {}).finally(() => {
    saving.value = false
  })
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="760"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard v-if="leave">
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2 flex-wrap">
          {{ $t('hr.leave.request_details') }}
          <VChip
            size="small"
            label
            :color="LEAVE_STATUS_COLORS[leave.status]"
          >
            {{ leave.status_label }}
          </VChip>
        </VCardTitle>
      </VCardItem>
      <VCardText>
        <HrInfoGrid :items="info" />

        <div class="mt-4">
          <div class="hr-page__muted">
            {{ $t('hr.leave.reason') }}
          </div>
          <div class="text-body-1 hr-leave-reason">
            {{ leave.reason }}
          </div>
        </div>
        <div
          v-if="leave.cancel_reason"
          class="mt-3"
        >
          <div class="hr-page__muted">
            {{ $t('hr.leave.cancel_reason') }}
          </div>
          <div class="text-body-1">
            {{ leave.cancel_reason }}
          </div>
        </div>

        <div
          v-if="leave.attachments?.length"
          class="mt-4"
        >
          <div class="hr-page__muted mb-1">
            {{ $t('hr.leave.attachments') }}
          </div>
          <div class="d-flex flex-wrap gap-2">
            <VChip
              v-for="file in leave.attachments"
              :key="file.id"
              prepend-icon="tabler-download"
              @click="downloadLeaveAttachment(leave.id, file)"
            >
              {{ file.name }}
            </VChip>
          </div>
        </div>

        <div class="mt-5">
          <div class="text-subtitle-1 font-weight-medium mb-2">
            {{ $t('hr.leave.approval_history') }}
          </div>
          <div
            v-if="!leave.approval"
            class="hr-page__muted"
          >
            {{ $t('hr.leave.no_approval') }}
          </div>
          <VTimeline
            v-else
            density="compact"
            align="start"
            side="end"
            truncate-line="both"
          >
            <VTimelineItem
              v-for="step in leave.approval.steps"
              :key="step.step_order"
              :dot-color="APPROVAL_STATUS_COLORS[step.status] || 'secondary'"
              size="x-small"
            >
              <div class="d-flex flex-wrap align-center gap-2">
                <strong>{{ step.step_order }}. {{ step.approver_value_label || step.approver_type_label }}</strong>
                <VChip
                  size="x-small"
                  label
                  :color="APPROVAL_STATUS_COLORS[step.status]"
                >
                  {{ $t(`hr.approvals.statuses.${step.status}`, step.status) }}
                </VChip>
              </div>
              <div
                v-if="step.acted_by"
                class="text-caption"
              >
                {{ step.acted_by.name }} · {{ formatHrDateTime(step.acted_at, locale) }}
              </div>
              <div
                v-if="step.comment"
                class="text-body-2"
              >
                {{ step.comment }}
              </div>
            </VTimelineItem>
          </VTimeline>
        </div>

        <div
          v-if="cancelling"
          class="mt-4"
        >
          <AppTextarea
            v-model="cancelReason"
            :label="$t('hr.leave.cancel_reason')"
            rows="2"
            maxlength="1000"
            counter
            autofocus
          />
          <div
            v-if="leave.status === 'approved'"
            class="text-caption hr-page__muted mt-1"
          >
            {{ $t('hr.leave.cancel_approved_hint') }}
          </div>
        </div>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Close') }}
        </VBtn>
        <template v-if="cancellable">
          <VBtn
            v-if="!cancelling"
            color="error"
            variant="tonal"
            @click="cancelling = true"
          >
            {{ $t('hr.leave.cancel_leave') }}
          </VBtn>
          <VBtn
            v-else
            color="error"
            :loading="saving"
            :disabled="cancelReason.trim().length < 5"
            @click="confirmCancel"
          >
            {{ $t('hr.leave.confirm_cancel') }}
          </VBtn>
        </template>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.hr-leave-reason {
  white-space: pre-line;
}
</style>
