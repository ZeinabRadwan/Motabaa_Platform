<script setup>
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'
import HrAttendancePunchesTable from '@/views/hr/attendance/HrAttendancePunchesTable.vue'
import HrAttendanceRecordsTable from '@/views/hr/attendance/HrAttendanceRecordsTable.vue'
import { can } from '@layouts/plugins/casl'

// Attendance of the employee (electronic and manual records) and every electronic check-in / check-out attempt.
const props = defineProps({
  employee: { type: Object, required: true },
})

const tab = ref('records')

const canOpenRecords = computed(() => can('access_hr-attendance-records', 'access_hr-attendance-records'))

const fetchRecords = params => hrEmployeesApi.attendanceRecords(props.employee.id, params)
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>{{ $t('hr.attendance_log.title') }}</VCardTitle>
      <VCardSubtitle>{{ $t('hr.attendance_log.subtitle') }}</VCardSubtitle>
      <template
        v-if="canOpenRecords"
        #append
      >
        <VBtn
          variant="tonal"
          size="small"
          prepend-icon="tabler-external-link"
          :to="{ name: 'hr-attendance-records', query: { employee_id: employee.id } }"
        >
          {{ $t('hr.attendance_records.title') }}
        </VBtn>
      </template>
    </VCardItem>
    <VTabs
      v-model="tab"
      class="hr-page__tabs"
    >
      <VTab value="records">
        <VIcon
          icon="tabler-list-details"
          start
        />
        {{ $t('hr.attendance_log.records') }}
      </VTab>
      <VTab value="attempts">
        <VIcon
          icon="tabler-history"
          start
        />
        {{ $t('hr.attendance_log.attempts') }}
      </VTab>
    </VTabs>
    <VDivider />
    <VWindow
      v-model="tab"
      :touch="false"
    >
      <VWindowItem value="records">
        <HrAttendanceRecordsTable
          :fetch="fetchRecords"
          show-location-details
          review-filter
        />
      </VWindowItem>
      <VWindowItem value="attempts">
        <HrAttendancePunchesTable :employee-id="employee.id" />
      </VWindowItem>
    </VWindow>
  </VCard>
</template>
