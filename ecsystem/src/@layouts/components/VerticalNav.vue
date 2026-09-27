<script setup>
import {
injectionKeyIsVerticalNavHovered,
useLayouts,
} from '@layouts'
import {
VerticalNavGroup,
VerticalNavLink,
VerticalNavSectionTitle,
} from '@layouts/components'
import { config } from '@layouts/config'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'
import { useSessionStore } from '@/stores/useSessionStore'
import VerticalNavPreferences from '@/layouts/components/VerticalNavPreferences.vue'
import VerticalNavUserFooter from '@/layouts/components/VerticalNavUserFooter.vue'
import { resolveSidebarLogoSrc } from '@/utils/branding'

const sessionStore = useSessionStore()

const props = defineProps({
  tag: {
    type: [
      String,
      null,
    ],
    required: false,
    default: 'aside',
  },
  navItems: {
    type: null,
    required: true,
  },
  isOverlayNavActive: {
    type: Boolean,
    required: true,
  },
  toggleIsOverlayNavActive: {
    type: Function,
    required: true,
  },
})

const refNav = ref()
const { width: windowWidth } = useWindowSize()
const isHovered = useElementHover(refNav)

provide(injectionKeyIsVerticalNavHovered, isHovered)

const {
  isVerticalNavCollapsed: isCollapsed,
  isLessThanOverlayNavBreakpoint,
  isAppRtl,
} = useLayouts()

const sidebarLogoSrc = computed(() => resolveSidebarLogoSrc(sessionStore.centerData, sessionStore.userData))
const showSidebarUserFooter = computed(() => !!sessionStore.userData?.id)

const resolveNavItemComponent = item => {
  if ('heading' in item)
    return VerticalNavSectionTitle
  if ('children' in item)
    return VerticalNavGroup
  
  return VerticalNavLink
}

const route = useRoute()

watch(() => route.name, () => {
  props.toggleIsOverlayNavActive(false)
})

const isVerticalNavScrolled = ref(false)
const updateIsVerticalNavScrolled = val => isVerticalNavScrolled.value = val

const handleNavScroll = evt => {
  isVerticalNavScrolled.value = evt.target.scrollTop > 0
}
</script>

<template>
  <Component
    :is="props.tag"
    ref="refNav"
    class="layout-vertical-nav"
    :class="[
      {
        'overlay-nav': isLessThanOverlayNavBreakpoint(windowWidth),
        'hovered': isHovered,
        'visible': isOverlayNavActive,
        'scrolled': isVerticalNavScrolled,
      },
    ]"
  >
    <!-- 👉 Header -->
    <div class="nav-header athar-nav-header">
      <slot name="nav-header">
        <div class="athar-nav-header__row">
          <RouterLink
            to="/"
            class="app-logo athar-nav-logo-wrap d-flex align-center app-title-wrapper"
          >
            <img
              :src="sidebarLogoSrc"
              alt="Athar"
              class="athar-nav-logo"
            >
          </RouterLink>
          <template v-if="!isLessThanOverlayNavBreakpoint(windowWidth)">
            <button
              type="button"
              class="header-action athar-nav-collapse"
              :aria-label="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
              @click="isCollapsed = !isCollapsed"
            >
              <Component
                :is="config.app.iconRenderer || 'div'"
                v-bind="isCollapsed ? config.icons.verticalNavUnPinned : config.icons.verticalNavPinned"
              />
            </button>
          </template>
          <template v-else>
            <button
              type="button"
              class="header-action athar-nav-collapse"
              aria-label="Close menu"
              @click="toggleIsOverlayNavActive(false)"
            >
              <Component
                :is="config.app.iconRenderer || 'div'"
                v-bind="config.icons.close"
              />
            </button>
          </template>
        </div>
      </slot>
    </div>
    <div
      class="athar-nav-header-divider"
      aria-hidden="true"
    />
    <slot name="before-nav-items">
      <div class="vertical-nav-items-shadow" />
    </slot>
    <slot
      name="nav-items"
      :update-is-vertical-nav-scrolled="updateIsVerticalNavScrolled"
    >
      <PerfectScrollbar
        :key="`${isAppRtl}-${sessionStore.contextKey}`"
        tag="ul"
        class="nav-items"
        :options="{ wheelPropagation: false }"
        @ps-scroll-y="handleNavScroll"
      >
        <Component
          :is="resolveNavItemComponent(item)"
          v-for="(item, index) in navItems"
          :key="item.identifier || item.title || index"
          :item="item"
        />
      </PerfectScrollbar>
    </slot>

    <div
      v-if="showSidebarUserFooter"
      class="athar-nav-footer"
    >
      <VerticalNavPreferences />
      <VerticalNavUserFooter />
    </div>
  </Component>
</template>

<style lang="scss">
@use "@configured-variables" as variables;
@use "@layouts/styles/mixins";

// 👉 Vertical Nav
.layout-vertical-nav {
  position: fixed;
  z-index: variables.$layout-vertical-nav-z-index;
  display: flex;
  flex-direction: column;
  block-size: 100%;
  inline-size: variables.$layout-vertical-nav-width;
  inset-block-start: 0;
  inset-inline-start: 0;
  transition: transform 0.25s ease-in-out, inline-size 0.25s ease-in-out, box-shadow 0.25s ease-in-out;
  will-change: transform, inline-size;

  .nav-header {
    display: flex;
    align-items: center;

    .header-action {
      cursor: pointer;
    }
  }

  .app-title-wrapper {
    margin-inline-end: auto;
  }

  .nav-items {
    block-size: 100%;

    // ℹ️ We no loner needs this overflow styles as perfect scrollbar applies it
    // overflow-x: hidden;

    // // ℹ️ We used `overflow-y` instead of `overflow` to mitigate overflow x. Revert back if any issue found.
    // overflow-y: auto;
  }

  .nav-item-title {
    overflow: hidden;
    margin-inline-end: auto;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  // 👉 Collapsed
  .layout-vertical-nav-collapsed & {
    &:not(.hovered) {
      inline-size: variables.$layout-vertical-nav-collapsed-width;
    }
  }

  // 👉 Overlay nav
  &.overlay-nav {
    &:not(.visible) {
      transform: translateX(-#{variables.$layout-vertical-nav-width});

      @include mixins.rtl {
        transform: translateX(variables.$layout-vertical-nav-width);
      }
    }
  }
}
</style>
