<script setup>
import HrLocationMap from '@/components/hr/HrLocationMap.vue'
import { hrLocationMonitoringApi } from '@/plugins/apis/hrRequest'
import { LOCATION_STATUS_COLORS } from '@/utils/hrLocationMonitoring'
import { useI18n } from 'vue-i18n'
import { VDataTable } from 'vuetify/labs/VDataTable'

/*
 * Attendance sessions of the selected day with their current location verification status, the employees' last
 * verified positions on the map, and summary counts. Refreshed every minute while the page is visible.
 */
const props = defineProps({
  filters: { type: Object, required: true },
})

const emit = defineEmits(['history', 'alerts'])

const { t } = useI18n()

const REFRESH_MS = 60000

const sessions = ref([])
const summary = ref(null)
const loading = ref(false)
const loaded = ref(false)
const failed = ref(false)

const fetchSessions = async () => {
  loading.value = true
  try {
    const { data } = await hrLocationMonitoringApi.sessions({
      branch_id: props.filters.branchId || undefined,
      employee_id: props.filters.employeeId || undefined,
      date: props.filters.date || undefined,
      status: props.filters.status || undefined,
    })

    sessions.value = data.data.sessions
    summary.value = data.data.summary
    failed.value = false
  }
  catch {
    failed.value = !loaded.value
  }
  finally {
    loading.value = false
    loaded.value = true
  }
}

watch(() => ({ ...props.filters }), fetchSessions, { deep: true, immediate: true })

const statusLabel = status => t(`hr.location_monitoring.statuses.${status}`)

const failedCount = computed(() => {
  const counts = summary.value?.by_status ?? {}

  return (counts.permission_denied ?? 0) + (counts.gps_unavailable ?? 0) + (counts.verification_failed ?? 0)
})

const cards = computed(() => {
  const counts = summary.value?.by_status ?? {}

  return [
    { key: 'open', icon: 'tabler-clock-play', color: 'primary', value: summary.value?.open_sessions ?? 0, label: t('hr.location_monitoring.cards.open_sessions') },
    { key: 'within', icon: 'tabler-map-pin-check', color: 'success', value: counts.within_radius ?? 0, label: statusLabel('within_radius') },
    { key: 'outside', icon: 'tabler-map-pin-off', color: 'error', value: counts.outside_radius ?? 0, label: statusLabel('outside_radius') },
    { key: 'pending', icon: 'tabler-hourglass', color: 'secondary', value: counts.pending ?? 0, label: t('hr.location_monitoring.cards.pending') },
    { key: 'failed', icon: 'tabler-alert-triangle', color: 'warning', value: failedCount.value, label: t('hr.location_monitoring.cards.failed') },
    { key: 'alerts', icon: 'tabler-bell', color: 'error', value: summary.value?.pending_alerts ?? 0, label: t('hr.location_monitoring.cards.pending_alerts'), click: () => emit('alerts') },
  ]
})

const meters = value => (value === null || value === undefined ? null : t('hr.my_attendance.meters', { value: Math.round(value) }))

const locations = computed(() => {
  const byId = new Map()

  sessions.value.forEach(session => {
    const location = session.work_location
    if (location?.latitude !== null && location?.latitude !== undefined && !byId.has(location.id))
      byId.set(location.id, { id: location.id, name: location.name, latitude: location.latitude, longitude: location.longitude, radius_meters: location.radius_meters })
  })

  return [...byId.values()]
})

const markers = computed(() => sessions.value
  .filter(session => session.status === 'checked_in' && session.last_check?.latitude !== null && session.last_check?.latitude !== undefined)
  .map(session => ({
    id: session.id,
    latitude: session.last_check.latitude,
    longitude: session.last_check.longitude,
    color: LOCATION_STATUS_COLORS[session.last_check.status] ?? 'info',
    title: session.employee.name,
    lines: [
      `${statusLabel(session.last_check.status)} · ${session.last_check.time}`,
      session.last_check.distance_meters !== null ? t('hr.location_monitoring.distance', { distance: Math.round(session.last_check.distance_meters), radius: session.work_location?.radius_meters ?? '—' }) : null,
      session.last_check.accuracy_meters !== null ? t('hr.my_attendance.accuracy_short', { accuracy: Math.round(session.last_check.accuracy_meters) }) : null,
      session.work_location?.name,
    ],
  })))

