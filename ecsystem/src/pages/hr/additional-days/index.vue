<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrAdditionalDaysApi } from '@/plugins/apis/hrRequest'
import { APPROVAL_STATUS_COLORS } from '@/utils/hrFormat'
import { ADDITIONAL_DAY_STATUS_COLORS, formatAmount, formatDays } from '@/utils/hrLeave'
import HrAdditionalDayDetailsDialog from '@/views/hr/additional-days/HrAdditionalDayDetailsDialog.vue'
import HrAdditionalDayDialog from '@/views/hr/additional-days/HrAdditionalDayDialog.vue'
import { can } from '@layouts/plugins/casl'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

// Additional working days (days only, never overtime hours): summary per status, history with filters (employee,
// branch, work date, payroll period, status), adding / editing / submitting records and their approval and audit
// history. Approved days are paid when their payroll period is approved: (gross salary / 30) × days.

const { t } = useI18n()
const { lookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-additional-days', 'edit_hr-additional-days') || can('admin_hr-additional-days', 'admin_hr-additional-days'))

const filters = reactive({
  q: '',
  branchId: null,
  employeeId: null,
  from: null,
  to: null,
  periodId: null,
  status: null,
})

const periods = ref([])

hrAdditionalDaysApi.periods().then(({ data }) => { periods.value = data.data ?? [] }).catch(() => { periods.value = [] })

const items = ref([])
const total = ref(0)
const loading = ref(false)
const summary = ref(null)
const selectedIds = ref([])
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })

const filterParams = () => ({
  q: filters.q || undefined,
  branch_id: filters.branchId || undefined,
  employee_id: filters.employeeId || undefined,
  from: filters.from || undefined,
  to: filters.to || undefined,
  attendance_period_id: filters.periodId || undefined,
  status: filters.status || undefined,
})

const fetchItems = () => {
  loading.value = true

  const params = filterParams()

  hrAdditionalDaysApi.summary(params).then(({ data }) => { summary.value = data.data }).catch(() => { summary.value = null })

  return hrAdditionalDaysApi.list({ ...params, options: options.value }).then(response => {
    items.value = response.data.data
    total.value = response.data.total
    selectedIds.value = selectedIds.value.filter(id => items.value.some(item => item.id === id && item.can_submit))
  }).catch(() => {
    items.value = []
    total.value = 0
  }).finally(() => {
    loading.value = false
  })
}

watchServerTableFetch(fetchItems, {
  filters: () => [filters.q, filters.branchId, filters.employeeId, filters.from, filters.to, filters.periodId, filters.status],
  options,
})

const searchEmployees = query => hrAdditionalDaysApi.employees({ q: query.q || undefined, ids: query.ids, branch_id: filters.branchId || undefined })

const cards = computed(() => {
  const s = summary.value
  if (!s)
    return []

  const daysOf = (...keys) => keys.reduce((sum, key) => sum + Number(s[key]?.days ?? 0), 0)
  const recordsOf = (...keys) => keys.reduce((sum, key) => sum + Number(s[key]?.records ?? 0), 0)

  return [
    {
      key: 'pending',
      status: 'pending',
      icon: 'tabler-hourglass',
      color: 'warning',
      title: t('hr.additional_days.summary.pending'),
      value: formatDays(daysOf('draft', 'pending')),
      caption: t('hr.additional_days.summary.pending_caption', { drafts: recordsOf('draft'), pending: recordsOf('pending') }),
    },
    {
      key: 'approved',
      status: 'approved',
      icon: 'tabler-circle-check',
      color: 'info',
      title: t('hr.additional_days.summary.approved'),
      value: formatDays(daysOf('approved')),
      caption: t('hr.additional_days.summary.approved_caption', { records: recordsOf('approved'), carried: s.carried_forward?.records ?? 0 }),
    },
    {
      key: 'rejected',
      status: 'rejected',
      icon: 'tabler-circle-x',
      color: 'error',
      title: t('hr.additional_days.summary.rejected'),
      value: formatDays(daysOf('rejected')),
      caption: t('hr.additional_days.summary.records', { records: recordsOf('rejected') }),
    },
    {
      key: 'paid',
      status: 'paid',
      icon: 'tabler-cash',
      color: 'success',
      title: t('hr.additional_days.summary.paid'),
      value: formatDays(daysOf('paid')),
      caption: t('hr.additional_days.summary.paid_caption', { records: recordsOf('paid'), amount: formatAmount(s.paid?.amount ?? 0) }),
    },
  ]
})

const filterByCard = card => {
  filters.status = filters.status === card.status ? null : card.status
}

