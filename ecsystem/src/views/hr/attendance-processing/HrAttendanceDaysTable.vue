<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrAttendanceDaysApi } from '@/plugins/apis/hrRequest'
import { ATTENDANCE_DAY_STATUS_COLORS, formatDays } from '@/utils/hrLeave'
import { formatMinutes, startOfMonth, todayIso } from '@/utils/hrShifts'
import HrAttendanceDayDialog from '@/views/hr/attendance-processing/HrAttendanceDayDialog.vue'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

// Processed attendance per employee and day. "daily" lists every result with totals for the filters;
// "exceptions" lists the days needing review (missing check-in / check-out, attendance during leave, ...).
// Processing runs hourly for recent days; HR can reprocess a range on demand (edit_hr-attendance-processing).

const props = defineProps({
  mode: { type: String, default: 'daily' },
  canEdit: { type: Boolean, default: false },
})

const emit = defineEmits(['changed'])

const { t } = useI18n()
const { lookups } = useHrLookups()
const isExceptions = computed(() => props.mode === 'exceptions')

const filters = reactive({
  from: startOfMonth(todayIso()),
  to: todayIso(),
  branchId: null,
  employeeId: null,
  status: null,
  reviewStatus: 'pending',
  hideNonWorking: true,
  lateOnly: false,
  q: '',
})

const statusItems = computed(() => [
  { value: 'present_any', title: t('hr.attendance_processing.present_any') },
  ...(lookups.value.attendance_day_statuses ?? []),
])

const reviewItems = computed(() => ['pending', 'resolved'].map(value => ({ value, title: t(`hr.attendance_processing.review.${value}`) })))

const listParams = () => ({
  from: filters.from || undefined,
  to: filters.to || undefined,
  branch_id: filters.branchId || undefined,
  employee_id: filters.employeeId || undefined,
  status: isExceptions.value ? 'exceptions' : (filters.status || undefined),
  review_status: isExceptions.value ? (filters.reviewStatus || undefined) : undefined,
  hide_non_working: !isExceptions.value && filters.hideNonWorking ? 1 : undefined,
  late_only: !isExceptions.value && filters.lateOnly ? 1 : undefined,
  q: filters.q || undefined,
})

const items = ref([])
const total = ref(0)
const loading = ref(false)
const summary = ref(null)
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })

const fetchItems = () => {
  loading.value = true

  const params = listParams()

  if (!isExceptions.value)
    hrAttendanceDaysApi.summary(params).then(response => { summary.value = response.data.data }).catch(() => { summary.value = null })

  return hrAttendanceDaysApi.list({ ...params, options: options.value }).then(response => {
    items.value = response.data.data
    total.value = response.data.total
  }).catch(() => {
    items.value = []
    total.value = 0
  }).finally(() => {
    loading.value = false
  })
}

watchServerTableFetch(fetchItems, {
  filters: () => [filters.from, filters.to, filters.branchId, filters.employeeId, filters.status, filters.reviewStatus, filters.hideNonWorking, filters.lateOnly, filters.q],
  options,
})

const updateOptions = incoming => applyServerTableOptions(options, incoming)

const searchEmployees = query => hrAttendanceDaysApi.employees({ q: query.q || undefined, ids: query.ids, branch_id: filters.branchId || undefined })

const presentCount = computed(() => {
  const statuses = summary.value?.statuses ?? {}

  return ['present', 'late', 'early_departure', 'late_early_departure'].reduce((sum, key) => sum + (statuses[key] ?? 0), 0)
})

const cards = computed(() => (summary.value
  ? [
    { key: 'present', icon: 'tabler-user-check', color: 'success', title: t('hr.attendance_processing.present_days'), value: presentCount.value },
    { key: 'absence', icon: 'tabler-user-x', color: 'error', title: t('hr.attendance_processing.absence_days'), value: formatDays(summary.value.absence_days) },
    { key: 'late', icon: 'tabler-clock-exclamation', color: 'warning', title: t('hr.attendance_processing.late_minutes'), value: formatMinutes(summary.value.late_minutes, t) },
    { key: 'early', icon: 'tabler-door-exit', color: 'warning', title: t('hr.attendance_processing.early_minutes'), value: formatMinutes(summary.value.early_departure_minutes, t) },
    { key: 'leave', icon: 'tabler-beach', color: 'info', title: t('hr.attendance_processing.leave_days'), value: summary.value.statuses?.on_leave ?? 0 },
    { key: 'exceptions', icon: 'tabler-alert-triangle', color: 'error', title: t('hr.attendance_processing.unresolved'), value: summary.value.unresolved_exceptions },
  ]
  : []))