const headers = computed(() => [
  { title: t('hr.fields.employee'), key: 'employee', sortable: false },
  { title: t('hr.location_monitoring.session'), key: 'session', sortable: false },
  { title: t('hr.location_monitoring.current_status'), key: 'location_status', sortable: false },
  { title: t('hr.location_monitoring.last_check'), key: 'last_check', sortable: false },
  { title: t('hr.location_monitoring.next_check'), key: 'next_check_time', sortable: false },
  { title: t('hr.location_monitoring.checks'), key: 'checks', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
])

const SESSION_COLORS = { checked_in: 'success', checked_out: 'info', missing_check_out: 'warning' }

let timer = null

onMounted(() => {
  timer = setInterval(() => {
    if (document.visibilityState === 'visible' && !loading.value)
      fetchSessions()
  }, REFRESH_MS)
})

onBeforeUnmount(() => clearInterval(timer))

defineExpose({ refresh: fetchSessions })
</script>

<template>
  <div>
    <VAlert
      v-if="failed"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      {{ $t('hr.location_monitoring.load_failed') }}
    </VAlert>

    <VRow class="mb-2">
      <VCol
        v-for="card in cards"
        :key="card.key"
        cols="6"
        sm="4"
        lg="2"
      >
        <VCard
          variant="outlined"
          class="h-100"
          :class="{ 'cursor-pointer': card.click }"
          @click="card.click?.()"
        >
          <VCardText class="d-flex align-center gap-3">
            <VAvatar
              :color="card.color"
              variant="tonal"
              rounded
            >
              <VIcon :icon="card.icon" />
            </VAvatar>
            <div>
              <div class="text-h5">
                {{ card.value }}
              </div>
              <div class="text-caption hr-page__muted">
                {{ card.label }}
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <HrLocationMap
      :locations="locations"
      :markers="markers"
      height="380px"
      class="mb-4"
    />
    <div class="d-flex flex-wrap gap-3 text-caption hr-page__muted mb-4">
      <span>{{ $t('hr.location_monitoring.map_note') }}</span>
      <span v-if="summary?.not_monitored_open_sessions">{{ $t('hr.location_monitoring.not_monitored_count', { count: summary.not_monitored_open_sessions }) }}</span>
    </div>

    <VDataTable
      :items="sessions"
      :headers="headers"
      :loading="loading"
      :items-per-page="25"
      item-value="id"
      class="text-no-wrap"
    >
      <template #item.employee="{ item }">
        <div class="font-weight-medium">
          {{ item.raw.employee.name }}
        </div>
        <div class="text-caption hr-page__muted">
          <span dir="ltr">{{ item.raw.employee.employee_number }}</span>
          <span v-if="item.raw.employee.branch"> · {{ item.raw.employee.branch.name }}</span>
        </div>
      </template>
      <template #item.session="{ item }">
        <VChip
          :color="SESSION_COLORS[item.raw.status] || 'secondary'"
          size="x-small"
          label
        >
          {{ $t(`hr.my_attendance.status.${item.raw.status}`) }}
        </VChip>
        <div
          class="text-caption"
          dir="ltr"
        >
          {{ item.raw.attendance_date }} · {{ item.raw.check_in_time }} - {{ item.raw.check_out_time ?? '…' }}
        </div>
      </template>
      <template #item.location_status="{ item }">
        <VChip
          :color="LOCATION_STATUS_COLORS[item.raw.location_status] || 'secondary'"
          size="small"
          label
        >
          {{ $t(`hr.location_monitoring.statuses.${item.raw.location_status}`) }}
        </VChip>
        <VChip
          v-if="item.raw.overdue"
          color="warning"
          size="x-small"
          label
          class="ms-1"
        >
          {{ $t('hr.location_monitoring.overdue') }}
        </VChip>
        <VChip
          v-if="item.raw.open_exceptions_count"
          color="error"
          size="x-small"
          variant="outlined"
          label
          class="ms-1"
        >
          {{ $t('hr.location_monitoring.open_alert') }}
        </VChip>
      </template>
      <template #item.last_check="{ item }">
        <template v-if="item.raw.last_check">
          <span dir="ltr">{{ item.raw.last_check.time }}</span>
          <div class="text-caption hr-page__muted">
            {{ [meters(item.raw.last_check.distance_meters), item.raw.last_check.accuracy_meters !== null ? $t('hr.my_attendance.accuracy_short', { accuracy: Math.round(item.raw.last_check.accuracy_meters) }) : null].filter(Boolean).join(' · ') }}
          </div>
        </template>
        <span v-else>—</span>
      </template>
      <template #item.next_check_time="{ item }">
        <span dir="ltr">{{ item.raw.next_check_time ?? '—' }}</span>
      </template>
      <template #item.checks="{ item }">
        <span>{{ item.raw.checks_count }}</span>
        <span
          v-if="item.raw.missed_count"
          class="text-warning"
        > · {{ $t('hr.location_monitoring.missed_short', { count: item.raw.missed_count }) }}</span>
      </template>
      <template #item.actions="{ item }">
        <VBtn
          v-if="item.raw.monitored"
          icon
          size="small"
          variant="text"
          @click="emit('history', item.raw)"
        >
          <VIcon icon="tabler-history" />
          <VTooltip activator="parent">
            {{ $t('hr.location_monitoring.view_history') }}
          </VTooltip>
        </VBtn>
      </template>
      <template #no-data>
        <div class="pa-4 hr-page__muted">
          {{ $t('hr.location_monitoring.no_sessions') }}
        </div>
      </template>
    </VDataTable>
  </div>
</template>
