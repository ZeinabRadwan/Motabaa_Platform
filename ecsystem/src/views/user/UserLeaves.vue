<script setup>
import { watchServerTableFetch } from '@core/utils/tableFetch'
import i18n from '@/plugins/i18n/index.js'
import { employeeLeavesApi } from "@/plugins/apis/employeeLeavesRequest"
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import { can } from '@layouts/plugins/casl'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import {
  leaveTypeItems,
  leaveStatusItems,
} from '@core/utils/generalItems'
import { useRoute } from 'vue-router'

const props = defineProps({
  userId: {
    type: [Number, String],
    default: null,
  },
  initialStatus: {
    type: String,
    default: null,
  },
  currentOnly: {
    type: Boolean,
    default: false,
  },
})

const route = useRoute()
const leavesReqest = employeeLeavesApi()
const userListStore = useUserListStore()
const snackbarRef = ref(null)
const leaves = ref([])
const employees = ref([])
const totalLeaves = ref(0)
const isDialogVisible = ref(false)
const editingId = ref(null)
const selectedType = ref()
const selectedStatus = ref(props.initialStatus || route.query.status || null)
const selectedEmployee = ref(props.userId)
const currentOnly = ref(props.currentOnly || route.query.current == '1')
const dateFrom = ref()
const dateTo = ref()
const loading = ref(false)
const balance = ref(null)
const dialogBalance = ref(null)

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

const form = ref({
  user_id: props.userId,
  type: 'annual',
  date_from: '',
  date_to: '',
  notes: '',
})

const canManage = computed(() => can('edit_users', 'edit_users'))
const balanceUserId = computed(() => props.userId || selectedEmployee.value)

const translatedHeaders = () => {
  const headers = [
    ...(!props.userId ? [{ title: 'employee_attendance.employee', key: 'user_name', sortable: false }] : []),
    { title: 'employee_leaves.leave_type', key: 'type_label', sortable: false },
    { title: 'from', key: 'date_from', sortable: false },
    { title: 'to', key: 'date_to', sortable: false },
    { title: 'employee_leaves.days', key: 'days', sortable: false },
    { title: 'Status', key: 'status_label', sortable: false },
    { title: 'Notes', key: 'notes', sortable: false },
    { title: 'Actions', key: 'actions', sortable: false },
  ]
  return headers.map(header => ({ ...header, title: i18n.global.t(header.title) }))
}

const statusColor = status => {
  if (status === 'approved') return 'success'
  if (status === 'rejected') return 'error'
  return 'warning'
}

const fetchBalance = (userId, target = 'page') => {
  if (!userId) {
    if (target === 'page') balance.value = null
    else dialogBalance.value = null
    return
  }
  leavesReqest.balance({ user_id: userId }).then(response => {
    const data = response.data.data
    if (target === 'page') balance.value = data
    else dialogBalance.value = data
  }).catch(() => {
    if (target === 'page') balance.value = null
    else dialogBalance.value = null
  })
}

const fetchLeaves = () => {
  loading.value = true
  leavesReqest.fetchAll({
    user_id: props.userId || selectedEmployee.value,
    type: selectedType.value,
    status: currentOnly.value ? 'approved' : selectedStatus.value,
    current: currentOnly.value ? '1' : undefined,
    date_from: dateFrom.value,
    date_to: dateTo.value,
    options: options.value,
    page: options.value.page,
  }).then(response => {
    leaves.value = response.data.data
    totalLeaves.value = response.data.total
    options.value.page = response.data.currentPage
    if (response.data.balance) {
      balance.value = response.data.balance
    } else if (balanceUserId.value) {
      fetchBalance(balanceUserId.value)
    } else {
      balance.value = null
    }
    loading.value = false
  }).catch(() => {
    loading.value = false
  })
}

const employeeSearch = ref('')

const searchEmployees = params => userListStore.searchItems({
  ...params,
  excludeParent: true,
  status: 'active',
})

const openDialog = (item = null) => {
  if (item) {
    editingId.value = item.id
    form.value = {
      user_id: item.user_id,
      type: item.type,
      date_from: item.date_from,
      date_to: item.date_to,
      notes: item.notes ?? '',
    }
    fetchBalance(item.user_id, 'dialog')
  } else {
    editingId.value = null
    form.value = {
      user_id: props.userId,
      type: 'annual',
      date_from: '',
      date_to: '',
      notes: '',
    }
    fetchBalance(props.userId || selectedEmployee.value, 'dialog')
  }
  isDialogVisible.value = true
}

const saveLeave = () => {
  leavesReqest.put({
    id: editingId.value,
    ...form.value,
  }).then(() => {
    isDialogVisible.value = false
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success')
    fetchLeaves()
  }).catch(error => {
    const message = error.response?.data?.errors?.error?.[0] || i18n.global.t('validtion.missing_field')
    snackbarRef.value.exposevisibleSnackbar(message, 'error')
  })
}

const reviewLeave = (id, status) => {
  leavesReqest.review(id, status).then(() => {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success')
    fetchLeaves()
  }).catch(error => {
    const message = error.response?.data?.errors?.error?.[0] || i18n.global.t('validtion.missing_field')
    snackbarRef.value.exposevisibleSnackbar(message, 'error')
  })
}

const deleteLeave = id => {
  leavesReqest.delete(id).then(() => {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('Record deleted successfully'), 'success')
    fetchLeaves()
  })
}


watch(() => form.value.user_id, userId => {
  if (isDialogVisible.value) fetchBalance(userId, 'dialog')
})

watchServerTableFetch(fetchLeaves, {
  filters: () => [
    props.userId,
    selectedEmployee.value,
    selectedType.value,
    selectedStatus.value,
    currentOnly.value,
    dateFrom.value,
    dateTo.value,
  ],
  options,
})

