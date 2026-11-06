<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { useHrCrudTable } from '@/composables/useHrCrudTable'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrAttendancePeriodsApi } from '@/plugins/apis/hrRequest'
import { formatHrDateTime } from '@/utils/hrFormat'
import { formatAmount, formatDays, PERIOD_STATUS_COLORS } from '@/utils/hrLeave'
import { endOfMonth, formatMinutes, startOfMonth, todayIso } from '@/utils/hrShifts'
import HrAttendancePeriodSummariesDialog from '@/views/hr/attendance-processing/HrAttendancePeriodSummariesDialog.vue'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

// Payroll periods: draft -> processed (per-employee summaries) -> approved (days locked, figures served to
// payroll). Approving needs every exception of the period reviewed; a period can't overlap another one of the
// same branch, so no day is counted twice. Reopening (admin, with a reason) unlocks the days.

const props = defineProps({
  canEdit: { type: Boolean, default: false },
  canAdmin: { type: Boolean, default: false },
})

const emit = defineEmits(['changed'])

const { t, locale } = useI18n()
const { lookups } = useHrLookups()

const statusFilter = ref(null)

const emptyForm = () => {
  const today = todayIso()

  return { name: '', branch_id: null, period_start: startOfMonth(today), period_end: endOfMonth(today), notes: '' }
}

const {
  items, total, loading, options, updateOptions, fetchItems,
  isDialogVisible, editingId, form, saving, openDialog, openCreateDialog, save,
  confirm, askConfirm, runConfirmed,
} = useHrCrudTable(hrAttendancePeriodsApi, {
  filters: () => ({ status: statusFilter.value || undefined }),
  emptyForm,
  toForm: item => ({ name: item.name, branch_id: item.branch_id, period_start: item.period_start, period_end: item.period_end, notes: item.notes ?? '' }),
  toPayload: payload => ({ ...payload, branch_id: payload.branch_id || null }),
})

const notify = message => window.Snackbar?.value?.exposevisibleSnackbar?.(message, 'success')

const busyId = ref(null)

const runAction = (item, request) => {
  busyId.value = item.id
  request.then(response => {
    notify(response.data?.message || t('saved_successfully'))
    fetchItems()
    emit('changed')
  }).catch(() => {}).finally(() => {
    busyId.value = null
  })
}

const processPeriod = item => runAction(item, hrAttendancePeriodsApi.process(item.id))

const approveTarget = ref(null)

const confirmApprove = () => {
  const item = approveTarget.value

  approveTarget.value = null
  if (item)
    runAction(item, hrAttendancePeriodsApi.approve(item.id))
}

const reopenTarget = ref(null)
const reopenReason = ref('')

const openReopen = item => {
  reopenTarget.value = item
  reopenReason.value = ''
}

const confirmReopen = () => {
  const item = reopenTarget.value

  reopenTarget.value = null
  runAction(item, hrAttendancePeriodsApi.reopen(item.id, reopenReason.value.trim()))
}

const summariesOpen = ref(false)
const summariesPeriod = ref(null)

const openSummaries = item => {
  summariesPeriod.value = item
  summariesOpen.value = true
}

const headers = computed(() => [
  { title: t('hr.fields.name'), key: 'name', sortable: false },
  { title: t('hr.attendance_processing.period'), key: 'period_start' },
  { title: t('hr.fields.branch'), key: 'branch', sortable: false },
  { title: t('hr.attendance_processing.totals'), key: 'totals', sortable: false },
  { title: t('Status'), key: 'status' },
  { title: t('Actions'), key: 'actions', sortable: false },
])
</script>

