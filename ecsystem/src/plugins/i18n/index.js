import { createI18n } from 'vue-i18n'
import ar from './locales/ar.json'

const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('getLocale') ?? 'ar',
  fallbackLocale: 'ar',
  missing: (lang, key) => key,
  silentTranslationWarn: true,
  fallbackValue: key => key,
  messages: {
    ar,
  },
})

export default i18n
