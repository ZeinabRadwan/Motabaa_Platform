<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { useHrCrudTable } from '@/composables/useHrCrudTable'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrAttendancePoliciesApi } from '@/plugins/apis/hrRequest'
import HrAttendancePolicyDialog from '@/views/hr/attendance/HrAttendancePolicyDialog.vue'
import HrAttendancePolicyEmployeesDialog from '@/views/hr/attendance/HrAttendancePolicyEmployeesDialog.vue'
import HrAttendancePolicyVersionsDialog from '@/views/hr/attendance/HrAttendancePolicyVersionsDialog.vue'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

const emit = defineEmits(['changed'])

const GPS_COLORS = { required: 'success', flexible: 'warning', none: 'secondary' }

const { t } = useI18n()
const { lookups, refresh: refreshLookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-attendance-setup', 'edit_hr-attendance-setup'))
const canAdmin = computed(() => can('admin_hr-attendance-setup', 'admin_hr-attendance-setup'))

const statusFilter = ref(null)
const gpsFilter = ref(null)

const statusItems = computed(() => [
  { value: 1, title: t('Active') },
  { value: 0, title: t('Inactive') },
])

const {
  items, total, loading, search, showTrashed, options, updateOptions, fetchItems,
  confirm, askConfirm, runConfirmed,
} = useHrCrudTable(hrAttendancePoliciesApi, {
  filters: () => ({
    is_active: statusFilter.value ?? undefined,
    gps_requirement: gpsFilter.value || undefined,
  }),
  emptyForm: () => ({}),
  toForm: item => item,
  onSaved: () => refreshLookups().catch(() => {}),
})

const afterChange = () => {
  refreshLookups().catch(() => {})
  fetchItems()
  emit('changed')
}

// Create / edit
const dialogPolicy = ref(null)
const isDialogVisible = ref(false)

const openDialog = (policy = null) => {
  dialogPolicy.value = policy
  isDialogVisible.value = true
}

// History / employees
const historyPolicy = ref(null)
const employeesPolicy = ref(null)

// Activate / deactivate
const statusTarget = ref(null)

const changeStatus = () => {
  const policy = statusTarget.value

  statusTarget.value = null
  if (!policy)
    return
  const request = policy.is_active ? hrAttendancePoliciesApi.deactivate(policy.id) : hrAttendancePoliciesApi.activate(policy.id)

  request.then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    afterChange()
  }).catch(() => {})
}

const minutes = value => t('hr.attendance_setup.minutes', { minutes: value })

const checkInWindow = policy => t('hr.policies.window_summary', {
  before: minutes(policy.check_in_before_minutes),
  after: policy.check_in_after_minutes === null ? t('hr.policies.until_shift_end') : minutes(policy.check_in_after_minutes),
})

const checkOutWindow = policy => t('hr.policies.window_summary', {
  before: policy.check_out_before_minutes === null ? t('hr.policies.any_time') : minutes(policy.check_out_before_minutes),
  after: minutes(policy.check_out_after_minutes),
})

