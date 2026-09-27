<script setup>
import { isDarkPreferred, useThemeConfig } from '@core/composable/useThemeConfig'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n({ useScope: 'global' })
const { theme, isAppRtl } = useThemeConfig()

const activeTheme = computed(() => {
  if (theme.value === 'system')
    return isDarkPreferred.value ? 'dark' : 'light'

  return theme.value === 'dark' ? 'dark' : 'light'
})

const themeIcon = computed(() => (activeTheme.value === 'dark' ? 'tabler-moon' : 'tabler-sun'))

const themeTooltip = computed(() => (
  activeTheme.value === 'light'
    ? t('navbar.toggle_theme_dark')
    : t('navbar.toggle_theme_light')
))

const languageTooltip = computed(() => (
  locale.value === 'ar'
    ? t('navbar.toggle_lang_en')
    : t('navbar.toggle_lang_ar')
))

function toggleTheme() {
  theme.value = activeTheme.value === 'dark' ? 'light' : 'dark'
}

function toggleLanguage() {
  const next = locale.value === 'ar' ? 'en' : 'ar'

  locale.value = next
  isAppRtl.value = next === 'ar'
  document.documentElement.setAttribute('lang', next)
  localStorage.setItem('getLocale', next)
}

watch(locale, val => {
  document.documentElement.setAttribute('lang', val)
})
</script>

<template>
  <div class="athar-nav-prefs">
    <div
      class="athar-nav-prefs__toggles"
      role="group"
      :aria-label="t('navbar.appearance')"
    >
      <VTooltip
        location="top"
        open-delay="200"
      >
        <template #activator="{ props: tipProps }">
          <button
            type="button"
            class="athar-nav-prefs__toggle athar-nav-prefs__toggle--active"
            v-bind="tipProps"
            :aria-label="themeTooltip"
            :title="themeTooltip"
            @click="toggleTheme"
          >
            <VIcon
              :icon="themeIcon"
              size="20"
            />
          </button>
        </template>
        <span>{{ themeTooltip }}</span>
      </VTooltip>

      <VTooltip
        location="top"
        open-delay="200"
      >
        <template #activator="{ props: tipProps }">
          <button
            type="button"
            class="athar-nav-prefs__toggle"
            v-bind="tipProps"
            :aria-label="languageTooltip"
            :title="languageTooltip"
            @click="toggleLanguage"
          >
            <VIcon
              icon="tabler-language"
              size="20"
            />
          </button>
        </template>
        <span>{{ languageTooltip }}</span>
      </VTooltip>
    </div>
  </div>
</template>
