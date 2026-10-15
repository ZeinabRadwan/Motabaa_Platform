<script setup>
import AppBreadcrumb from '@/components/AppBreadcrumb.vue'
import GeneralPageHeader from '@/components/GeneralPageHeader.vue'
import SidebarMobileToggle from '@/layouts/components/SidebarMobileToggle.vue'
import { markRaw, provide, shallowRef } from 'vue'

const headerActionsComponent = shallowRef(null)

provide('registerGeneralPageHeaderActions', component => {
  headerActionsComponent.value = component ? markRaw(component) : null
})

provide('unregisterGeneralPageHeaderActions', () => {
  headerActionsComponent.value = null
})
</script>

<template>
  <div class="app-page-content">
    <div class="app-page-content__top">
      <SidebarMobileToggle />
      <AppBreadcrumb />
      <VSpacer />
    </div>
    <div class="app-page-header-region">
      <GeneralPageHeader />
      <div
        id="general-page-header-actions"
        class="general-page-header__actions"
      >
        <component
          :is="headerActionsComponent"
          v-if="headerActionsComponent"
        />
      </div>
    </div>
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

</style>

<!-- layout-wrapper is an ancestor; class tracks the same breakpoint as SidebarMobileToggle -->
<style lang="scss">
.layout-wrapper.layout-overlay-nav .app-page-content__top {
  position: sticky;
  inset-block-start: 0;
  z-index: 12;
  margin-inline: -0.25rem;
  padding-block: 0.35rem 0.5rem;
  padding-inline: 0.25rem;
  margin-block-end: 0.5rem;
  background-color: rgb(var(--v-theme-background));
  border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
