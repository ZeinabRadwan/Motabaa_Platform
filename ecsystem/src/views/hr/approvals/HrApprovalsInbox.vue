<script setup>
import { hrApprovalsApi } from '@/plugins/apis/hrRequest'
import { APPROVAL_STATUS_COLORS, formatHrDateTime } from '@/utils/hrFormat'
import { downloadLeaveAttachment, formatDays } from '@/utils/hrLeave'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

const STATUSES = ['pending', 'approved', 'rejected', 'cancelled']

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()

const canSeeAll = computed(() => can('access_hr-approvals', 'access_hr-approvals'))

const scopes = computed(() => [
  { value: 'awaiting_me', title: t('hr.approvals.scopes.awaiting_me'), icon: 'tabler-inbox' },
  { value: 'mine', title: t('hr.approvals.scopes.mine'), icon: 'tabler-send' },
  ...(canSeeAll.value ? [{ value: 'all', title: t('hr.approvals.scopes.all'), icon: 'tabler-list' }] : []),
])

const scope = computed({
  get: () => (scopes.value.some(item => item.value === route.query.scope) ? route.query.scope : 'awaiting_me'),
  set: value => router.replace({ query: { ...route.query, scope: value } }),
})

const status = ref(null)
const statusItems = computed(() => STATUSES.map(value => ({ value, title: t(`hr.approvals.statuses.${value}`) })))

const items = ref([])
const total = ref(0)
const loading = ref(false)
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })

const fetchRequests = () => {
  loading.value = true

  return hrApprovalsApi.list({
    scope: scope.value,
    status: scope.value === 'awaiting_me' ? undefined : (status.value || undefined),
    options: options.value,
  }).then(response => {
    items.value = response.data.data
    total.value = response.data.total
  }).catch(() => {
    items.value = []
    total.value = 0
  }).finally(() => {
    loading.value = false
  })
}

watchServerTableFetch(fetchRequests, {
  filters: () => [scope.value, status.value],
  options,
})

const headers = computed(() => [
  { title: t('hr.fields.request_type'), key: 'request_type_label', sortable: false },
  { title: t('hr.fields.employee'), key: 'employee', sortable: false },
  { title: t('hr.approvals.requested_by'), key: 'requested_by', sortable: false },
  { title: t('hr.approvals.submitted_at'), key: 'submitted_at' },
  { title: t('hr.workflows.steps'), key: 'steps', sortable: false },
  { title: t('Status'), key: 'status' },
  { title: t('Actions'), key: 'actions', sortable: false },
])

// ── Decision dialog ─────────────────────────────────────────────────────
const decision = ref({ visible: false, action: null, item: null, comment: '' })
const deciding = ref(false)

const openDecision = (action, item) => {
  decision.value = { visible: true, action, item, comment: '' }
}

const submitDecision = () => {
  const { action, item, comment } = decision.value

  deciding.value = true
  hrApprovalsApi[action](item.id, comment || null).then(response => {
    decision.value.visible = false
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    fetchRequests()
  }).catch(() => {}).finally(() => {
    deciding.value = false
  })
}

const decisionColor = action => ({ approve: 'success', reject: 'error', cancel: 'secondary' }[action] ?? 'primary')
</script>