const headers = computed(() => [
  ...(canEdit.value ? [{ title: '', key: 'select', sortable: false, width: 48 }] : []),
  { title: t('hr.fields.employee'), key: 'employee', sortable: false },
  { title: t('hr.additional_days.work_date'), key: 'work_date' },
  { title: t('hr.additional_days.days'), key: 'days' },
  { title: t('hr.additional_days.payroll_period'), key: 'period', sortable: false },
  { title: t('hr.additional_days.amount'), key: 'amount', sortable: false },
  { title: t('hr.workflows.steps'), key: 'approval', sortable: false },
  { title: t('Status'), key: 'status' },
  { title: t('Actions'), key: 'actions', sortable: false },
])

const submittable = computed(() => items.value.filter(item => item.can_submit))
const allSelected = computed(() => submittable.value.length > 0 && submittable.value.every(item => selectedIds.value.includes(item.id)))

const toggleAll = value => {
  selectedIds.value = value ? submittable.value.map(item => item.id) : []
}

const toggle = (id, value) => {
  selectedIds.value = value ? [...new Set([...selectedIds.value, id])] : selectedIds.value.filter(selected => selected !== id)
}

const formOpen = ref(false)
const editing = ref(null)
const detailsOpen = ref(false)
const detailsId = ref(null)
const confirmOpen = ref(false)
const confirmIds = ref([])
const submitting = ref(false)

const openCreate = () => {
  editing.value = null
  formOpen.value = true
}

const openEdit = item => {
  editing.value = item
  formOpen.value = true
}

const openDetails = item => {
  detailsId.value = item.id
  detailsOpen.value = true
}

const askSubmit = ids => {
  confirmIds.value = ids
  confirmOpen.value = true
}

const submitRecords = () => {
  submitting.value = true

  const ids = confirmIds.value
  const request = ids.length === 1 ? hrAdditionalDaysApi.submit(ids[0]) : hrAdditionalDaysApi.submitMany(ids)

  request.then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    selectedIds.value = []
    confirmOpen.value = false
    fetchItems()
  }).catch(() => {}).finally(() => {
    submitting.value = false
  })
}

const amountOf = item => {
  if (item.amount !== null && item.amount !== undefined)
    return { value: formatAmount(item.amount), estimated: false }
  if (item.estimated)
    return { value: formatAmount(item.estimated.amount), estimated: true }

  return null
}
</script>