const headers = computed(() => [
  { title: t('hr.fields.code'), key: 'code' },
  { title: t('hr.fields.name'), key: 'name' },
  { title: t('hr.policies.windows'), key: 'windows', sortable: false },
  { title: t('hr.policies.grace_short'), key: 'late_grace_minutes' },
  { title: t('hr.policies.gps_requirement'), key: 'gps_requirement' },
  { title: t('hr.policies.manual_short'), key: 'allow_manual_attendance', sortable: false },
  { title: t('hr.shifts.assigned'), key: 'employees_count' },
  { title: t('Status'), key: 'is_active', sortable: false },
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
          <AppTextField
            v-model="search"
            :placeholder="$t('Search')"
            prepend-inner-icon="tabler-search"
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
            v-model="statusFilter"
            :items="statusItems"
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
          <AppSelect
            v-model="gpsFilter"
            :items="lookups.attendance_rule_options?.gps_requirements ?? []"
            :placeholder="$t('hr.policies.gps_requirement')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
          class="d-flex align-center justify-end gap-3"
        >
          <VSwitch
            v-if="canAdmin"
            v-model="showTrashed"
            :label="$t('hr.common.show_deleted')"
            hide-details
          />
          <VBtn
            v-if="canEdit"
            prepend-icon="tabler-plus"
            @click="openDialog"
          >
            {{ $t('hr.attendance_setup.add_policy') }}
          </VBtn>
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
      <template #item.code="{ item }">
        <span dir="ltr">{{ item.raw.code }}</span>
      </template>
      <template #item.name="{ item }">
        <div>{{ item.raw.name }}</div>
        <VChip
          v-if="item.raw.scheduled_version"
          size="x-small"
          color="warning"
          variant="tonal"
          class="mt-1"
          prepend-icon="tabler-calendar-time"
        >
          {{ $t('hr.shifts.change_on', { date: item.raw.scheduled_version.effective_from }) }}
        </VChip>
      </template>
      <template #item.windows="{ item }">
        <div class="text-body-2">
          <span class="hr-page__muted">{{ $t('hr.policies.check_in_short') }}:</span> {{ checkInWindow(item.raw) }}
        </div>
        <div class="text-body-2">
          <span class="hr-page__muted">{{ $t('hr.policies.check_out_short') }}:</span> {{ checkOutWindow(item.raw) }}
        </div>
      </template>
      <template #item.late_grace_minutes="{ item }">
        <div class="text-body-2">
          <span class="hr-page__muted">{{ $t('hr.policies.late_short') }}:</span> {{ minutes(item.raw.late_grace_minutes) }}
        </div>
        <div class="text-body-2">
          <span class="hr-page__muted">{{ $t('hr.policies.early_short') }}:</span> {{ minutes(item.raw.early_leave_grace_minutes) }}
        </div>
      </template>
      <template #item.gps_requirement="{ item }">
        <VChip
          size="small"
          label
          :color="GPS_COLORS[item.raw.gps_requirement] ?? 'secondary'"
          prepend-icon="tabler-map-pin"
        >
          {{ $t(`hr.policies.gps_short.${item.raw.gps_requirement}`) }}
          <VTooltip activator="parent">
            {{ item.raw.gps_requirement_label }}
          </VTooltip>
        </VChip>
      </template>
      <template #item.allow_manual_attendance="{ item }">
        <VIcon
          :icon="item.raw.allow_manual_attendance ? 'tabler-check' : 'tabler-x'"
          :color="item.raw.allow_manual_attendance ? 'success' : 'secondary'"
        />
      </template>
      <template #item.employees_count="{ item }">
        <VBtn
          variant="tonal"
          size="small"
          prepend-icon="tabler-users"
          :disabled="!!item.raw.deleted_at"
          @click="employeesPolicy = item.raw"
        >
          {{ item.raw.employees_count ?? 0 }}
        </VBtn>
      </template>
      <template #item.is_active="{ item }">
        <VChip
          size="small"
          label
          :color="item.raw.is_active ? 'success' : 'secondary'"
        >
          {{ $t(item.raw.is_active ? 'Active' : 'Inactive') }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <template v-if="item.raw.deleted_at">
            <VBtn
              v-if="canAdmin"
              icon
              variant="text"
              size="small"
              @click="askConfirm('restore', item.raw)"
            >
              <VIcon icon="tabler-arrow-back-up" />
            </VBtn>
          </template>
          <template v-else>
            <VBtn
              v-if="canEdit"
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
              @click="historyPolicy = item.raw"
            >
              <VIcon icon="tabler-history" />
              <VTooltip activator="parent">
                {{ $t('hr.policies.history') }}
              </VTooltip>
            </VBtn>
            <VBtn
              v-if="canAdmin"
              icon
              variant="text"
              size="small"
              :color="item.raw.is_active ? 'warning' : 'success'"
              @click="statusTarget = item.raw"
            >
              <VIcon :icon="item.raw.is_active ? 'tabler-player-pause' : 'tabler-player-play'" />
              <VTooltip activator="parent">
                {{ $t(item.raw.is_active ? 'hr.organization.deactivate' : 'hr.organization.activate') }}
              </VTooltip>
            </VBtn>
            <VBtn
              v-if="canAdmin"
              icon
              variant="text"
              size="small"
              color="error"
              @click="askConfirm('delete', item.raw)"
            >
              <VIcon icon="tabler-trash" />
            </VBtn>
          </template>
        </div>
      </template>
    </VDataTableServer>

    <HrAttendancePolicyDialog
      v-model="isDialogVisible"
      :policy="dialogPolicy"
      @saved="afterChange"
    />
    <HrAttendancePolicyVersionsDialog
      :model-value="!!historyPolicy"
      :policy="historyPolicy"
      @update:model-value="historyPolicy = null"
      @changed="afterChange"
    />
    <HrAttendancePolicyEmployeesDialog
      :model-value="!!employeesPolicy"
      :policy="employeesPolicy"
      @update:model-value="employeesPolicy = null"
    />

    <HrConfirmDialog
      v-model="confirm.visible"
      :action="confirm.action"
      @confirm="runConfirmed"
    />
    <HrConfirmDialog
      :model-value="!!statusTarget"
      :action="statusTarget?.is_active ? 'delete' : 'restore'"
      :message="$t(statusTarget?.is_active ? 'hr.policies.confirm_deactivate' : 'hr.policies.confirm_activate')"
      :confirm-label="$t(statusTarget?.is_active ? 'hr.organization.deactivate' : 'hr.organization.activate')"
      @update:model-value="statusTarget = null"
      @confirm="changeStatus"
    />
  </div>
</template>
