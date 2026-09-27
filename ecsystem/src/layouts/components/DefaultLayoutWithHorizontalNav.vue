<script setup>
import { buildHorizontalNav } from '@/navigation/horizontal'
import { useSessionStore } from '@/stores/useSessionStore'
import { useThemeConfig } from '@core/composable/useThemeConfig'
import { themeConfig } from '@themeConfig'

// Components
import Footer from '@/layouts/components/Footer.vue'
import NavBarI18n from '@/layouts/components/NavBarI18n.vue'
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import NavbarShortcuts from '@/layouts/components/NavbarShortcuts.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import NavSearchBar from '@/layouts/components/NavSearchBar.vue'
import ImpersonationBanner from '@/layouts/components/ImpersonationBanner.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import { HorizontalNavLayout } from '@layouts'
import { resolveSidebarLogoSrc, sidebarLogoWidth } from '@/utils/branding'

const TheCustomizer = defineAsyncComponent(() => import('@core/components/TheCustomizer.vue'))

const { appRouteTransition } = useThemeConfig()
const route = useRoute()
const sessionStore = useSessionStore()
const navItems = computed(() => buildHorizontalNav(sessionStore.userData, sessionStore.center))
const pageKey = computed(() => `${sessionStore.userData?.id || 'anon'}:${sessionStore.center || 'none'}:${route.path}`)
const sidebarLogoSrc = computed(() => resolveSidebarLogoSrc(sessionStore.centerData, sessionStore.userData))
const sidebarLogoSize = computed(() => sidebarLogoWidth(sessionStore.userData))
</script>

<template>
  <HorizontalNavLayout :nav-items="navItems">
    <!-- 👉 navbar -->
    <template #navbar>
      <RouterLink
        to="/"
        class="app-logo d-flex align-center gap-x-3"
      >
        <img
          :src="sidebarLogoSrc"
          alt=""
          :style="{ lineHeight: 0, width: sidebarLogoSize }"
        >

        <h1 class="app-title font-weight-bold leading-normal text-xl text-capitalize">
          {{ $t(themeConfig.app.title) }}
        </h1>
      </RouterLink>
      <VSpacer />

      <NavSearchBar trigger-btn-class="ms-lg-n3" />

      <NavBarI18n class="me-1" />
      <NavbarThemeSwitcher class="me-1" />
      <NavbarShortcuts class="me-1" />
      <NavBarNotifications class="me-2" />
      <ImpersonationBanner />
      <UserProfile />
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
  </HorizontalNavLayout>
</template>
