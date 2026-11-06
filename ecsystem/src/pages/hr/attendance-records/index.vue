<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrAttendanceRecordsApi } from '@/plugins/apis/hrRequest'
import { ATTENDANCE_METHODS, ATTENDANCE_STATUSES, canChangeRecord } from '@/utils/hrAttendanceRecords'
import { startOfMonth } from '@/utils/hrShifts'
import HrAttendanceRecordsTable from '@/views/hr/attendance/HrAttendanceRecordsTable.vue'
import HrAttendanceHistoryDialog from '@/views/hr/attendance-records/HrAttendanceHistoryDialog.vue'
import HrAttendanceImportDialog from '@/views/hr/attendance-records/HrAttendanceImportDialog.vue'
import HrBulkAttendanceDialog from '@/views/hr/attendance-records/HrBulkAttendanceDialog.vue'
import HrManualAttendanceDialog from '@/views/hr/attendance-records/HrManualAttendanceDialog.vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'

// Central attendance records: electronic (website / mobile GPS) and manual records together, each keeping its
// recording method. HR and branch managers enter and correct records here; every change keeps its history.

const { t } = useI18n()
const route = useRoute()
const { lookups } = useHrLookups()

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(new Date())

const filters = reactive({
  branchId: null,
  employeeId: Number(route.query.employee_id) || null,
  from: route.query.employee_id ? null : startOfMonth(today()),
  to: null,
  statuses: [],
  method: null,
  needsReview: false,
  corrected: false,
})

const params = computed(() => ({
  branch_id: filters.branchId || undefined,
  employee_id: filters.employeeId || undefined,
  from: filters.from || undefined,
  to: filters.to || undefined,
  attendance_status: filters.statuses.length ? filters.statuses : undefined,
  method: filters.method || undefined,
  needs_review: filters.needsReview ? 1 : undefined,
  corrected: filters.corrected ? 1 : undefined,
}))

const summary = ref(null)
const limits = computed(() => summary.value?.limits ?? {})

let summarySeq = 0

const loadSummary = () => {
  const seq = ++summarySeq

  hrAttendanceRecordsApi.summary(params.value).then(({ data }) => {
    if (seq === summarySeq)
      summary.value = data.data
  }).catch(() => {})
}

watch(() => JSON.stringify({ ...params.value, attendance_status: undefined, needs_review: undefined }), loadSummary, { immediate: true })

const count = (...statuses) => statuses.reduce((sum, status) => sum + (summary.value?.by_status?.[status] ?? 0), 0)

const sameStatuses = statuses => statuses.length === filters.statuses.length && statuses.every(status => filters.statuses.includes(status))

const cards = computed(() => [
  { key: 'total', icon: 'tabler-list-check', color: 'primary', value: summary.value?.total ?? 0, statuses: [] },
  { key: 'present', icon: 'tabler-circle-check', color: 'success', value: count('present'), statuses: ['present'] },
  { key: 'late', icon: 'tabler-clock-exclamation', color: 'warning', value: count('late', 'late_early_departure'), statuses: ['late', 'late_early_departure'] },
  { key: 'early_departure', icon: 'tabler-door-exit', color: 'warning', value: count('early_departure', 'late_early_departure'), statuses: ['early_departure', 'late_early_departure'] },
  { key: 'missing', icon: 'tabler-alert-circle', color: 'error', value: count('missing_check_in', 'missing_check_out'), statuses: ['missing_check_in', 'missing_check_out'] },
  { key: 'manual', icon: 'tabler-pencil', color: 'info', value: summary.value?.by_method?.manual ?? 0, method: 'manual' },
])

const selectCard = card => {
  if (card.method) {
    filters.method = filters.method === card.method ? null : card.method

    return
  }
  filters.statuses = sameStatuses(card.statuses) ? [] : [...card.statuses]
}

const cardActive = card => card.method ? filters.method === card.method : card.statuses.length > 0 && sameStatuses(card.statuses)

const statusItems = computed(() => ATTENDANCE_STATUSES.map(value => ({ value, title: t(`hr.attendance_records.statuses.${value}`) })))
const methodItems = computed(() => ATTENDANCE_METHODS.map(value => ({ value, title: t(`hr.attendance_records.methods.${value}`) })))

const searchEmployees = query => hrAttendanceRecordsApi.employees({
  q: query.q || undefined,
  ids: query.ids,
  branch_id: filters.branchId || undefined,
})

const table = ref(null)
const fetchRecords = query => hrAttendanceRecordsApi.list(query)

const formOpen = ref(false)
const editedRecord = ref(null)
const bulkOpen = ref(false)
const importOpen = ref(false)
const historyOpen = ref(false)
const historyRecordId = ref(null)

const openCreate = () => {
  editedRecord.value = null
  formOpen.value = true
}

const openEdit = record => {
  editedRecord.value = record
  formOpen.value = true
}

const openHistory = record => {
  historyRecordId.value = record.id
  historyOpen.value = true
}

const refresh = () => {
  table.value?.refresh()
  loadSummary()
}
</script>

