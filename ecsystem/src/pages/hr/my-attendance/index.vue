<script setup>
import { hrMyAttendanceApi } from '@/plugins/apis/hrRequest'
import HrAttendanceRecordsTable from '@/views/hr/attendance/HrAttendanceRecordsTable.vue'
import HrMyAttendancePanel from '@/views/hr/my-attendance/HrMyAttendancePanel.vue'

const history = ref(null)
</script>

<template>
  <div class="hr-page">
    <HrMyAttendancePanel @punched="history?.refresh()" />

    <VCard class="mt-6">
      <VCardItem>
        <VCardTitle>{{ $t('hr.my_attendance.history_title') }}</VCardTitle>
      </VCardItem>
      <HrAttendanceRecordsTable
        ref="history"
        :fetch="hrMyAttendanceApi.history"
      />
    </VCard>
  </div>
</template>

<route lang="yaml">
meta:
  action: apply_hr-self-attendance
  subject: apply_hr-self-attendance
  pageTitleKey: hr.my_attendance.title
  pageSubtitleKey: hr.my_attendance.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
