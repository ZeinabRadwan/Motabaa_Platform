<script setup>
/**
 * Registers default slot content into AppPageContent's header actions area
 * (avoids Teleport + RouterView transition issues on hard refresh, Vue 3.2).
 */
import { inject, nextTick, onBeforeUnmount, onMounted, onUpdated, useSlots, watch } from 'vue'

const register = inject('registerGeneralPageHeaderActions', null)
const unregister = inject('unregisterGeneralPageHeaderActions', null)
const slots = useSlots()

const sync = async () => {
  await nextTick()
  if (! register)
    return

  const content = slots.default?.()
  if (! content?.length) {
    unregister?.()

    return
  }

  register({
    name: 'PageHeaderActionsSlot',
    render: () => slots.default?.() ?? [],
  })
}

onMounted(sync)
onUpdated(sync)
watch(() => slots.default?.(), sync, { flush: 'post' })
onBeforeUnmount(() => unregister?.())
</script>

<template>
  <span
    class="page-header-actions-registrar"
    aria-hidden="true"
  />
</template>

<style scoped>
.page-header-actions-registrar {
  display: none !important;
}
</style>
