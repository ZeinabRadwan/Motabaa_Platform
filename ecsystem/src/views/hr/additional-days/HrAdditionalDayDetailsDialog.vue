<script setup>
import HrInfoGrid from '@/components/hr/HrInfoGrid.vue'
import { hrAdditionalDaysApi } from '@/plugins/apis/hrRequest'
import { APPROVAL_STATUS_COLORS, formatHrDateTime } from '@/utils/hrFormat'
import { ADDITIONAL_DAY_STATUS_COLORS, formatAmount, formatDays } from '@/utils/hrLeave'
import { useI18n } from 'vue-i18n'

// Additional working day details: the record, its payroll status (period, paid amount or estimate), the approval
// steps and the audit history of every change. Records can be cancelled with a reason when allowed (can_cancel).

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  recordId: { type: Number, default: null },
})

const emit = defineEmits(['update:modelValue', 'changed'])

const { t, te, locale } = useI18n()

const record = ref(null)
const audit = ref([])
const loading = ref(false)
const cancelling = ref(false)
const cancelReason = ref('')
const saving = ref(false)

const load = () => {
  if (!props.recordId)
    return
  loading.value = true
  hrAdditionalDaysApi.history(props.recordId).then(({ data }) => {
    record.value = data.data.record
    audit.value = data.data.audit ?? []
  }).catch(() => {
    record.value = null
    audit.value = []
  }).finally(() => {
    loading.value = false
  })
}

watch(() => [props.modelValue, props.recordId], ([open]) => {
  if (!open)
    return
  cancelling.value = false
  cancelReason.value = ''
  record.value = null
  audit.value = []
  load()
})

const AUDIT_KEYS = ['work_date', 'days', 'reason', 'status', 'day_type', 'branch_id', 'paid_period_id', 'amount', 'approval_request_id']

const fieldLabel = key => (te(`hr.additional_days.fields.${key}`) ? t(`hr.additional_days.fields.${key}`) : key)

const auditValue = (key, value) => {
  if (value === null || value === undefined || value === '')
    return '—'
  if (key === 'status')
    return t(`hr.additional_days.statuses.${value}`, value)
  if (key === 'work_date')
    return String(value).slice(0, 10)

  return String(value)
}

const auditChanges = log => {
  const keys = Object.keys({ ...(log.old_values ?? {}), ...(log.new_values ?? {}) })
    .filter(key => AUDIT_KEYS.includes(key))

  return keys.map(key => ({
    key,
    label: fieldLabel(key),
    old: log.event === 'created' ? null : auditValue(key, log.old_values?.[key]),
    new: auditValue(key, log.new_values?.[key]),
  }))
}

const payLine = item => {
  if (item.amount !== null && item.amount !== undefined)
    return `${formatAmount(item.amount)} (${formatAmount(item.gross_salary)} / 30 × ${formatDays(item.days)})`
  if (item.estimated)
    return `≈ ${formatAmount(item.estimated.amount)} (${t('hr.additional_days.estimated')})`

  return t('hr.additional_days.no_salary')
}

/** "Name · date" rows for the actions that happened (created, submitted, approved, ...). */
const actorRows = item => [
  ['created', item.created_by, item.created_at],
  ['submitted', item.submitted_by, item.submitted_at],
  ['approved', item.approved_by, item.approved_at],
  ['rejected', item.rejected_by, item.rejected_at],
  ['cancelled', item.cancelled_by, item.cancelled_at],
].filter(([, , at]) => at)
  .map(([key, user, at]) => ({ label: t(`hr.additional_days.${key}`), value: `${user?.name ?? '—'} · ${formatHrDateTime(at, locale.value)}` }))

const info = computed(() => {
  const item = record.value
  if (!item)
    return []

  return [
    { label: t('hr.fields.employee'), value: `${item.employee?.name ?? '—'} (${item.employee?.employee_number ?? ''})` },
    { label: t('hr.fields.branch'), value: item.branch_name },
    { label: t('hr.additional_days.work_date'), value: item.work_date, ltr: true },
    { label: t('hr.additional_days.day_type'), value: item.day_type_label },
    { label: t('hr.additional_days.days'), value: formatDays(item.days) },
    { label: t('hr.additional_days.payroll_period'), value: item.attendance_period?.name ?? t('hr.additional_days.no_period') },
    ...(item.paid_period ? [{ label: t('hr.additional_days.paid_in'), value: item.paid_period.name }] : []),
    ...(['draft', 'pending', 'approved', 'paid'].includes(item.status) ? [{ label: t('hr.additional_days.amount'), value: payLine(item) }] : []),
    ...actorRows(item),
    ...(item.paid_at ? [{ label: t('hr.additional_days.paid_at'), value: formatHrDateTime(item.paid_at, locale.value) }] : []),
  ]
})

