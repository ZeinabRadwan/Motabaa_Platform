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

@media (max-width: 1279.98px) {
  .app-page-content__top {
    position: sticky;
    inset-block-start: 0;
    z-index: 10;
    margin-inline: -0.25rem;
    padding-block: 0.35rem 0.5rem;
    padding-inline: 0.25rem;
    margin-block-end: 0.5rem;
    background: rgb(var(--v-theme-background));
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  }
}
</style>
