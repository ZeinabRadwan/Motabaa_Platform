<script setup>
import { injectionKeyOverlayNavActive, injectionKeyToggleOverlayNav } from '@layouts'
import { useThemeConfig } from '@core/composable/useThemeConfig'
import i18n from '@/plugins/i18n/index.js'

const toggleOverlayNav = inject(injectionKeyToggleOverlayNav, null)
const overlayNavActive = inject(injectionKeyOverlayNavActive, ref(false))
const { isLessThanOverlayNavBreakpoint } = useThemeConfig()
const { width: windowWidth } = useWindowSize()

const showToggle = computed(() => (
  !!toggleOverlayNav
  && isLessThanOverlayNavBreakpoint.value(windowWidth.value)
))

const isOpen = computed(() => Boolean(unref(overlayNavActive)))

const ariaLabel = computed(() => (
  isOpen.value
    ? i18n.global.t('layout.close_sidebar')
    : i18n.global.t('layout.open_sidebar')
))

const onToggle = () => {
  if (! toggleOverlayNav)
    return
  toggleOverlayNav(! isOpen.value)
}
</script>

<template>
  <div
    v-if="showToggle"
    class="sidebar-mobile-toggle"
  >
    <button
      type="button"
      class="sidebar-mobile-toggle__btn"
      :aria-label="ariaLabel"
      :title="ariaLabel"
      :aria-expanded="isOpen"
      @click="onToggle"
    >
      <svg
        v-if="!isOpen"
        class="sidebar-mobile-toggle__icon"
        viewBox="0 0 24 24"
        width="22"
        height="22"
        aria-hidden="true"
        focusable="false"
      >
        <path
          fill="currentColor"
          d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h16v2H4v-2z"
        />
      </svg>
      <svg
        v-else
        class="sidebar-mobile-toggle__icon"
        viewBox="0 0 24 24"
        width="22"
        height="22"
        aria-hidden="true"
        focusable="false"
      >
        <path
          fill="currentColor"
          d="M6.4 5.4 5 6.8l5.6 5.6-5.6 5.6 1.4 1.4 5.6-5.6 5.6 5.6 1.4-1.4-5.6-5.6 5.6-5.6-1.4-1.4-5.6 5.6-5.6-5.6z"
        />
      </svg>
    </button>
  </div>
</template>

<style lang="scss" scoped>
.sidebar-mobile-toggle {
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}

.sidebar-mobile-toggle__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  inline-size: 2.75rem;
  block-size: 2.75rem;
  padding: 0;
  border: 1px solid rgba(8, 127, 217, 0.45);
  border-radius: 10px;
  background-color: #fff;
  background-color: rgb(var(--v-theme-surface));
  color: #087ed9;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(8, 127, 217, 0.18);
  opacity: 1;
  visibility: visible;
  transition: background 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;

  &:hover {
    background: #eef7ff;
    border-color: rgba(8, 127, 217, 0.55);
  }

  &:focus-visible {
    outline: 2px solid #087ed9;
    outline-offset: 2px;
  }
}

.sidebar-mobile-toggle__icon {
  display: block;
  flex-shrink: 0;
}

.v-theme--dark .sidebar-mobile-toggle__btn {
  border-color: rgba(77, 168, 255, 0.45);
  color: #4da8ff;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25);

  &:hover {
    background: rgba(77, 168, 255, 0.12);
  }
}
</style>