<template>
  <VCard class="hr-page">
    <VCardText>
      <div class="d-flex align-center flex-wrap gap-2 mb-4">
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          class="flex-grow-1"
        >
          {{ $t('hr.additional_days.formula_hint') }}
        </VAlert>
        <VBtn
          v-if="canEdit && selectedIds.length"
          color="warning"
          prepend-icon="tabler-send"
          @click="askSubmit(selectedIds)"
        >
          {{ $t('hr.additional_days.submit_selected', { count: selectedIds.length }) }}
        </VBtn>
        <VBtn
          v-if="canEdit"
          prepend-icon="tabler-plus"
          @click="openCreate"
        >
          {{ $t('hr.additional_days.add') }}
        </VBtn>
      </div>

      <VRow v-if="cards.length">
        <VCol
          v-for="card in cards"
          :key="card.key"
          cols="12"
          sm="6"
          md="3"
        >
          <VCard
            variant="tonal"
            :color="card.color"
            class="pa-3 cursor-pointer"
            :class="{ 'hr-additional-card--active': filters.status === card.status }"
            @click="filterByCard(card)"
          >
            <div class="d-flex align-center gap-2">
              <VIcon
                :icon="card.icon"
                size="20"
              />
              <span class="text-caption">{{ card.title }}</span>
            </div>
            <div class="text-h5 mt-1">
              {{ card.value }} <span class="text-body-2">{{ $t('hr.additional_days.days_unit') }}</span>
            </div>
            <div class="text-caption">
              {{ card.caption }}
            </div>
          </VCard>
        </VCol>
      </VRow>

      <VRow class="mt-2">
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
          cols="12"
          sm="6"
          md="3"
        >
          <AppSelect
            v-model="filters.status"
            :items="lookups.additional_day_statuses"
            :placeholder="$t('Status')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <AppDateTimePicker
            v-model="filters.from"
            :placeholder="$t('hr.additional_days.from')"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <AppDateTimePicker
            v-model="filters.to"
            :placeholder="$t('hr.additional_days.to')"
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="filters.periodId"
            :items="periods"
            :placeholder="$t('hr.additional_days.payroll_period')"
            clearable
            clear-icon="tabler-x"
          />
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
      @update:options="applyServerTableOptions(options, $event)"
    >
      <template #column.select>
        <VCheckbox
          :model-value="allSelected"
          :disabled="!submittable.length"
          density="compact"
          hide-details
          @update:model-value="toggleAll"
        />
      </template>
      <template #item.select="{ item }">
        <VCheckbox
          v-if="item.raw.can_submit"
          :model-value="selectedIds.includes(item.raw.id)"
          density="compact"
          hide-details
          @update:model-value="toggle(item.raw.id, $event)"
        />
      </template>
      <template #item.employee="{ item }">
        <div>{{ item.raw.employee?.name || '—' }}</div>
        <div class="text-caption hr-page__muted">
          {{ item.raw.employee?.employee_number }}<template v-if="item.raw.branch_name">
            · {{ item.raw.branch_name }}
          </template>
        </div>
      </template>
      <template #item.work_date="{ item }">
        <div dir="ltr">
          {{ item.raw.work_date }}
        </div>
        <div class="text-caption hr-page__muted">
          {{ item.raw.day_type_label }}
        </div>
      </template>
      <template #item.days="{ item }">
        {{ formatDays(item.raw.days) }}
      </template>
      <template #item.period="{ item }">
        <div>{{ item.raw.attendance_period?.name || '—' }}</div>
        <div
          v-if="item.raw.paid_period && item.raw.paid_period.id !== item.raw.attendance_period?.id"
          class="text-caption"
        >
          {{ $t('hr.additional_days.paid_in') }}: {{ item.raw.paid_period.name }}
        </div>
        <VChip
          v-if="item.raw.carried_forward"
          size="x-small"
          label
          color="warning"
          class="mt-1"
        >
          {{ $t('hr.additional_days.carried_forward') }}
        </VChip>
      </template>
      <template #item.amount="{ item }">
        <template v-if="['draft', 'pending', 'approved', 'paid'].includes(item.raw.status)">
          <span
            v-if="amountOf(item.raw)"
            :class="{ 'hr-page__muted': amountOf(item.raw).estimated }"
          >
            <template v-if="amountOf(item.raw).estimated">≈ </template>{{ amountOf(item.raw).value }}
          </span>
          <span
            v-else
            class="text-caption text-error"
          >{{ $t('hr.additional_days.no_salary') }}</span>
        </template>
        <span
          v-else
          class="hr-page__muted"
        >—</span>
      </template>
      <template #item.approval="{ item }">
        <div
          v-if="item.raw.approval"
          class="hr-steps py-2"
        >
          <VChip
            v-for="step in item.raw.approval.steps"
            :key="step.step_order"
            size="small"
            label
            :variant="step.step_order === item.raw.approval.current_step && item.raw.status === 'pending' ? 'elevated' : 'tonal'"
            :color="APPROVAL_STATUS_COLORS[step.status]"
          >
            {{ step.step_order }}. {{ step.approver_value_label || step.approver_type_label }}
          </VChip>
        </div>
        <span
          v-else
          class="hr-page__muted"
        >—</span>
      </template>
      <template #item.status="{ item }">
        <VChip
          size="small"
          label
          :color="ADDITIONAL_DAY_STATUS_COLORS[item.raw.status]"
        >
          {{ item.raw.status_label }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <VBtn
          icon
          variant="text"
          size="small"
          @click="openDetails(item.raw)"
        >
          <VIcon icon="tabler-eye" />
          <VTooltip activator="parent">
            {{ $t('hr.additional_days.details') }}
          </VTooltip>
        </VBtn>
        <VBtn
          v-if="item.raw.can_edit"
          icon
          variant="text"
          size="small"
          @click="openEdit(item.raw)"
        >
          <VIcon icon="tabler-edit" />
          <VTooltip activator="parent">
            {{ $t('hr.additional_days.edit') }}
          </VTooltip>
        </VBtn>
        <VBtn
          v-if="item.raw.can_submit"
          icon
          variant="text"
          size="small"
          color="warning"
          @click="askSubmit([item.raw.id])"
        >
          <VIcon icon="tabler-send" />
          <VTooltip activator="parent">
            {{ $t('hr.additional_days.submit') }}
          </VTooltip>
        </VBtn>
      </template>
      <template #no-data>
        <div class="pa-6 text-center hr-page__muted">
          {{ $t('hr.additional_days.no_records') }}
        </div>
      </template>
    </VDataTableServer>

    <HrAdditionalDayDialog
      v-model="formOpen"
      :record="editing"
      @saved="fetchItems"
    />
    <HrAdditionalDayDetailsDialog
      v-model="detailsOpen"
      :record-id="detailsId"
      @changed="fetchItems"
    />
    <HrConfirmDialog
      v-model="confirmOpen"
      action="restore"
      :message="$t('hr.additional_days.confirm_submit', { count: confirmIds.length })"
      :confirm-label="$t('hr.additional_days.submit')"
      @confirm="submitRecords"
    />
  </VCard>
</template>

<route lang="yaml">
meta:
  action: access_hr-additional-days
  subject: access_hr-additional-days
  actionsAny:
    - access_hr-additional-days
    - edit_hr-additional-days
    - admin_hr-additional-days
    - apply_hr-additional-days
  pageTitleKey: hr.additional_days.title
  pageSubtitleKey: hr.additional_days.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';

.hr-additional-card--active {
  outline: 2px solid currentcolor;
}
</style>
