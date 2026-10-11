<script setup>
import { buildVerticalNav } from '@/navigation/vertical'
import { useSessionStore } from '@/stores/useSessionStore'
import { useThemeConfig } from '@core/composable/useThemeConfig'

// Components
import AppPageContent from '@/layouts/components/AppPageContent.vue'
import Footer from '@/layouts/components/Footer.vue'
// @layouts plugin
import { VerticalNavLayout } from '@layouts'

const { appRouteTransition } = useThemeConfig()
const route = useRoute()
const sessionStore = useSessionStore()
const navItems = computed(() => buildVerticalNav(sessionStore.userData, sessionStore.center, sessionStore.userAbilities))
const pageKey = computed(() => `${sessionStore.userData?.id || 'anon'}:${sessionStore.center || 'none'}:${route.path}`)
</script>

<template>
  <VerticalNavLayout :nav-items="navItems">
    <!-- Navbar hidden via themeConfig (NavbarType.Hidden) -->

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

  </VerticalNavLayout>
</template>
