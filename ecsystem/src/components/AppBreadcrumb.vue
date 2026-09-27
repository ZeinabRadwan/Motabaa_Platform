<script setup>
import { useAppBreadcrumb } from '@/composables/useAppBreadcrumb'

/**
 * Global breadcrumb — pass `items` to override automatic route-based trail.
 * @example
 * <AppBreadcrumb :items="[{ label: '…', to: '/' }, { label: '…', disabled: true }]" />
 */
const props = defineProps({
  items: {
    type: Array,
    default: null,
  },
})

const { items: routeItems, visible: routeVisible } = useAppBreadcrumb()

const resolvedItems = computed(() => {
  if (Array.isArray(props.items))
    return props.items

  return routeItems.value
})

const isVisible = computed(() => {
  if (Array.isArray(props.items))
    return props.items.length > 0

  return routeVisible.value
})
</script>

<template>
  <nav
    v-if="isVisible"
    class="app-breadcrumb"
    aria-label="Breadcrumb"
  >
    <ol class="app-breadcrumb__list">
      <li
        v-for="(item, index) in resolvedItems"
        :key="`${item.label}-${index}`"
        class="app-breadcrumb__item"
      >
        <RouterLink
          v-if="item.to && !item.disabled"
          :to="item.to"
          class="app-breadcrumb__link"
        >
          {{ item.label }}
        </RouterLink>
        <span
          v-else-if="item.disabled"
          class="app-breadcrumb__current"
          aria-current="page"
        >
          {{ item.label }}
        </span>
        <span
          v-else
          class="app-breadcrumb__muted"
        >
          {{ item.label }}
        </span>
        <span
          v-if="index < resolvedItems.length - 1"
          class="app-breadcrumb__sep"
          aria-hidden="true"
        >/</span>
      </li>
    </ol>
  </nav>
</template>

<style lang="scss">
@import '@/styles/app-breadcrumb.scss';
</style>
