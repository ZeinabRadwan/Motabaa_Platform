<script setup>
import { hrLocationMonitoringApi } from '@/plugins/apis/hrRequest'
import { LOCATION_SOURCES, LOCATION_STATUS_COLORS } from '@/utils/hrLocationMonitoring'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

/** Location history: every verification attempt, including pending (missed) checks without a location. */
const props = defineProps({
  filters: { type: Object, required: true },
  attendanceRecordId: { type: Number, default: null },
})

const emit = defineEmits(['clear-session'])

const { t } = useI18n()

const KINDS = ['check_in', 'periodic', 'check_out']

const source = ref(null)
const kind = ref(null)
const items = ref([])
const total = ref(0)
const loading = ref(false)
const options = ref({ page: 1, itemsPerPage: 25, sortBy: [], groupBy: [] })

const sourceItems = computed(() => LOCATION_SOURCES.map(value => ({ value, title: t(`hr.location_monitoring.sources.${value}`) })))
const kindItems = computed(() => KINDS.map(value => ({ value, title: t(`hr.location_monitoring.kinds.${value}`) })))

const fetchChecks = () => {
  loading.value = true

  return hrLocationMonitoringApi.checks({
    branch_id: props.filters.branchId || undefined,
    employee_id: props.filters.employeeId || undefined,
    status: props.filters.status && props.filters.status !== 'not_monitored' ? props.filters.status : undefined,
    from: props.attendanceRecordId ? undefined : props.filters.date || undefined,
    to: props.attendanceRecordId ? undefined : props.filters.date || undefined,
    attendance_record_id: props.attendanceRecordId || undefined,
    source: source.value || undefined,
    kind: kind.value || undefined,
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

watchServerTableFetch(fetchChecks, {
  filters: () => [props.filters.branchId, props.filters.employeeId, props.filters.status, props.filters.date, props.attendanceRecordId, source.value, kind.value],
  options,
})

const headers = computed(() => [
  { title: t('hr.location_monitoring.timestamp'), key: 'timestamp', sortable: false },
  { title: t('hr.fields.employee'), key: 'employee', sortable: false },
  { title: t('hr.location_monitoring.kind'), key: 'kind', sortable: false },
  { title: t('hr.location_monitoring.status'), key: 'status', sortable: false },
  { title: t('hr.location_monitoring.distance_header'), key: 'distance_meters', sortable: false },
  { title: t('hr.location_monitoring.coordinates'), key: 'coordinates', sortable: false },
  { title: t('hr.location_monitoring.source'), key: 'source', sortable: false },
])

const mapLink = check => `https://www.openstreetmap.org/?mlat=${check.latitude}&mlon=${check.longitude}#map=18/${check.latitude}/${check.longitude}`

defineExpose({ refresh: fetchChecks })
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
            v-model="kind"
            :items="kindItems"
            :placeholder="$t('hr.location_monitoring.kind')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
          md="3"
        >
          <AppSelect
            v-model="source"
            :items="sourceItems"
            :placeholder="$t('hr.location_monitoring.source')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          v-if="attendanceRecordId"
          cols="12"
          md="6"
          class="d-flex align-center"
        >
          <VChip
            closable
            color="primary"
            label
            @click:close="emit('clear-session')"
          >
            {{ $t('hr.location_monitoring.session_filter', { id: attendanceRecordId }) }}
          </VChip>
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
      <template #item.timestamp="{ item }">
        <span dir="ltr">{{ item.raw.date }} {{ item.raw.time }}</span>
        <div
          v-if="item.raw.due_time && item.raw.due_time !== item.raw.time"
          class="text-caption hr-page__muted"
        >
          {{ $t('hr.location_monitoring.due_at', { time: item.raw.due_time }) }}
        </div>
      </template>
      <template #item.employee="{ item }">
        <div>{{ item.raw.employee.name }}</div>
        <div class="text-caption hr-page__muted">
          <span dir="ltr">{{ item.raw.employee.employee_number }}</span>
          <span v-if="item.raw.employee.branch"> · {{ item.raw.employee.branch.name }}</span>
        </div>
      </template>
      <template #item.kind="{ item }">
        {{ $t(`hr.location_monitoring.kinds.${item.raw.kind}`) }}
      </template>
      <template #item.status="{ item }">
        <VChip
          :color="item.raw.missed ? 'warning' : LOCATION_STATUS_COLORS[item.raw.status] || 'secondary'"
          size="small"
          label
        >
          {{ item.raw.missed ? $t('hr.location_monitoring.missed') : $t(`hr.location_monitoring.statuses.${item.raw.status}`) }}
        </VChip>
        <div
          v-if="item.raw.reason"
          class="text-caption hr-page__muted"
        >
          {{ $t(`hr.location_monitoring.reasons.${item.raw.reason}`) }}
        </div>
      </template>
      <template #item.distance_meters="{ item }">
        <template v-if="item.raw.distance_meters !== null">
          {{ $t('hr.location_monitoring.distance', { distance: Math.round(item.raw.distance_meters), radius: item.raw.radius_meters ?? '—' }) }}
          <div
            v-if="item.raw.accuracy_meters !== null"
            class="text-caption hr-page__muted"
          >
            {{ $t('hr.my_attendance.accuracy_short', { accuracy: Math.round(item.raw.accuracy_meters) }) }}
          </div>
        </template>
        <span v-else>—</span>
      </template>
      <template #item.coordinates="{ item }">
        <a
          v-if="item.raw.latitude !== null"
          :href="mapLink(item.raw)"
          target="_blank"
          rel="noopener noreferrer"
          dir="ltr"
        >{{ item.raw.latitude }}, {{ item.raw.longitude }}</a>
        <span v-else>—</span>
      </template>
      <template #item.source="{ item }">
        {{ $t(`hr.location_monitoring.sources.${item.raw.source}`) }}
        <div
          v-if="item.raw.device"
          class="text-caption hr-page__muted"
        >
          {{ item.raw.device.name || item.raw.device.platform }}
        </div>
      </template>
    </VDataTableServer>
  </div>
</template>
