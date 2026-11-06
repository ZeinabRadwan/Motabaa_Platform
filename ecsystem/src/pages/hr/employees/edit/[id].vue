<script setup>
import HrEmployeeForm from '@/views/hr/employees/HrEmployeeForm.vue'
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

<template>
  <section class="hr-page">
    <HrEmployeeForm
      :key="route.params.id"
      :employee-id="route.params.id"
    />
  </section>
</template>

<route lang="yaml">
meta:
  action: edit_hr-employees
  subject: edit_hr-employees
  pageTitleKey: hr.employees.edit
  pageSubtitleKey: hr.employees.edit_subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
