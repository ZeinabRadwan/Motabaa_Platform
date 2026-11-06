<script setup>
import HrEmployeesTable from '@/views/hr/employees/HrEmployeesTable.vue'
</script>

<template>
  <section class="hr-page">
    <HrEmployeesTable />
  </section>
</template>

<route lang="yaml">
meta:
  action: access_hr-employees
  subject: access_hr-employees
  pageTitleKey: hr.employees.title
  pageSubtitleKey: hr.employees.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
