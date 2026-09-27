<script setup>
import { buildVerticalNav } from '@/navigation/vertical'
import { useSessionStore } from '@/stores/useSessionStore'
import { useThemeConfig } from '@core/composable/useThemeConfig'

// Components
import Footer from '@/layouts/components/Footer.vue'
import NavBarI18n from '@/layouts/components/NavBarI18n.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import NavSearchBar from '@/layouts/components/NavSearchBar.vue'
import ImpersonationBanner from '@/layouts/components/ImpersonationBanner.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'

// @layouts plugin
import { VerticalNavLayout } from '@layouts'

const TheCustomizer = defineAsyncComponent(() => import('@core/components/TheCustomizer.vue'))

const { appRouteTransition, isLessThanOverlayNavBreakpoint } = useThemeConfig()
const { width: windowWidth } = useWindowSize()
const route = useRoute()
const sessionStore = useSessionStore()
const navItems = computed(() => buildVerticalNav(sessionStore.userData, sessionStore.center, sessionStore.userAbilities))
const pageKey = computed(() => `${sessionStore.userData?.id || 'anon'}:${sessionStore.center || 'none'}:${route.path}`)
</script>

<template>
  <VerticalNavLayout :nav-items="navItems">
    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          v-if="isLessThanOverlayNavBreakpoint(windowWidth)"
          id="vertical-nav-toggle-btn"
          class="ms-n3"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            size="26"
            icon="tabler-menu-2"
          />
        </IconBtn>

        <NavSearchBar class="ms-lg-n3" />

        <VSpacer />

        <NavBarI18n class="me-1" />
        <NavbarThemeSwitcher class="me-1" />
        <!-- <NavbarShortcuts class="me-1" /> -->
        <!-- <NavBarNotifications class="me-2" /> -->
        <ImpersonationBanner />
        <UserProfile />
      </div>
    </template>

    <!-- 👉 Pages -->
    <RouterView v-slot="{ Component }">
      <Transition
        :name="appRouteTransition"
        mode="out-in"
      >
        <Component :is="Component" :key="pageKey" />
      </Transition>
    </RouterView>

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <TheCustomizer />
  </VerticalNavLayout>
</template>
