<script setup>

</script>

<template>
 <h3>test</h3>
</template>

<style lang="scss">
  .app-user-search-filter {
    inline-size: 31.6rem;
  }

  .text-capitalize {
    text-transform: capitalize;
  }

  .user-list-name:not(:hover) {
    color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
  }
</style>
<route lang="yaml">
  meta:
    action: access_parents
    subject: access_parents
</route>
