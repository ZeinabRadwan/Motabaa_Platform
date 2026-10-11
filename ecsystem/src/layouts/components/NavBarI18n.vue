<script setup>
import NavBarI18n from '@core/components/I18n.vue'
import { useThemeConfig } from '@core/composable/useThemeConfig'

const { isAppRtl } = useThemeConfig()

const i18nCompLanguages = [
  {
    labelKey: 'navbar.lang_ar',
    i18nLang: 'ar',
  },
  {
    labelKey: 'navbar.lang_en',
    i18nLang: 'en',
  },
]

const handleLangChange = lang => {
  isAppRtl.value = lang === 'ar'
  document.documentElement.setAttribute('lang', lang)
  localStorage.setItem('getLocale', lang)
}
</script>

<template>
  <NavBarI18n
    :languages="i18nCompLanguages"
    @change="handleLangChange"
  />
</template>