<template>
  <div>
    <VCardText>
      <VRow>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <AppSelect
            v-model="statusFilter"
            :items="lookups.attendance_period_statuses"
            :placeholder="$t('Status')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          v-if="props.canEdit"
          cols="12"
          sm="6"
          md="9"
          class="d-flex align-center justify-end"
        >
          <VBtn
            prepend-icon="tabler-plus"
            @click="openCreateDialog"
          >
            {{ $t('hr.attendance_processing.add_period') }}
          </VBtn>
        </VCol>
      </VRow>
      <VAlert
        type="info"
        variant="tonal"
        density="compact"
        class="mt-4"
      >
        {{ $t('hr.attendance_processing.periods_hint') }}
      </VAlert>
    </VCardText>
    <VDivider />

    <VDataTableServer
      v-model:items-per-page="options.itemsPerPage"
      v-model:page="options.page"
      :items="items"
      :items-length="total"
      :headers="headers"
      :loading="loading"
      class="text-no-wrap"
      @update:options="updateOptions"
    >
      <template #item.name="{ item }">
        <div class="font-weight-medium">
          {{ item.raw.name }}
        </div>
        <div
          v-if="item.raw.approved_at"
          class="text-caption hr-page__muted"
        >
          {{ $t('hr.attendance_processing.approved_by', { name: item.raw.approved_by?.name ?? '—', at: formatHrDateTime(item.raw.approved_at, locale) }) }}
        </div>
        <div
          v-else-if="item.raw.reopen_reason"
          class="text-caption text-warning"
        >
          {{ $t('hr.attendance_processing.reopened_reason', { reason: item.raw.reopen_reason }) }}
        </div>
      </template>
      <template #item.period_start="{ item }">
        <span dir="ltr">{{ item.raw.period_start }} → {{ item.raw.period_end }}</span>
        <div class="text-caption hr-page__muted">
          {{ $t('hr.attendance_processing.days_count', { days: item.raw.days }) }}
        </div>
      </template>
      <template #item.branch="{ item }">
        {{ item.raw.branch_name || $t('hr.attendance_processing.all_branches') }}
      </template>
      <template #item.totals="{ item }">
        <div
          v-if="item.raw.totals"
          class="d-flex flex-wrap gap-1"
        >
          <VChip
            size="x-small"
            label
          >
            {{ $t('hr.attendance_processing.employees_count', { count: item.raw.employees_count ?? 0 }) }}
          </VChip>
          <VChip
            size="x-small"
            label
            color="error"
          >
            {{ $t('hr.attendance_processing.absence_short', { days: formatDays(item.raw.totals.absence_days) }) }}
          </VChip>
          <VChip
            size="x-small"
            label
            color="warning"
          >
            {{ $t('hr.attendance_processing.late_short', { time: formatMinutes(item.raw.totals.late_minutes ?? 0, t) }) }}
          </VChip>
          <VChip
            v-if="item.raw.totals.unresolved_exceptions"
            size="x-small"
            label
            color="error"
          >
            {{ $t('hr.attendance_processing.unresolved_short', { count: item.raw.totals.unresolved_exceptions }) }}
          </VChip>
          <VChip
            v-if="item.raw.totals.additional_records"
            size="x-small"
            label
            color="success"
          >
            {{ $t('hr.attendance_processing.additional_short', { days: formatDays(item.raw.totals.additional_days), amount: formatAmount(item.raw.totals.additional_amount) }) }}
          </VChip>
          <VChip
            v-if="item.raw.totals.additional_missing_salary"
            size="x-small"
            label
            color="error"
          >
            {{ $t('hr.attendance_processing.additional_missing_salary_short', { count: item.raw.totals.additional_missing_salary }) }}
          </VChip>
        </div>
        <span
          v-else
          class="hr-page__muted"
        >{{ $t('hr.attendance_processing.not_processed') }}</span>
      </template>
      <template #item.status="{ item }">
        <VChip
          size="small"
          label
          :color="PERIOD_STATUS_COLORS[item.raw.status]"
        >
          {{ item.raw.status_label }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <VBtn
            v-if="item.raw.totals"
            icon
            variant="text"
            size="small"
            @click="openSummaries(item.raw)"
          >
            <VIcon icon="tabler-report-analytics" />
            <VTooltip activator="parent">
              {{ $t('hr.attendance_processing.summaries') }}
            </VTooltip>
          </VBtn>
          <template v-if="item.raw.status !== 'approved'">
            <VBtn
              v-if="props.canEdit"
              icon
              variant="text"
              size="small"
              :loading="busyId === item.raw.id"
              @click="processPeriod(item.raw)"
            >
              <VIcon icon="tabler-refresh" />
              <VTooltip activator="parent">
                {{ $t('hr.attendance_processing.process_period') }}
              </VTooltip>
            </VBtn>
            <VBtn
              v-if="props.canAdmin && item.raw.status === 'processed'"
              icon
              variant="text"
              size="small"
              color="success"
              @click="approveTarget = item.raw"
            >
              <VIcon icon="tabler-circle-check" />
              <VTooltip activator="parent">
                {{ $t('hr.attendance_processing.approve_period') }}
              </VTooltip>
            </VBtn>
            <VBtn
              v-if="props.canEdit"
              icon
              variant="text"
              size="small"
              @click="openDialog(item.raw)"
            >
              <VIcon icon="tabler-edit" />
            </VBtn>
            <VBtn
              v-if="props.canAdmin"
              icon
              variant="text"
              size="small"
              color="error"
              @click="askConfirm('delete', item.raw)"
            >
              <VIcon icon="tabler-trash" />
            </VBtn>
          </template>
          <VBtn
            v-else-if="props.canAdmin"
            icon
            variant="text"
            size="small"
            color="warning"
            @click="openReopen(item.raw)"
          >
            <VIcon icon="tabler-lock-open" />
            <VTooltip activator="parent">
              {{ $t('hr.attendance_processing.reopen_period') }}
            </VTooltip>
          </VBtn>
        </div>
      </template>
    </VDataTableServer>

    <VDialog
      v-model="isDialogVisible"
      max-width="600"
    >
      <VCard :title="$t(editingId ? 'hr.attendance_processing.edit_period' : 'hr.attendance_processing.add_period')">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="form.name"
                :label="$t('hr.fields.name')"
                :placeholder="$t('hr.attendance_processing.period_name_placeholder')"
              />
            </VCol>
            <VCol cols="6">
              <AppDateTimePicker
                v-model="form.period_start"
                :label="$t('hr.fields.start_date')"
              />
            </VCol>
            <VCol cols="6">
              <AppDateTimePicker
                v-model="form.period_end"
                :label="$t('hr.fields.end_date')"
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="form.branch_id"
                :items="lookups.branches"
                :label="$t('hr.fields.branch')"
                :placeholder="$t('hr.attendance_processing.all_branches')"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.notes"
                :label="$t('hr.fields.notes')"
                rows="2"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isDialogVisible = false"
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

    <HrConfirmDialog
      v-model="confirm.visible"
      :action="confirm.action"
      @confirm="runConfirmed"
    />
    <HrConfirmDialog
      :model-value="!!approveTarget"
      action="approve"
      :message="$t('hr.attendance_processing.confirm_approve')"
      :confirm-label="$t('hr.attendance_processing.approve_period')"
      @update:model-value="approveTarget = null"
      @confirm="confirmApprove"
    />

    <VDialog
      :model-value="!!reopenTarget"
      max-width="500"
      @update:model-value="reopenTarget = null"
    >
      <VCard :title="$t('hr.attendance_processing.reopen_period')">
        <VCardText>
          <VAlert
            type="warning"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ $t('hr.attendance_processing.reopen_hint') }}
          </VAlert>
          <AppTextarea
            v-model="reopenReason"
            :label="$t('hr.attendance_processing.reopen_reason')"
            rows="2"
            counter="1000"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="reopenTarget = null"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="warning"
            :disabled="reopenReason.trim().length < 5"
            @click="confirmReopen"
          >
            {{ $t('hr.attendance_processing.reopen_period') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <HrAttendancePeriodSummariesDialog
      v-model="summariesOpen"
      :period="summariesPeriod"
    />
  </div>
</template>