<template>
  <VCard>
    <VTabs
      v-model="scope"
      class="hr-page__tabs"
    >
      <VTab
        v-for="item in scopes"
        :key="item.value"
        :value="item.value"
      >
        <VIcon
          :icon="item.icon"
          start
        />
        {{ item.title }}
      </VTab>
    </VTabs>
    <VDivider />

    <VCardText v-if="scope !== 'awaiting_me'">
      <VRow>
        <VCol
          cols="12"
          sm="4"
        >
          <AppSelect
            v-model="status"
            :items="statusItems"
            :placeholder="$t('Status')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDataTableServer
      v-model:items-per-page="options.itemsPerPage"
      v-model:page="options.page"
      :items="items"
      :items-length="total"
      :headers="headers"
      :loading="loading"
      class="text-no-wrap"
      @update:options="applyServerTableOptions(options, $event)"
    >
      <template #item.request_type_label="{ item }">
        <div>{{ item.raw.request_type_label }}</div>
        <div
          v-if="item.raw.subject?.kind === 'leave_request'"
          class="text-caption hr-page__muted"
        >
          {{ item.raw.subject.leave_type }} ·
          <span dir="ltr">{{ item.raw.subject.start_date }} → {{ item.raw.subject.end_date }}</span>
          · {{ $t('hr.approvals.leave_days', { days: formatDays(item.raw.subject.days) }) }}
          <VIcon
            v-if="item.raw.subject.attachments?.length"
            icon="tabler-paperclip"
            size="14"
          />
        </div>
        <div
          v-else-if="item.raw.subject?.kind === 'additional_work_day'"
          class="text-caption hr-page__muted"
        >
          <span dir="ltr">{{ item.raw.subject.work_date }}</span>
          · {{ item.raw.subject.day_type_label }}
          · {{ $t('hr.approvals.leave_days', { days: formatDays(item.raw.subject.days) }) }}
        </div>
      </template>
      <template #item.employee="{ item }">
        {{ item.raw.employee?.name || '—' }}
      </template>
      <template #item.requested_by="{ item }">
        {{ item.raw.requested_by?.name || '—' }}
      </template>
      <template #item.submitted_at="{ item }">
        {{ formatHrDateTime(item.raw.submitted_at, locale) }}
      </template>
      <template #item.steps="{ item }">
        <div class="hr-steps py-2">
          <VTooltip
            v-for="step in item.raw.steps"
            :key="step.step_order"
            location="top"
          >
            <template #activator="{ props: tooltipProps }">
              <VChip
                v-bind="tooltipProps"
                size="small"
                label
                :variant="step.step_order === item.raw.current_step && item.raw.status === 'pending' ? 'elevated' : 'tonal'"
                :color="APPROVAL_STATUS_COLORS[step.status]"
              >
                {{ step.step_order }}. {{ step.approver_value_label || step.approver_type_label }}
              </VChip>
            </template>
            <div>{{ step.approver_type_label }}</div>
            <div v-if="step.acted_by">
              {{ step.acted_by.name }} · {{ formatHrDateTime(step.acted_at, locale) }}
            </div>
            <div v-if="step.comment">
              {{ step.comment }}
            </div>
          </VTooltip>
        </div>
      </template>
      <template #item.status="{ item }">
        <VChip
          size="small"
          label
          :color="APPROVAL_STATUS_COLORS[item.raw.status]"
        >
          {{ item.raw.status_label }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <template v-if="item.raw.can_act">
            <VBtn
              size="small"
              color="success"
              variant="tonal"
              @click="openDecision('approve', item.raw)"
            >
              {{ $t('hr.approvals.approve') }}
            </VBtn>
            <VBtn
              size="small"
              color="error"
              variant="tonal"
              @click="openDecision('reject', item.raw)"
            >
              {{ $t('hr.approvals.reject') }}
            </VBtn>
          </template>
          <VBtn
            v-if="item.raw.can_cancel && item.raw.status === 'pending'"
            size="small"
            variant="text"
            @click="openDecision('cancel', item.raw)"
          >
            {{ $t('hr.approvals.cancel_request') }}
          </VBtn>
        </div>
      </template>
      <template #no-data>
        <div class="pa-6 text-center hr-page__muted">
          {{ $t(scope === 'awaiting_me' ? 'hr.approvals.empty_awaiting' : 'hr.approvals.empty') }}
        </div>
      </template>
    </VDataTableServer>

    <VDialog
      v-model="decision.visible"
      max-width="520"
    >
      <VCard
        v-if="decision.item"
        :title="`${$t(`hr.approvals.${decision.action === 'cancel' ? 'cancel_request' : decision.action}`)} — ${decision.item.request_type_label}`"
      >
        <VCardText>
          <p
            v-if="decision.item.employee"
            class="mb-3"
          >
            {{ $t('hr.fields.employee') }}: <strong>{{ decision.item.employee.name }}</strong>
          </p>
          <div
            v-if="decision.item.subject?.kind === 'leave_request'"
            class="mb-3"
          >
            <div>
              {{ decision.item.subject.leave_type }} · {{ $t('hr.approvals.leave_days', { days: formatDays(decision.item.subject.days) }) }}
              · {{ $t(decision.item.subject.is_paid ? 'hr.leave.paid_yes' : 'hr.leave.paid_no') }}
            </div>
            <div
              class="hr-page__muted"
              dir="ltr"
            >
              {{ decision.item.subject.start_date }} → {{ decision.item.subject.end_date }}
            </div>
            <p
              v-if="decision.item.subject.reason"
              class="mt-2 mb-0"
            >
              {{ $t('hr.leave.reason') }}: {{ decision.item.subject.reason }}
            </p>
            <div
              v-if="decision.item.subject.attachments?.length"
              class="d-flex flex-wrap gap-2 mt-2"
            >
              <VChip
                v-for="file in decision.item.subject.attachments"
                :key="file.id"
                size="small"
                label
                prepend-icon="tabler-paperclip"
                @click="downloadLeaveAttachment(decision.item.subject.leave_request_id, file)"
              >
                {{ file.name }}
              </VChip>
            </div>
          </div>
          <div
            v-else-if="decision.item.subject?.kind === 'additional_work_day'"
            class="mb-3"
          >
            <div>
              <span dir="ltr">{{ decision.item.subject.work_date }}</span>
              · {{ decision.item.subject.day_type_label }}
              · {{ $t('hr.approvals.leave_days', { days: formatDays(decision.item.subject.days) }) }}
            </div>
            <p
              v-if="decision.item.subject.reason"
              class="mt-2 mb-0"
            >
              {{ $t('hr.additional_days.reason') }}: {{ decision.item.subject.reason }}
            </p>
          </div>
          <AppTextarea
            v-model="decision.comment"
            :label="$t('hr.approvals.comment')"
            rows="3"
            maxlength="1000"
            counter
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="decision.visible = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            :color="decisionColor(decision.action)"
            :loading="deciding"
            @click="submitDecision"
          >
            {{ $t(`hr.approvals.${decision.action === 'cancel' ? 'cancel_request' : decision.action}`) }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>