onMounted(() => {
  if (balanceUserId.value) fetchBalance(balanceUserId.value)
})
</script>

<template>
  <VCard class="mb-4" v-if="balance">
    <VCardText>
      <div class="text-subtitle-1 mb-3">
        {{ $t('employee_leaves.balance') }} ({{ balance.year }})
      </div>
      <VRow>
        <VCol cols="12" sm="3">
          <div class="text-sm text-medium-emphasis">{{ $t('employee_leaves.entitlement') }}</div>
          <div class="text-h5">{{ balance.entitlement }}</div>
        </VCol>
        <VCol cols="12" sm="3">
          <div class="text-sm text-medium-emphasis">{{ $t('employee_leaves.used') }}</div>
          <div class="text-h5 text-error">{{ balance.used }}</div>
        </VCol>
        <VCol cols="12" sm="3">
          <div class="text-sm text-medium-emphasis">{{ $t('employee_leaves.pending_days') }}</div>
          <div class="text-h5 text-warning">{{ balance.pending }}</div>
        </VCol>
        <VCol cols="12" sm="3">
          <div class="text-sm text-medium-emphasis">{{ $t('employee_leaves.remaining') }}</div>
          <div class="text-h5 text-success">{{ balance.remaining }}</div>
        </VCol>
      </VRow>
    </VCardText>
  </VCard>

  <VCard>
    <VCardText>
      <VRow>
        <VCol
          v-if="!userId"
          cols="12"
          sm="4"
        >
          <AppAutocomplete
            v-model="selectedEmployee"
            :server-search="searchEmployees"
            item-title="name"
            item-value="id"
            :label="$t('employee_attendance.employee')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol cols="12" sm="4">
          <AppSelect
            v-model="selectedType"
            :items="leaveTypeItems()"
            :label="$t('employee_leaves.leave_type')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol cols="12" sm="4">
          <AppSelect
            v-model="selectedStatus"
            :items="leaveStatusItems()"
            :label="$t('Status')"
            :disabled="currentOnly"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol cols="12" sm="4">
          <AppDateTimePicker
            v-model="dateFrom"
            :placeholder="$t('from')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol cols="12" sm="4">
          <AppDateTimePicker
            v-model="dateTo"
            :placeholder="$t('to')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol cols="12" sm="4" class="d-flex align-end">
          <VCheckbox
            v-model="currentOnly"
            :label="$t('employee_leaves.currently_on_leave')"
          />
        </VCol>
        <VCol
          v-if="canManage"
          cols="12"
          sm="4"
          class="d-flex align-end"
        >
          <VBtn @click="openDialog()">
            {{ $t('employee_leaves.add_leave') }}
          </VBtn>
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VDataTableServer
      v-model:items-per-page="options.itemsPerPage"
      v-model:page="options.page"
      :items="leaves"
      :items-length="totalLeaves"
      :headers="translatedHeaders()"
      :loading="loading"
      class="text-no-wrap"
      @update:options="options = $event"
    >
      <template #item.status_label="{ item }">
        <VChip
          size="small"
          label
          :color="statusColor(item.raw.status)"
        >
          {{ item.raw.status_label }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <div v-if="canManage" class="d-flex gap-1">
          <VBtn
            v-if="item.raw.status === 'pending'"
            color="success"
            size="small"
            variant="tonal"
            @click="reviewLeave(item.raw.id, 'approved')"
          >
            {{ $t('employee_leaves.approve') }}
          </VBtn>
          <VBtn
            v-if="item.raw.status === 'pending'"
            color="error"
            size="small"
            variant="tonal"
            @click="reviewLeave(item.raw.id, 'rejected')"
          >
            {{ $t('employee_leaves.reject') }}
          </VBtn>
          <VBtn
            icon
            variant="text"
            size="small"
            @click="openDialog(item.raw)"
          >
            <VIcon icon="tabler-edit" />
          </VBtn>
          <VBtn
            icon
            variant="text"
            size="small"
            color="error"
            @click="deleteLeave(item.raw.id)"
          >
            <VIcon icon="tabler-trash" />
          </VBtn>
        </div>
      </template>
    </VDataTableServer>
  </VCard>

  <VDialog
    v-model="isDialogVisible"
    max-width="640"
  >
    <VCard :title="editingId ? $t('employee_leaves.edit_leave') : $t('employee_leaves.add_leave')">
      <VCardText>
        <VAlert
          v-if="dialogBalance"
          class="mb-4"
          variant="tonal"
          color="info"
        >
          {{ $t('employee_leaves.entitlement') }}: {{ dialogBalance.entitlement }}
          · {{ $t('employee_leaves.used') }}: {{ dialogBalance.used }}
          · {{ $t('employee_leaves.remaining') }}: {{ dialogBalance.remaining }}
        </VAlert>
        <VRow>
          <VCol
            v-if="!userId"
            cols="12"
          >
            <AppAutocomplete
              v-model="form.user_id"
              :server-search="searchEmployees"
              item-title="name"
              item-value="id"
              :label="$t('employee_attendance.employee')"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppSelect
              v-model="form.type"
              :items="leaveTypeItems()"
              :label="$t('employee_leaves.leave_type')"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppDateTimePicker
              v-model="form.date_from"
              :placeholder="$t('from')"
            />
          </VCol>
          <VCol cols="12" sm="6">
            <AppDateTimePicker
              v-model="form.date_to"
              :placeholder="$t('to')"
            />
          </VCol>
          <VCol cols="12">
            <AppTextField
              v-model="form.notes"
              :label="$t('Notes')"
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
        <VBtn @click="saveLeave">
          {{ $t('Save') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
  <SnackbarComponent ref="snackbarRef" />
</template>