const confirmCancel = () => {
  saving.value = true
  hrAdditionalDaysApi.cancel(record.value.id, cancelReason.value.trim()).then(response => {
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
    max-width="820"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard :loading="loading">
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2 flex-wrap">
          {{ $t('hr.additional_days.details') }}
          <template v-if="record">
            <VChip
              size="small"
              label
              :color="ADDITIONAL_DAY_STATUS_COLORS[record.status]"
            >
              {{ record.status_label }}
            </VChip>
            <VChip
              v-if="record.carried_forward"
              size="small"
              label
              color="warning"
              prepend-icon="tabler-arrow-forward-up"
            >
              {{ $t('hr.additional_days.carried_forward') }}
            </VChip>
          </template>
        </VCardTitle>
      </VCardItem>
      <VCardText v-if="record">
        <HrInfoGrid :items="info" />

        <div class="mt-4">
          <div class="hr-page__muted">
            {{ $t('hr.additional_days.reason') }}
          </div>
          <div class="text-body-1 hr-additional-text">
            {{ record.reason }}
          </div>
        </div>
        <div
          v-if="record.rejection_reason"
          class="mt-3"
        >
          <div class="hr-page__muted">
            {{ $t('hr.additional_days.rejection_reason') }}
          </div>
          <div class="text-body-1 hr-additional-text">
            {{ record.rejection_reason }}
          </div>
        </div>
        <div
          v-if="record.cancel_reason"
          class="mt-3"
        >
          <div class="hr-page__muted">
            {{ $t('hr.additional_days.cancel_reason') }}
          </div>
          <div class="text-body-1 hr-additional-text">
            {{ record.cancel_reason }}
          </div>
        </div>

        <div class="mt-5">
          <div class="text-subtitle-1 font-weight-medium mb-2">
            {{ $t('hr.leave.approval_history') }}
          </div>
          <div
            v-if="!record.approval"
            class="hr-page__muted"
          >
            {{ $t('hr.additional_days.not_submitted') }}
          </div>
          <VTimeline
            v-else
            density="compact"
            align="start"
            side="end"
            truncate-line="both"
          >
            <VTimelineItem
              v-for="step in record.approval.steps"
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

        <div class="mt-5">
          <div class="text-subtitle-1 font-weight-medium mb-2">
            {{ $t('hr.additional_days.change_history') }}
          </div>
          <VTable density="compact">
            <thead>
              <tr>
                <th>{{ $t('hr.audit.event') }}</th>
                <th>{{ $t('hr.audit.by') }}</th>
                <th>{{ $t('hr.additional_days.changes') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="log in audit"
                :key="log.id"
              >
                <td class="text-no-wrap">
                  <div>{{ log.event_label }}</div>
                  <div class="text-caption hr-page__muted">
                    {{ formatHrDateTime(log.created_at, locale) }}
                  </div>
                </td>
                <td>{{ log.user?.name ?? '—' }}</td>
                <td class="py-1">
                  <div
                    v-for="change in auditChanges(log)"
                    :key="change.key"
                    class="text-body-2"
                  >
                    <span class="font-weight-medium">{{ change.label }}:</span>
                    <template v-if="change.old !== null">
                      <span class="hr-page__muted"> {{ change.old }} → </span>
                    </template>
                    {{ change.new }}
                  </div>
                </td>
              </tr>
            </tbody>
          </VTable>
        </div>

        <div
          v-if="cancelling"
          class="mt-4"
        >
          <AppTextarea
            v-model="cancelReason"
            :label="$t('hr.additional_days.cancel_reason')"
            rows="2"
            maxlength="1000"
            counter
            autofocus
          />
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
        <template v-if="record?.can_cancel">
          <VBtn
            v-if="!cancelling"
            color="error"
            variant="tonal"
            @click="cancelling = true"
          >
            {{ $t('hr.additional_days.cancel') }}
          </VBtn>
          <VBtn
            v-else
            color="error"
            :loading="saving"
            :disabled="cancelReason.trim().length < 5"
            @click="confirmCancel"
          >
            {{ $t('hr.additional_days.confirm_cancel') }}
          </VBtn>
        </template>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss" scoped>
.hr-additional-text {
  white-space: pre-line;
}
</style>
