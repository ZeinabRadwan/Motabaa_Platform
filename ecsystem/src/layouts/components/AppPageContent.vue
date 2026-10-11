<script setup>
import AppBreadcrumb from '@/components/AppBreadcrumb.vue'
import GeneralPageHeader from '@/components/GeneralPageHeader.vue'
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import SidebarMobileToggle from '@/layouts/components/SidebarMobileToggle.vue'
</script>

<template>
  <div class="app-page-content">
    <div class="app-page-content__top">
      <SidebarMobileToggle />
      <AppBreadcrumb />
      <VSpacer />
      <NavBarNotifications class="app-page-content__notifications" />
    </div>
    <GeneralPageHeader />
    <slot />
  </div>
</template>

<style lang="scss" scoped>
.app-page-content__top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  margin-block-end: 0.25rem;
}

.app-page-content__notifications {
  flex-shrink: 0;
}
</style>
