<script setup>
const shortcuts = [
  {
    icon: 'tabler-smart-home',
    title: 'Dashboard',
    subtitle: 'Dashboard Analytics',
    to: { name: 'dashboards-analytics' },
  },
  {
    icon: 'tabler-mood-boy',
    title: 'Cases',
    subtitle: 'Show Cases',
    to: { name: 'cases-list' },
  },
  {
    icon: 'tabler-users',
    title: 'Staff',
    subtitle: 'Career staff',
    to: { name: 'user-list' },
  },
  {
    icon: 'tabler-users-group',
    title: 'Parents',
    subtitle: 'Manage Parents',
    to: { name: 'parents-list' },
  },
  {
    icon: 'tabler-building-skyscraper',
    title: 'Centers',
    subtitle: 'System settings',
    to: { name: 'centers-list' },
  },
  {
    icon: 'tabler-settings',
    title: 'Roles',
    subtitle: 'Roles & Permissions',
    to: { name: 'roles-list' },
  },
]
</script>

<template>
  <Shortcuts :shortcuts="shortcuts" />
</template>
