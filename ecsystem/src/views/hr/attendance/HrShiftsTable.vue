<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { useHrCrudTable } from '@/composables/useHrCrudTable'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrShiftsApi } from '@/plugins/apis/hrRequest'
import { formatMinutes, shiftColor } from '@/utils/hrShifts'
import HrShiftAssignDialog from '@/views/hr/attendance/HrShiftAssignDialog.vue'
import HrShiftDialog from '@/views/hr/attendance/HrShiftDialog.vue'
import HrShiftVersionsDialog from '@/views/hr/attendance/HrShiftVersionsDialog.vue'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

const emit = defineEmits(['changed'])

const { t } = useI18n()
const { lookups, refresh: refreshLookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-attendance-setup', 'edit_hr-attendance-setup'))
const canAdmin = computed(() => can('admin_hr-attendance-setup', 'admin_hr-attendance-setup'))
const canAssign = computed(() => canEdit.value && can('edit_hr-employees', 'edit_hr-employees'))

const typeFilter = ref(null)
const statusFilter = ref(null)
const branchFilter = ref(null)

const statusItems = computed(() => [
  { value: 1, title: t('Active') },
  { value: 0, title: t('Inactive') },
])

const {
  items, total, loading, search, showTrashed, options, updateOptions, fetchItems,
  confirm, askConfirm, runConfirmed,
} = useHrCrudTable(hrShiftsApi, {
  filters: () => ({
    shift_type: typeFilter.value || undefined,
    is_active: statusFilter.value ?? undefined,
    branch_id: branchFilter.value || undefined,
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
const dialogShift = ref(null)
const isDialogVisible = ref(false)

const openDialog = (shift = null) => {
  dialogShift.value = shift
  isDialogVisible.value = true
}

// Assign / history
const assignShift = ref(null)
const historyShift = ref(null)

// Activate / deactivate
const statusTarget = ref(null)

const changeStatus = () => {
  const shift = statusTarget.value

  statusTarget.value = null
  if (!shift)
    return
  const request = shift.is_active ? hrShiftsApi.deactivate(shift.id) : hrShiftsApi.activate(shift.id)

  request.then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    afterChange()
  }).catch(() => {})
}

const days = list => (list ?? []).map(day => t(`hr.weekdays_short.${day}`)).join(' · ')

const headers = computed(() => [
  { title: t('hr.fields.code'), key: 'code' },
  { title: t('hr.fields.name'), key: 'name' },
  { title: t('hr.shifts.type'), key: 'shift_type' },
  { title: t('hr.attendance_setup.time_range'), key: 'time_range', sortable: false },
  { title: t('hr.shifts.expected_hours'), key: 'effective_expected_minutes', sortable: false },
  { title: t('hr.shifts.days'), key: 'working_days', sortable: false },
  { title: t('hr.fields.branch'), key: 'branch_name', sortable: false },
  { title: t('hr.shifts.assigned'), key: 'employees_count', sortable: false },
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
            v-model="typeFilter"
            :items="lookups.shift_types"
            :placeholder="$t('hr.shifts.type')"
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
          md="2"
        >
          <AppSelect
            v-model="branchFilter"
            :items="lookups.branches"
            :placeholder="$t('hr.fields.branch')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          md="3"
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
            {{ $t('hr.attendance_setup.add_shift') }}
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
        <div class="d-flex align-center gap-2">
          <span
            class="hr-shift-swatch hr-shift-swatch--inline"
            :style="{ backgroundColor: shiftColor(item.raw.color, item.raw.shift_type) }"
          />
          <span dir="ltr">{{ item.raw.code }}</span>
        </div>
      </template>
      <template #item.shift_type="{ item }">
        <VChip
          size="small"
          label
          :color="shiftColor(item.raw.color, item.raw.shift_type)"
        >
          {{ item.raw.shift_type_label }}
        </VChip>
      </template>
      <template #item.time_range="{ item }">
        <div v-if="item.raw.is_rotating">
          <div class="d-flex gap-1">
            <span
              v-for="slot in item.raw.rotation_slots"
              :key="slot.day"
              class="hr-rotation-dot"
              :class="{ 'hr-rotation-dot--rest': slot.is_rest }"
              :style="slot.is_rest ? {} : { backgroundColor: shiftColor(slot.color, null) }"
            >
              <VTooltip activator="parent">
                {{ $t('hr.shifts.cycle_day', { day: slot.day }) }}: {{ slot.is_rest ? $t('hr.shifts.rest_day') : `${slot.name} (${slot.time_range})` }}
              </VTooltip>
            </span>
          </div>
          <div class="hr-page__muted">
            {{ item.raw.time_range }}
          </div>
        </div>
        <div v-else>
          <span dir="ltr">{{ item.raw.time_range }}</span>
          <VChip
            v-if="item.raw.is_overnight"
            size="x-small"
            label
            color="info"
            class="ms-1"
          >
            +1
            <VTooltip activator="parent">
              {{ $t('hr.attendance_setup.overnight_hint') }}
            </VTooltip>
          </VChip>
          <div
            v-if="item.raw.break_minutes"
            class="hr-page__muted"
          >
            {{ $t('hr.shifts.break_short', { value: formatMinutes(item.raw.break_minutes, t) }) }}<span v-if="item.raw.break_start_time"> · <span dir="ltr">{{ item.raw.break_start_time }}</span></span>
          </div>
        </div>
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
      <template #item.effective_expected_minutes="{ item }">
        {{ item.raw.is_rotating ? '—' : formatMinutes(item.raw.effective_expected_minutes, t) }}
      </template>
      <template #item.working_days="{ item }">
        <template v-if="!item.raw.is_rotating">
          <div>{{ days(item.raw.working_days) }}</div>
          <div class="hr-page__muted">
            {{ $t('hr.shifts.rest_short', { days: days(item.raw.rest_days) }) }}
          </div>
        </template>
        <span v-else>—</span>
      </template>
      <template #item.branch_name="{ item }">
        {{ item.raw.branch_name || $t('hr.attendance_setup.all_branches') }}
      </template>
      <template #item.employees_count="{ item }">
        <VChip
          size="small"
          variant="tonal"
          prepend-icon="tabler-users"
        >
          {{ item.raw.employees_count ?? 0 }}
        </VChip>
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
              v-if="canAssign && item.raw.is_active"
              icon
              variant="text"
              size="small"
              color="primary"
              @click="assignShift = item.raw"
            >
              <VIcon icon="tabler-user-plus" />
              <VTooltip activator="parent">
                {{ $t('hr.shifts.assign') }}
              </VTooltip>
            </VBtn>
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
              @click="historyShift = item.raw"
            >
              <VIcon icon="tabler-history" />
              <VTooltip activator="parent">
                {{ $t('hr.shifts.history') }}
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

    <HrShiftDialog
      v-model="isDialogVisible"
      :shift="dialogShift"
      @saved="afterChange"
    />
    <HrShiftAssignDialog
      :model-value="!!assignShift"
      :shift="assignShift"
      @update:model-value="assignShift = null"
      @assigned="afterChange"
    />
    <HrShiftVersionsDialog
      :model-value="!!historyShift"
      :shift="historyShift"
      @update:model-value="historyShift = null"
      @changed="afterChange"
    />

    <HrConfirmDialog
      v-model="confirm.visible"
      :action="confirm.action"
      @confirm="runConfirmed"
    />
    <HrConfirmDialog
      :model-value="!!statusTarget"
      :action="statusTarget?.is_active ? 'delete' : 'restore'"
      :message="$t(statusTarget?.is_active ? 'hr.shifts.confirm_deactivate' : 'hr.shifts.confirm_activate')"
      :confirm-label="$t(statusTarget?.is_active ? 'hr.organization.deactivate' : 'hr.organization.activate')"
      @update:model-value="statusTarget = null"
      @confirm="changeStatus"
    />
  </div>
</template>
