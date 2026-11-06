<script setup>
import HrApprovalsInbox from '@/views/hr/approvals/HrApprovalsInbox.vue'
</script>

<template>
  <section class="hr-page">
    <HrApprovalsInbox />
  </section>
</template>

<!-- Any signed-in user: direct/department/branch managers approve without holding HR permissions. -->
<route lang="yaml">
meta:
  action: all-users
  subject: Auth
  pageTitleKey: hr.approvals.title
  pageSubtitleKey: hr.approvals.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
