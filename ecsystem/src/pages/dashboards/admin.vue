<script setup>
import { can } from '@layouts/plugins/casl'
import { useSessionStore } from '@/stores/useSessionStore'

const sessionStore = useSessionStore()

const welcomeTitle = computed(() => {
  const name = sessionStore.userData?.name

  return name
    ? { key: 'dashboard_welcome', values: { name } }
    : { key: 'dashboard_welcome_guest', values: {} }
})

const shortcuts = computed(() => [
  {
    title: 'Centers',
    subtitle: 'shortcut_centers',
    to: { name: 'centers-list' },
    icon: 'tabler-building-community',
    show: can('access_centers', 'access_centers'),
  },
  {
    title: 'centers.payments',
    subtitle: 'shortcut_payments',
    to: { name: 'centers-payments-list' },
    icon: 'tabler-credit-card',
    show: can('access_centers', 'access_centers'),
  },
  {
    title: 'centers.users',
    subtitle: 'shortcut_users',
    to: { name: 'centers-users-list' },
    icon: 'tabler-users',
    show: can('access_centers', 'access_centers'),
  },
  {
    title: 'Roles & Permissions',
    subtitle: 'shortcut_roles',
    to: { name: 'roles-list' },
    icon: 'tabler-shield-lock',
    show: can('access_roles', 'access_roles'),
  },
].filter(item => item.show))
</script>

<template>
  <section>
    <div class="page-header">
      <div>
        <h1 class="page-header__title">
          {{ $t(welcomeTitle.key, welcomeTitle.values) }}
        </h1>
        <p class="page-header__subtitle">
          {{ $t('dashboard_welcome_subtitle') }}
        </p>
      </div>
    </div>

    <div class="shortcut-grid">
      <RouterLink
        v-for="item in shortcuts"
        :key="item.subtitle"
        :to="item.to"
        class="shortcut-card"
      >
        <div class="shortcut-card__icon">
          <VIcon :icon="item.icon" />
        </div>
        <div>
          <p class="shortcut-card__title">
            {{ $t(item.title) }}
          </p>
          <p class="shortcut-card__subtitle">
            {{ $t(item.subtitle) }}
          </p>
        </div>
      </RouterLink>
    </div>
  </section>
</template>

<route lang="yaml">
  meta:
    action: all-users
    subject: Auth
</route>
