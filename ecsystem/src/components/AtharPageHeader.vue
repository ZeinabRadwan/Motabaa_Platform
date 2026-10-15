<script setup>
/**
 * @deprecated Page title/subtitle are rendered globally via GeneralPageHeader in AppPageContent.
 * Use PageHeaderActions for primary actions in the page header.
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
