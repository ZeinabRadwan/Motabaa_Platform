import { createI18n } from 'vue-i18n'

const messages = Object.fromEntries(Object.entries(
  import.meta.glob('./locales/{ar,en}.json', { eager: true }))
  .map(([key, value]) => [key.slice(10, -5), value.default]))

export default createI18n({
  legacy: false,
  locale: localStorage.getItem('getLocale') ?? 'ar',
  fallbackLocale: localStorage.getItem('getLocale') ?? 'ar',
  missing: (lang, key) => key,
  silentTranslationWarn: true,
  fallbackValue: (key) => key, // Return the key itself as the translation
  messages,
})
