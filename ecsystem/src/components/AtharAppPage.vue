<script setup>
import dashboardBg from '@images/dashboard.png'

defineProps({
  /** Show dashboard-style fixed background (matches analytics). */
  useDashboardBackdrop: {
    type: Boolean,
    default: true,
  },
})
</script>

<template>
  <div
    class="athar-app-page-view"
    :style="useDashboardBackdrop ? { '--athar-dashboard-bg': `url(${dashboardBg})` } : undefined"
  >
    <div
      class="athar-app-page-view__backdrop"
      aria-hidden="true"
    />

    <div class="athar-page-body">
      <slot />
    </div>
  </div>
</template>

<style lang="scss">
@import '@/styles/dashboard-page.scss';
</style>
