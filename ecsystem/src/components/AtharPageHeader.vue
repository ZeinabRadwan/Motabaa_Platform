<script setup>
/**
 * @deprecated Page title/subtitle are rendered globally via GeneralPageHeader in AppPageContent.
 * Use Teleport to="#general-page-header-actions" for primary actions only.
 */
defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <div
    v-if="$slots['primary-action'] || $slots.actions"
    class="general-page-header__actions general-page-header__actions--legacy"
  >
    <slot name="actions" />
    <slot name="primary-action" />
  </div>
</template>
