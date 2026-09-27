<script setup>
defineOptions({ inheritAttrs: false })
</script>

<template>
  <VCardText
    class="app-list-toolbar d-flex flex-wrap gap-4"
    v-bind="$attrs"
  >
    <div
      v-if="$slots.leading"
      class="app-list-toolbar__leading"
    >
      <slot name="leading" />
    </div>
    <VSpacer />
    <div class="app-list-toolbar__actions app-user-search-filter d-flex align-center flex-wrap gap-4">
      <slot />
    </div>
  </VCardText>
</template>
