<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { hrLocationMonitoringApi } from '@/plugins/apis/hrRequest'
import { formatHrDateTime } from '@/utils/hrFormat'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

/**
 * Mobile devices registered for location updates (integration foundation for the future mobile application).
 * Approving a device replaces the employee's previous active device; a revoked device cannot send locations again.
 */
const props = defineProps({
  filters: { type: Object, required: true },
})

const { t, locale } = useI18n()

const STATUS_COLORS = { pending: 'warning', active: 'success', revoked: 'secondary' }

const canEdit = computed(() => can('edit_hr-location-monitoring', 'edit_hr-location-monitoring'))

const status = ref(null)
const items = ref([])
const total = ref(0)
const loading = ref(false)
const options = ref({ page: 1, itemsPerPage: 25, sortBy: [], groupBy: [] })

const statusItems = computed(() => ['pending', 'active', 'revoked'].map(value => ({ value, title: t(`hr.location_monitoring.devices.statuses.${value}`) })))

const fetchDevices = () => {
  loading.value = true

  return hrLocationMonitoringApi.devices({
    branch_id: props.filters.branchId || undefined,
    employee_id: props.filters.employeeId || undefined,
    status: status.value || undefined,
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

watchServerTableFetch(fetchDevices, {
  filters: () => [props.filters.branchId, props.filters.employeeId, status.value],
  options,
})

const headers = computed(() => [
  { title: t('hr.fields.employee'), key: 'employee', sortable: false },
  { title: t('hr.location_monitoring.devices.device'), key: 'device', sortable: false },
  { title: t('hr.location_monitoring.status'), key: 'status', sortable: false },
  { title: t('hr.location_monitoring.devices.registered_at'), key: 'registered_at', sortable: false },
  { title: t('hr.location_monitoring.devices.last_location_at'), key: 'last_location_at', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
])

const action = ref(null)

// Errors (e.g. a device that is no longer pending) are shown by the axios interceptor.
const confirmAction = () => {
  const { type, device } = action.value
  const request = type === 'approve' ? hrLocationMonitoringApi.approveDevice : hrLocationMonitoringApi.revokeDevice

  action.value = null
  request(device.id)
    .then(({ data }) => window.Snackbar?.value?.exposevisibleSnackbar?.(data.message, 'success'))
    .catch(() => {})
    .finally(fetchDevices)
}
</script>

<template>
  <div>
    <VCardText class="pb-0">
      <VRow>
        <VCol
          cols="12"
          sm="4"
          md="3"
        >
          <AppSelect
            v-model="status"
            :items="statusItems"
            :placeholder="$t('hr.location_monitoring.status')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="8"
          md="9"
          class="d-flex align-center text-caption hr-page__muted"
        >
          {{ $t('hr.location_monitoring.devices.note') }}
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
      item-value="id"
      class="text-no-wrap"
      @update:options="applyServerTableOptions(options, $event)"
    >
      <template #item.employee="{ item }">
        <div>{{ item.raw.employee?.name ?? '—' }}</div>
        <div
          class="text-caption hr-page__muted"
          dir="ltr"
        >
          {{ item.raw.employee?.employee_number }}
        </div>
      </template>
      <template #item.device="{ item }">
        <div>{{ item.raw.device_name || item.raw.device_model || '—' }}</div>
        <div
          class="text-caption hr-page__muted"
          dir="ltr"
        >
          {{ [item.raw.platform, item.raw.os_version, item.raw.app_version ? `v${item.raw.app_version}` : null].filter(Boolean).join(' · ') }}
        </div>
      </template>
      <template #item.status="{ item }">
        <VChip
          :color="STATUS_COLORS[item.raw.status] || 'secondary'"
          size="small"
          label
        >
          {{ $t(`hr.location_monitoring.devices.statuses.${item.raw.status}`) }}
        </VChip>
        <div
          v-if="item.raw.revoke_reason"
          class="text-caption hr-page__muted"
        >
          {{ $t(`hr.location_monitoring.devices.revoke_reasons.${item.raw.revoke_reason}`) }}
        </div>
      </template>
      <template #item.registered_at="{ item }">
        {{ formatHrDateTime(item.raw.registered_at, locale) }}
      </template>
      <template #item.last_location_at="{ item }">
        {{ formatHrDateTime(item.raw.last_location_at, locale) }}
      </template>
      <template #item.actions="{ item }">
        <template v-if="canEdit">
          <VBtn
            v-if="item.raw.status === 'pending'"
            size="small"
            color="success"
            variant="tonal"
            class="me-2"
            @click="action = { type: 'approve', device: item.raw }"
          >
            {{ $t('hr.location_monitoring.devices.approve') }}
          </VBtn>
          <VBtn
            v-if="item.raw.status !== 'revoked'"
            size="small"
            color="error"
            variant="tonal"
            @click="action = { type: 'revoke', device: item.raw }"
          >
            {{ $t('hr.location_monitoring.devices.revoke') }}
          </VBtn>
        </template>
      </template>
      <template #no-data>
        <div class="pa-4 hr-page__muted">
          {{ $t('hr.location_monitoring.devices.none') }}
        </div>
      </template>
    </VDataTableServer>

    <HrConfirmDialog
      :model-value="!!action"
      :action="action?.type === 'approve' ? 'restore' : 'delete'"
      :message="action ? $t(`hr.location_monitoring.devices.confirm_${action.type}`) : ''"
      :confirm-label="action ? $t(`hr.location_monitoring.devices.${action.type}`) : ''"
      @update:model-value="action = null"
      @confirm="confirmAction"
    />
  </div>
</template>
