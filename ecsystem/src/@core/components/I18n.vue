<script setup>
const props = defineProps({
  languages: {
    type: Array,
    required: true,
  },
  location: {
    type: null,
    required: false,
    default: 'bottom end',
  },
})

defineEmits(['change'])

const { locale, t } = useI18n({ useScope: 'global' })

watch(locale, val => {
  document.documentElement.setAttribute('lang', val)
})

const currentLang = computed({
  get() {
    return locale.value
  },
  set(val) {
    locale.value = val
  },
})

const currentLanguage = computed(() => props.languages.find(lang => lang.i18nLang === locale.value))

const currentLabel = computed(() => {
  if (currentLanguage.value?.labelKey)
    return t(currentLanguage.value.labelKey)

  return currentLanguage.value?.label || ''
})

function selectLanguage(lang) {
  currentLang.value = lang.i18nLang
  document.documentElement.setAttribute('lang', lang.i18nLang)
  localStorage.setItem('getLocale', lang.i18nLang)
}
</script>

<template>
  <VMenu
    :location="props.location"
    offset="8"
    :close-on-content-click="true"
  >
    <template #activator="{ props: menuProps }">
      <button
        type="button"
        class="navbar-pref-btn"
        v-bind="menuProps"
        :aria-label="t('navbar.language')"
      >
        <VIcon
          icon="tabler-world"
          size="20"
        />
        <span class="navbar-pref-btn__label">{{ currentLabel }}</span>
      </button>
    </template>

    <VList
      class="navbar-pref-menu"
      density="compact"
      nav
    >
      <div class="navbar-pref-menu__header">
        {{ t('navbar.language') }}
      </div>

      <VListItem
        v-for="lang in props.languages"
        :key="lang.i18nLang"
        class="navbar-pref-menu__item"
        :class="{ 'navbar-pref-menu__item--active': locale === lang.i18nLang }"
        @click="selectLanguage(lang); $emit('change', lang.i18nLang)"
      >
        <VListItemTitle>
          <VIcon
            icon="tabler-language"
            size="18"
            class="navbar-pref-menu__item-icon"
          />
          <span>{{ lang.labelKey ? t(lang.labelKey) : lang.label }}</span>
          <VIcon
            v-if="locale === lang.i18nLang"
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
