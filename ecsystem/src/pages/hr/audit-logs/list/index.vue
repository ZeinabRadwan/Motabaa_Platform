<script setup>
import HrAuditLogTable from '@/views/hr/audit/HrAuditLogTable.vue'
</script>

<template>
  <section class="hr-page">
    <HrAuditLogTable />
  </section>
</template>

<route lang="yaml">
meta:
  action: access_hr-audit
  subject: access_hr-audit
  pageTitleKey: hr.audit.title
  pageSubtitleKey: hr.audit.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
