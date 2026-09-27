<script setup>
import { buildHorizontalNav } from '@/navigation/horizontal'
import { useSessionStore } from '@/stores/useSessionStore'
import { useThemeConfig } from '@core/composable/useThemeConfig'
import { themeConfig } from '@themeConfig'

// Components
import AppPageContent from '@/layouts/components/AppPageContent.vue'
import Footer from '@/layouts/components/Footer.vue'
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import NavbarShortcuts from '@/layouts/components/NavbarShortcuts.vue'
import NavSearchBar from '@/layouts/components/NavSearchBar.vue'
import { HorizontalNavLayout } from '@layouts'
import { resolveSidebarLogoSrc, sidebarLogoWidth } from '@/utils/branding'

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

      <NavbarShortcuts class="me-1" />
      <NavBarNotifications class="me-2" />
    </template>

    <!-- 👉 Pages -->
    <AppPageContent>
      <RouterView v-slot="{ Component }">
        <Transition
          :name="appRouteTransition"
          mode="out-in"
        >
          <Component :is="Component" :key="pageKey" />
        </Transition>
      </RouterView>
    </AppPageContent>

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

  </HorizontalNavLayout>
</template>