const headers = computed(() => [
  { title: t('hr.attendance_processing.date'), key: 'attendance_date' },
  { title: t('hr.fields.employee'), key: 'employee', sortable: false },
  { title: t('hr.fields.shift'), key: 'shift', sortable: false },
  { title: t('hr.attendance_processing.in_out'), key: 'in_out', sortable: false },
  { title: t('hr.attendance_processing.worked'), key: 'worked_minutes', sortable: false },
  { title: t('hr.attendance_processing.late'), key: 'late_minutes' },
  { title: t('hr.attendance_processing.early'), key: 'early_departure_minutes', sortable: false },
  { title: t('Status'), key: 'status' },
  ...(isExceptions.value ? [{ title: t('hr.attendance_processing.exceptions'), key: 'exceptions', sortable: false }] : []),
  { title: t('Actions'), key: 'actions', sortable: false },
])

const minutes = value => (value ? formatMinutes(value, t) : '—')

const selectedId = ref(null)
const detailsOpen = ref(false)

const openDay = item => {
  selectedId.value = item.id
  detailsOpen.value = true
}

const onChanged = () => {
  fetchItems()
  emit('changed')
}

// Reprocess on demand (max 62 days per run).
const processOpen = ref(false)
const processing = ref(false)
const processForm = ref({ from: null, to: null, branch_id: null, employee_id: null })

const searchProcessEmployees = query => hrAttendanceDaysApi.employees({ q: query.q || undefined, ids: query.ids, branch_id: processForm.value.branch_id || undefined })

const openProcess = () => {
  processForm.value = { from: filters.from || todayIso(), to: filters.to || todayIso(), branch_id: filters.branchId, employee_id: filters.employeeId }
  processOpen.value = true
}

const runProcess = () => {
  processing.value = true
  hrAttendanceDaysApi.process({
    from: processForm.value.from,
    to: processForm.value.to,
    branch_id: processForm.value.branch_id || undefined,
    employee_id: processForm.value.employee_id || undefined,
  }).then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    processOpen.value = false
    onChanged()
  }).catch(() => {}).finally(() => {
    processing.value = false
  })
}

