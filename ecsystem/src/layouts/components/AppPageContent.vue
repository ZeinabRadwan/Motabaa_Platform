<script setup>
import AppBreadcrumb from '@/components/AppBreadcrumb.vue'
import GeneralPageHeader from '@/components/GeneralPageHeader.vue'
import SidebarMobileToggle from '@/layouts/components/SidebarMobileToggle.vue'
</script>

<template>
  <div class="app-page-content">
    <SidebarMobileToggle />
    <AppBreadcrumb />
    <GeneralPageHeader />
    <slot />
  </div>
</template>
