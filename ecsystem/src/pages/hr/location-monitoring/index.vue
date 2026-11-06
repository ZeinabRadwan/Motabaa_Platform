<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrLocationMonitoringApi } from '@/plugins/apis/hrRequest'
import { LOCATION_STATUSES } from '@/utils/hrLocationMonitoring'
import HrAttendanceDevicesTable from '@/views/hr/location-monitoring/HrAttendanceDevicesTable.vue'
import HrLocationChecksTable from '@/views/hr/location-monitoring/HrLocationChecksTable.vue'
import HrLocationExceptionsTable from '@/views/hr/location-monitoring/HrLocationExceptionsTable.vue'
import HrLocationSessionsView from '@/views/hr/location-monitoring/HrLocationSessionsView.vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const TABS = [
  { value: 'live', icon: 'tabler-map-pin', title: 'hr.location_monitoring.tabs.live' },
  { value: 'alerts', icon: 'tabler-bell', title: 'hr.location_monitoring.tabs.alerts' },
  { value: 'history', icon: 'tabler-history', title: 'hr.location_monitoring.tabs.history' },
  { value: 'devices', icon: 'tabler-device-mobile', title: 'hr.location_monitoring.tabs.devices' },
]

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { lookups } = useHrLookups()

const activeTab = computed({
  get: () => (TABS.some(tab => tab.value === route.query.tab) ? route.query.tab : TABS[0].value),
  set: tab => router.replace({ query: { ...route.query, tab } }),
})

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(new Date())

const filters = reactive({
  branchId: null,
  employeeId: null,
  date: today(),
  status: null,
})

const historySession = ref(null)
const employees = ref([])

const loadEmployees = () => hrLocationMonitoringApi.employees({ branch_id: filters.branchId || undefined })
  .then(({ data }) => {
    employees.value = data.data
    if (filters.employeeId && !employees.value.some(option => option.value === filters.employeeId))
      filters.employeeId = null
  })
  .catch(() => {
    employees.value = []
  })

watch(() => filters.branchId, loadEmployees, { immediate: true })

const statusItems = computed(() => [...LOCATION_STATUSES, 'not_monitored'].map(value => ({ value, title: t(`hr.location_monitoring.statuses.${value}`) })))

const showHistory = session => {
  historySession.value = session.id
  activeTab.value = 'history'
}

const sessionsView = ref(null)
</script>

<template>
  <div class="hr-page">
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
              :items="employees"
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
            <AppDateTimePicker
              v-model="filters.date"
              :placeholder="$t('hr.location_monitoring.date')"
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
              :items="statusItems"
              :placeholder="$t('hr.location_monitoring.status')"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard>
      <VTabs
        v-model="activeTab"
        class="hr-page__tabs"
        show-arrows
      >
        <VTab
          v-for="tab in TABS"
          :key="tab.value"
          :value="tab.value"
        >
          <VIcon
            :icon="tab.icon"
            start
          />
          {{ $t(tab.title) }}
        </VTab>
      </VTabs>
      <VDivider />

      <VWindow
        v-model="activeTab"
        :touch="false"
      >
        <VWindowItem value="live">
          <VCardText>
            <HrLocationSessionsView
              ref="sessionsView"
              :filters="filters"
              @history="showHistory"
              @alerts="activeTab = 'alerts'"
            />
          </VCardText>
        </VWindowItem>
        <VWindowItem value="alerts">
          <HrLocationExceptionsTable
            :filters="filters"
            @reviewed="sessionsView?.refresh()"
          />
        </VWindowItem>
        <VWindowItem value="history">
          <HrLocationChecksTable
            :filters="filters"
            :attendance-record-id="historySession"
            @clear-session="historySession = null"
          />
        </VWindowItem>
        <VWindowItem value="devices">
          <HrAttendanceDevicesTable :filters="filters" />
        </VWindowItem>
      </VWindow>
    </VCard>

    <VAlert
      type="info"
      variant="tonal"
      density="compact"
      icon="tabler-shield-lock"
      class="mt-6"
    >
      {{ $t('hr.location_monitoring.privacy_hr') }}
    </VAlert>
  </div>
</template>

<route lang="yaml">
meta:
  action: access_hr-location-monitoring
  subject: access_hr-location-monitoring
  pageTitleKey: hr.location_monitoring.title
  pageSubtitleKey: hr.location_monitoring.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
