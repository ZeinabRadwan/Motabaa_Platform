<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrLeaveRequestsApi, hrMyLeaveApi } from '@/plugins/apis/hrRequest'
import { APPROVAL_STATUS_COLORS } from '@/utils/hrFormat'
import { LEAVE_STATUS_COLORS, formatDays } from '@/utils/hrLeave'
import HrLeaveRequestDetailsDialog from '@/views/hr/leave/HrLeaveRequestDetailsDialog.vue'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

// Leave history: every request with its status and approval steps. HR mode lists the requests of the user's
// branches (filters by branch / employee); self mode lists the signed-in employee's own requests.

const props = defineProps({
  mode: { type: String, default: 'hr' },
  leaveTypes: { type: Array, default: () => [] },
  canCancel: { type: Boolean, default: false },
  canCancelApproved: { type: Boolean, default: false },
})

const emit = defineEmits(['changed'])

const { t } = useI18n()
const { lookups } = useHrLookups()
const isHr = computed(() => props.mode === 'hr')

const filters = reactive({
  status: null,
  leaveTypeId: null,
  year: new Date().getFullYear(),
  branchId: null,
  employeeId: null,
  q: '',
})

const yearItems = computed(() => {
  const current = new Date().getFullYear()

  return [current + 1, current, current - 1, current - 2].map(value => ({ value, title: String(value) }))
})

const typeItems = computed(() => props.leaveTypes.map(type => ({ value: type.value ?? type.id, title: type.title ?? type.name })))

const items = ref([])
const total = ref(0)
const loading = ref(false)
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })

const fetchItems = () => {
  loading.value = true

  const params = {
    status: filters.status || undefined,
    leave_type_id: filters.leaveTypeId || undefined,
    year: filters.year || undefined,
    branch_id: isHr.value ? (filters.branchId || undefined) : undefined,
    employee_id: isHr.value ? (filters.employeeId || undefined) : undefined,
    q: isHr.value ? (filters.q || undefined) : undefined,
    options: options.value,
  }

  return (isHr.value ? hrLeaveRequestsApi.list(params) : hrMyLeaveApi.requests(params)).then(response => {
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
  filters: () => [filters.status, filters.leaveTypeId, filters.year, filters.branchId, filters.employeeId, filters.q],
  options,
})

const searchEmployees = query => hrLeaveRequestsApi.employees({ q: query.q || undefined, ids: query.ids, branch_id: filters.branchId || undefined })

const headers = computed(() => [
  ...(isHr.value ? [{ title: t('hr.fields.employee'), key: 'employee', sortable: false }] : []),
  { title: t('hr.leave.leave_type'), key: 'leave_type', sortable: false },
  { title: t('hr.leave.period'), key: 'start_date' },
  { title: t('hr.leave.days'), key: 'days' },
  { title: t('hr.workflows.steps'), key: 'approval', sortable: false },
  { title: t('Status'), key: 'status' },
  { title: t('Actions'), key: 'actions', sortable: false },
])

const selected = ref(null)
const detailsOpen = ref(false)

const openDetails = item => {
  selected.value = item
  detailsOpen.value = true
}

const onChanged = () => {
  fetchItems()
  emit('changed')
}

defineExpose({ refresh: fetchItems })
</script>

<template>
  <div>
    <VCardText>
      <VRow>
        <VCol
          v-if="isHr"
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
          v-if="isHr"
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
          v-if="isHr"
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
            v-model="filters.leaveTypeId"
            :items="typeItems"
            :placeholder="$t('hr.leave.leave_type')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="6"
          sm="3"
          md="2"
        >
          <AppSelect
            v-model="filters.status"
            :items="lookups.leave_statuses"
            :placeholder="$t('Status')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="6"
          sm="3"
          md="2"
        >
          <AppSelect
            v-model="filters.year"
            :items="yearItems"
            :placeholder="$t('hr.leave.year')"
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
      <template #item.employee="{ item }">
        <div>{{ item.raw.employee?.name || '—' }}</div>
        <div class="text-caption hr-page__muted">
          {{ item.raw.employee?.employee_number }}
        </div>
      </template>
      <template #item.leave_type="{ item }">
        <VChip
          size="small"
          label
          :color="item.raw.leave_type?.color || 'primary'"
        >
          {{ item.raw.leave_type?.name || '—' }}
        </VChip>
        <VIcon
          v-if="item.raw.attachments?.length"
          icon="tabler-paperclip"
          size="16"
          class="ms-1"
        />
      </template>
      <template #item.start_date="{ item }">
        <span dir="ltr">{{ item.raw.start_date }}<template v-if="item.raw.end_date !== item.raw.start_date"> → {{ item.raw.end_date }}</template></span>
      </template>
      <template #item.days="{ item }">
        {{ formatDays(item.raw.days) }}
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
        >{{ item.raw.source_label }}</span>
      </template>
      <template #item.status="{ item }">
        <VChip
          size="small"
          label
          :color="LEAVE_STATUS_COLORS[item.raw.status]"
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
            {{ $t('hr.leave.request_details') }}
          </VTooltip>
        </VBtn>
      </template>
      <template #no-data>
        <div class="pa-6 text-center hr-page__muted">
          {{ $t('hr.leave.no_requests') }}
        </div>
      </template>
    </VDataTableServer>

    <HrLeaveRequestDetailsDialog
      v-model="detailsOpen"
      :leave="selected"
      :mode="mode"
      :can-cancel="canCancel"
      :can-cancel-approved="canCancelApproved"
      @changed="onChanged"
    />
  </div>
</template>
