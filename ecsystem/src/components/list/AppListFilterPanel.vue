<script setup>
defineOptions({ inheritAttrs: false })
</script>

<template>
  <VCardText
    class="app-list-filters"
    v-bind="$attrs"
  >
    <div class="app-list-filters__head">
      <VIcon
        icon="tabler-filter"
        size="18"
        class="app-list-filters__head-icon"
      />
      <span class="app-list-filters__head-title">
        {{ $t('Search Filter') }}
      </span>
    </div>
    <div class="app-list-filters__body">
      <slot />
    </div>
  </VCardText>
</template>
