<script setup>
defineProps({
  // [{ label, value, ltr?: boolean }] — empty values render as '—'.
  items: { type: Array, required: true },
})
</script>

<template>
  <VRow class="hr-info-grid">
    <VCol
      v-for="item in items"
      :key="item.label"
      cols="12"
      sm="6"
      md="4"
    >
      <div class="hr-page__muted">
        {{ item.label }}
      </div>
      <div
        class="font-weight-medium"
        :dir="item.ltr ? 'ltr' : undefined"
        :class="{ 'text-start': item.ltr }"
      >
        {{ item.value === null || item.value === undefined || item.value === '' ? '—' : item.value }}
      </div>
    </VCol>
  </VRow>
</template>
