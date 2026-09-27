<script setup>
import { useSessionStore } from '@/stores/useSessionStore'
import { resolveUserGivenName } from '@/utils/resolveUserGivenName'

const sessionStore = useSessionStore()

const givenName = computed(() => resolveUserGivenName(sessionStore.userData))
</script>

<template>
  <header
    class="athar-dashboard-welcome"
    :aria-label="$t('dashboard.welcome_aria')"
  >
    <h1 class="athar-dashboard-welcome__title">
      {{ $t('dashboard.welcome_greeting', { name: givenName }) }}
    </h1>
    <p class="athar-dashboard-welcome__subtitle">
      {{ $t('dashboard.welcome_subtitle') }}
    </p>
  </header>
</template>

<style lang="scss" scoped>
@import '@/styles/dashboard-welcome.scss';
</style>