defineExpose({ refresh: fetchItems })
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
          <AppTextField
            v-model="filters.q"
            :placeholder="$t('Search')"
            prepend-inner-icon="tabler-search"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="6"
          md="2"
        >
          <AppDateTimePicker
            v-model="filters.from"
            :placeholder="$t('from')"
          />
        </VCol>
        <VCol
          cols="6"
          md="2"
        >
          <AppDateTimePicker
            v-model="filters.to"
            :placeholder="$t('to')"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="2"
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
          v-if="!isExceptions"
          cols="12"
          sm="6"
          md="3"
        >
          <AppSelect
            v-model="filters.status"
            :items="statusItems"
            :placeholder="$t('Status')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          v-else
          cols="12"
          sm="6"
          md="3"
        >
          <AppSelect
            v-model="filters.reviewStatus"
            :items="reviewItems"
            :placeholder="$t('hr.attendance_processing.review_status')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          v-if="!isExceptions"
          cols="12"
          sm="6"
          md="5"
          class="d-flex align-center gap-4 flex-wrap"
        >
          <VSwitch
            v-model="filters.hideNonWorking"
            :label="$t('hr.attendance_processing.hide_non_working')"
            hide-details
          />
          <VSwitch
            v-model="filters.lateOnly"
            :label="$t('hr.attendance_processing.late_only')"
            hide-details
          />
        </VCol>
        <VCol
          v-if="canEdit"
          cols="12"
          :md="isExceptions ? 9 : 4"
          class="d-flex align-center justify-end"
        >
          <VBtn
            prepend-icon="tabler-refresh"
            @click="openProcess"
          >
            {{ $t('hr.attendance_processing.process') }}
          </VBtn>
        </VCol>
      </VRow>

      <VRow
        v-if="!isExceptions && cards.length"
        class="mt-2"
      >
        <VCol
          v-for="card in cards"
          :key="card.key"
          cols="6"
          sm="4"
          md="2"
        >
          <VCard
            variant="tonal"
            :color="card.color"
            class="pa-3"
          >
            <div class="d-flex align-center gap-2">
              <VIcon
                :icon="card.icon"
                size="20"
              />
              <span class="text-caption">{{ card.title }}</span>
            </div>
            <div class="text-h6 mt-1">
              {{ card.value }}
            </div>
          </VCard>
        </VCol>
      </VRow>
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
      <template #item.attendance_date="{ item }">
        <span dir="ltr">{{ item.raw.attendance_date }}</span>
        <VIcon
          v-if="item.raw.is_locked"
          icon="tabler-lock"
          size="14"
          class="ms-1"
        />
      </template>
      <template #item.employee="{ item }">
        <div>{{ item.raw.employee?.name }}</div>
        <div
          class="text-caption hr-page__muted"
          dir="ltr"
        >
          {{ item.raw.employee?.employee_number }}
        </div>
      </template>
      <template #item.shift="{ item }">
        <template v-if="item.raw.shift">
          <div>{{ item.raw.shift.name }}</div>
          <div
            class="text-caption hr-page__muted"
            dir="ltr"
          >
            {{ item.raw.scheduled_start }}–{{ item.raw.scheduled_end }}
          </div>
        </template>
        <span v-else>—</span>
      </template>
      <template #item.in_out="{ item }">
        <span dir="ltr">{{ item.raw.check_in || '—' }} / {{ item.raw.check_out || '—' }}</span>
      </template>
      <template #item.worked_minutes="{ item }">
        {{ minutes(item.raw.worked_minutes) }}
      </template>
      <template #item.late_minutes="{ item }">
        <span :class="{ 'text-warning': item.raw.late_minutes > 0 }">{{ minutes(item.raw.late_minutes) }}</span>
      </template>
      <template #item.early_departure_minutes="{ item }">
        <span :class="{ 'text-warning': item.raw.early_departure_minutes > 0 }">{{ minutes(item.raw.early_departure_minutes) }}</span>
      </template>
      <template #item.status="{ item }">
        <div class="d-flex align-center gap-1">
          <VChip
            size="small"
            label
            :color="ATTENDANCE_DAY_STATUS_COLORS[item.raw.status] || 'secondary'"
          >
            {{ item.raw.status_label }}
          </VChip>
          <VIcon
            v-if="item.raw.override"
            icon="tabler-edit-circle"
            size="16"
            color="info"
          >
            <VTooltip activator="parent">
              {{ $t('hr.attendance_processing.corrected') }}
            </VTooltip>
          </VIcon>
          <VIcon
            v-if="item.raw.review_status === 'pending'"
            icon="tabler-alert-triangle"
            size="16"
            color="error"
          >
            <VTooltip activator="parent">
              {{ item.raw.exception_reasons.map(reason => reason.label).join(' · ') }}
            </VTooltip>
          </VIcon>
          <VIcon
            v-else-if="item.raw.review_flags?.length"
            icon="tabler-flag"
            size="16"
            color="warning"
          >
            <VTooltip activator="parent">
              {{ item.raw.review_flags.map(flag => flag.label).join(' · ') }}
            </VTooltip>
          </VIcon>
        </div>
        <div
          v-if="item.raw.leave || item.raw.holiday"
          class="text-caption hr-page__muted"
        >
          {{ item.raw.leave?.leave_type || item.raw.holiday?.name }}
        </div>
      </template>
      <template #item.exceptions="{ item }">
        <div class="d-flex flex-wrap gap-1">
          <VChip
            v-for="reason in item.raw.exception_reasons"
            :key="reason.key"
            size="x-small"
            label
            color="error"
          >
            {{ reason.label }}
          </VChip>
        </div>
      </template>
      <template #item.actions="{ item }">
        <VBtn
          icon
          variant="text"
          size="small"
          @click="openDay(item.raw)"
        >
          <VIcon :icon="canEdit && !item.raw.is_locked ? 'tabler-edit' : 'tabler-eye'" />
        </VBtn>
      </template>
    </VDataTableServer>

    <HrAttendanceDayDialog
      v-model="detailsOpen"
      :day-id="selectedId"
      :can-edit="canEdit"
      @changed="onChanged"
    />

    <VDialog
      v-model="processOpen"
      max-width="560"
    >
      <VCard :title="$t('hr.attendance_processing.process')">
        <VCardText>
          <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ $t('hr.attendance_processing.process_hint') }}
          </VAlert>
          <VRow>
            <VCol cols="6">
              <AppDateTimePicker
                v-model="processForm.from"
                :label="$t('from')"
              />
            </VCol>
            <VCol cols="6">
              <AppDateTimePicker
                v-model="processForm.to"
                :label="$t('to')"
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="processForm.branch_id"
                :items="lookups.branches"
                :label="$t('hr.fields.branch')"
                :placeholder="$t('hr.attendance_processing.all_branches')"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>
            <VCol cols="12">
              <AppAutocomplete
                v-model="processForm.employee_id"
                :server-search="searchProcessEmployees"
                :preload-key="processForm.branch_id"
                item-title="title"
                item-value="id"
                :label="$t('hr.fields.employee')"
                :placeholder="$t('hr.attendance_processing.all_employees')"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="processOpen = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            :disabled="!processForm.from || !processForm.to"
            :loading="processing"
            @click="runProcess"
          >
            {{ $t('hr.attendance_processing.process') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
