<script setup>
import HrAttendanceDaysTable from '@/views/hr/attendance-processing/HrAttendanceDaysTable.vue'
import HrAttendancePeriodsTable from '@/views/hr/attendance-processing/HrAttendancePeriodsTable.vue'
import { can } from '@layouts/plugins/casl'
import { useRoute, useRouter } from 'vue-router'

const TABS = [
  { value: 'daily', icon: 'tabler-calendar-stats', title: 'hr.attendance_processing.daily' },
  { value: 'exceptions', icon: 'tabler-alert-triangle', title: 'hr.attendance_processing.exceptions' },
  { value: 'periods', icon: 'tabler-report-money', title: 'hr.attendance_processing.periods' },
]

const route = useRoute()
const router = useRouter()

const activeTab = computed({
  get: () => (TABS.some(tab => tab.value === route.query.tab) ? route.query.tab : TABS[0].value),
  set: tab => router.replace({ query: { ...route.query, tab } }),
})

const canAdmin = computed(() => can('admin_hr-attendance-processing', 'admin_hr-attendance-processing'))
const canEdit = computed(() => can('edit_hr-attendance-processing', 'edit_hr-attendance-processing') || canAdmin.value)

const daily = ref(null)
const exceptions = ref(null)

const refreshDays = () => {
  daily.value?.refresh()
  exceptions.value?.refresh()
}
</script>

<template>
  <VCard class="hr-page">
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
      <VWindowItem value="daily">
        <HrAttendanceDaysTable
          ref="daily"
          mode="daily"
          :can-edit="canEdit"
          @changed="exceptions?.refresh()"
        />
      </VWindowItem>
      <VWindowItem value="exceptions">
        <HrAttendanceDaysTable
          ref="exceptions"
          mode="exceptions"
          :can-edit="canEdit"
          @changed="daily?.refresh()"
        />
      </VWindowItem>
      <VWindowItem value="periods">
        <HrAttendancePeriodsTable
          :can-edit="canEdit"
          :can-admin="canAdmin"
          @changed="refreshDays"
        />
      </VWindowItem>
    </VWindow>
  </VCard>
</template>

<route lang="yaml">
meta:
  action: access_hr-attendance-processing
  subject: access_hr-attendance-processing
  actionsAny:
    - access_hr-attendance-processing
    - edit_hr-attendance-processing
    - admin_hr-attendance-processing
  pageTitleKey: hr.attendance_processing.title
  pageSubtitleKey: hr.attendance_processing.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
