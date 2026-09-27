<script setup>
import AppBreadcrumb from '@/components/AppBreadcrumb.vue'
import SidebarMobileToggle from '@/layouts/components/SidebarMobileToggle.vue'
</script>

<template>
  <div class="app-page-content">
    <SidebarMobileToggle />
    <AppBreadcrumb />
    <slot />
  </div>
</template>