<template>
  <div class="hr-page">
    <VRow class="mb-2">
      <VCol
        v-for="card in cards"
        :key="card.key"
        cols="6"
        sm="4"
        md="2"
      >
        <VCard
          class="hr-records-card"
          :class="{ 'hr-records-card--active': cardActive(card) }"
          :color="cardActive(card) ? card.color : undefined"
          :variant="cardActive(card) ? 'tonal' : 'elevated'"
          @click="selectCard(card)"
        >
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              :color="card.color"
              variant="tonal"
              rounded
              size="40"
            >
              <VIcon :icon="card.icon" />
            </VAvatar>
            <div>
              <div class="text-h5">
                {{ card.value }}
              </div>
              <div class="text-caption">
                {{ $t(`hr.attendance_records.cards.${card.key}`) }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mb-6">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppSelect
              v-model="filters.branchId"
              :items="lookups.branches"
              :placeholder="$t('hr.fields.branch')"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppAutocomplete
              v-model="filters.employeeId"
              :server-search="searchEmployees"
              :preload-key="filters.branchId"
              item-title="title"
              item-value="id"
              :placeholder="$t('hr.fields.employee')"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="6"
            sm="3"
            md="2"
          >
            <AppDateTimePicker
              v-model="filters.from"
              :placeholder="$t('from')"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="6"
            sm="3"
            md="2"
          >
            <AppDateTimePicker
              v-model="filters.to"
              :placeholder="$t('to')"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="2"
          >
            <AppSelect
              v-model="filters.method"
              :items="methodItems"
              :placeholder="$t('hr.attendance_records.method')"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="filters.statuses"
              :items="statusItems"
              :placeholder="$t('hr.attendance_log.status')"
              multiple
              chips
              closable-chips
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
            class="d-flex flex-wrap align-center gap-4"
          >
            <VSwitch
              v-model="filters.needsReview"
              :label="`${$t('hr.attendance_log.needs_review_only')}${summary ? ` (${summary.needs_review})` : ''}`"
              hide-details
            />
            <VSwitch
              v-model="filters.corrected"
              :label="$t('hr.attendance_records.corrected_only')"
              hide-details
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VCardItem>
        <VCardTitle>{{ $t('hr.attendance_records.list_title') }}</VCardTitle>
        <template #append>
          <div
            v-if="limits.can_edit"
            class="d-flex flex-wrap gap-2"
          >
            <VBtn
              variant="tonal"
              prepend-icon="tabler-file-import"
              @click="importOpen = true"
            >
              {{ $t('hr.attendance_records.import') }}
            </VBtn>
            <VBtn
              variant="tonal"
              prepend-icon="tabler-users-plus"
              @click="bulkOpen = true"
            >
              {{ $t('hr.attendance_records.bulk') }}
            </VBtn>
            <VBtn
              prepend-icon="tabler-plus"
              @click="openCreate"
            >
              {{ $t('hr.attendance_records.add') }}
            </VBtn>
          </div>
        </template>
      </VCardItem>
      <VCardText
        v-if="limits.can_edit"
        class="pt-0 text-caption hr-page__muted"
      >
        {{ limits.can_enter_historical ? $t('hr.attendance_records.limits_historical') : $t('hr.attendance_records.limits_days', { days: limits.max_days_back, date: limits.oldest_date }) }}
        <span v-if="limits.locked_until"> {{ $t('hr.attendance_records.limits_locked', { date: limits.locked_until }) }}</span>
      </VCardText>
      <VDivider />
      <HrAttendanceRecordsTable
        ref="table"
        :fetch="fetchRecords"
        :params="params"
        show-employee
        show-location-details
      >
        <template #actions="{ record }">
          <div class="d-flex justify-end">
            <VBtn
              v-if="canChangeRecord(record, limits)"
              icon
              variant="text"
              size="small"
              @click="openEdit(record)"
            >
              <VIcon icon="tabler-edit" />
              <VTooltip activator="parent">
                {{ $t('hr.attendance_records.edit') }}
              </VTooltip>
            </VBtn>
            <VBtn
              icon
              variant="text"
              size="small"
              @click="openHistory(record)"
            >
              <VIcon icon="tabler-history" />
              <VTooltip activator="parent">
                {{ $t('hr.attendance_records.history') }}
              </VTooltip>
            </VBtn>
          </div>
        </template>
      </HrAttendanceRecordsTable>
    </VCard>

    <HrManualAttendanceDialog
      v-model="formOpen"
      :record="editedRecord"
      :limits="limits"
      @saved="refresh"
    />
    <HrBulkAttendanceDialog
      v-model="bulkOpen"
      :limits="limits"
      @saved="refresh"
    />
    <HrAttendanceImportDialog
      v-model="importOpen"
      :limits="limits"
      @saved="refresh"
    />
    <HrAttendanceHistoryDialog
      v-model="historyOpen"
      :record-id="historyRecordId"
    />
  </div>
</template>

<route lang="yaml">
meta:
  action: access_hr-attendance-records
  subject: access_hr-attendance-records
  pageTitleKey: hr.attendance_records.title
  pageSubtitleKey: hr.attendance_records.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';

.hr-records-card {
  block-size: 100%;
  cursor: pointer;
}
</style>
