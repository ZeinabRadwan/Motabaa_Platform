<script setup>
import HrEmployeeForm from '@/views/hr/employees/HrEmployeeForm.vue'
</script>

<template>
  <section class="hr-page">
    <HrEmployeeForm />
  </section>
</template>

<route lang="yaml">
meta:
  action: edit_hr-employees
  subject: edit_hr-employees
  pageTitleKey: hr.employees.add
  pageSubtitleKey: hr.employees.add_subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
