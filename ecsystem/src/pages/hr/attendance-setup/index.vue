<script setup>
import HrAttendancePoliciesTable from '@/views/hr/attendance/HrAttendancePoliciesTable.vue'
import HrHolidaysTable from '@/views/hr/attendance/HrHolidaysTable.vue'
import HrShiftExceptionsTable from '@/views/hr/attendance/HrShiftExceptionsTable.vue'
import HrShiftScheduleView from '@/views/hr/attendance/HrShiftScheduleView.vue'
import HrShiftsTable from '@/views/hr/attendance/HrShiftsTable.vue'
import { useRoute, useRouter } from 'vue-router'

const TABS = [
  { value: 'schedule', icon: 'tabler-calendar-time', title: 'hr.schedule.title' },
  { value: 'shifts', icon: 'tabler-clock-hour-8', title: 'hr.attendance_setup.shifts' },
  { value: 'exceptions', icon: 'tabler-calendar-event', title: 'hr.exceptions.title' },
  { value: 'holidays', icon: 'tabler-confetti', title: 'hr.holidays.title' },
  { value: 'policies', icon: 'tabler-clipboard-check', title: 'hr.attendance_setup.policies' },
]

const route = useRoute()
const router = useRouter()

const activeTab = computed({
  get: () => (TABS.some(tab => tab.value === route.query.tab) ? route.query.tab : TABS[0].value),
  set: tab => router.replace({ query: { ...route.query, tab } }),
})

// Shift and exception changes alter the resolved schedule shown in the other tab.
const schedule = ref(null)
const exceptions = ref(null)

const onShiftsChanged = () => {
  schedule.value?.refresh()
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
      <VWindowItem value="schedule">
        <HrShiftScheduleView ref="schedule" />
      </VWindowItem>
      <VWindowItem value="shifts">
        <HrShiftsTable @changed="onShiftsChanged" />
      </VWindowItem>
      <VWindowItem value="exceptions">
        <HrShiftExceptionsTable
          ref="exceptions"
          @changed="schedule?.refresh()"
        />
      </VWindowItem>
      <VWindowItem value="holidays">
        <HrHolidaysTable />
      </VWindowItem>
      <VWindowItem value="policies">
        <HrAttendancePoliciesTable @changed="schedule?.refresh()" />
      </VWindowItem>
    </VWindow>
  </VCard>
</template>

<route lang="yaml">
meta:
  action: access_hr-attendance-setup
  subject: access_hr-attendance-setup
  pageTitleKey: hr.attendance_setup.title
  pageSubtitleKey: hr.attendance_setup.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
