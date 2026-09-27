<script setup>
import { injectionKeyToggleOverlayNav } from '@layouts'
import { useThemeConfig } from '@core/composable/useThemeConfig'

const toggleOverlayNav = inject(injectionKeyToggleOverlayNav, null)
const { isLessThanOverlayNavBreakpoint } = useThemeConfig()
const { width: windowWidth } = useWindowSize()

const showToggle = computed(() => (
  !!toggleOverlayNav
  && isLessThanOverlayNavBreakpoint.value(windowWidth.value)
))
</script>

<template>
  <div
    v-if="showToggle"
    class="sidebar-mobile-toggle"
  >
    <button
      type="button"
      class="sidebar-mobile-toggle__btn"
      aria-label="Open menu"
      @click="toggleOverlayNav(true)"
    >
      <VIcon
        icon="tabler-menu-2"
        size="22"
      />
    </button>
  </div>
</template>

<style lang="scss" scoped>
.sidebar-mobile-toggle {
  margin-block-end: 0.75rem;
}

.sidebar-mobile-toggle__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  inline-size: 2.5rem;
  block-size: 2.5rem;
  border: 1px solid rgba(31, 45, 61, 0.1);
  border-radius: 10px;
  background: rgb(var(--v-theme-surface));
  color: #087ed9;
  cursor: pointer;
  box-shadow: 0 1px 4px rgba(31, 45, 61, 0.06);

  &:hover {
    background: #eef7ff;
  }
}
</style>
