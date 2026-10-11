<script setup>
import { isDarkPreferred, useThemeConfig } from '@core/composable/useThemeConfig'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { theme } = useThemeConfig()

const themes = [
  { name: 'light', icon: 'tabler-sun' },
  { name: 'dark', icon: 'tabler-moon' },
]

const activeTheme = computed(() => {
  if (theme.value === 'system')
    return isDarkPreferred.value ? 'dark' : 'light'

  return theme.value === 'dark' ? 'dark' : 'light'
})

const triggerIcon = computed(() => (activeTheme.value === 'dark' ? 'tabler-moon' : 'tabler-sun'))

const triggerLabel = computed(() => (
  activeTheme.value === 'dark' ? t('navbar.theme_dark') : t('navbar.theme_light')
))

function selectTheme(name) {
  theme.value = name
}
</script>

<template>
  <VMenu
    location="bottom end"
    offset="8"
    :close-on-content-click="true"
  >
    <template #activator="{ props: menuProps }">
      <button
        type="button"
        class="navbar-pref-btn"
        v-bind="menuProps"
        :aria-label="t('navbar.appearance')"
      >
        <VIcon
          :icon="triggerIcon"
          size="20"
        />
        <span class="navbar-pref-btn__label">{{ triggerLabel }}</span>
      </button>
    </template>

    <VList
      class="navbar-pref-menu"
      density="compact"
      nav
    >
      <div class="navbar-pref-menu__header">
        {{ t('navbar.appearance') }}
      </div>

      <VListItem
        v-for="item in themes"
        :key="item.name"
        class="navbar-pref-menu__item"
        :class="{ 'navbar-pref-menu__item--active': activeTheme === item.name }"
        @click="selectTheme(item.name)"
      >
        <VListItemTitle>
          <VIcon
            :icon="item.icon"
            size="18"
            class="navbar-pref-menu__item-icon"
          />
          <span>{{ item.name === 'light' ? t('navbar.theme_light') : t('navbar.theme_dark') }}</span>
          <VIcon
            v-if="activeTheme === item.name"
            icon="tabler-check"
            size="18"
            class="navbar-pref-menu__check"
          />
        </VListItemTitle>
      </VListItem>
    </VList>
  </VMenu>
</template>

<style lang="scss">
@import '@/styles/navbar-preferences.scss';
</style>
