/* eslint-disable import/order */
import App from '@/App.vue'
import ability from '@/plugins/casl/ability'
import i18n from '@/plugins/i18n'
import layoutsPlugin from '@/plugins/layouts'
import vuetify from '@/plugins/vuetify'
import { loadFonts } from '@/plugins/webfontloader'
import router from '@/router'
import { abilitiesPlugin } from '@casl/vue'
import '@core/scss/template/index.scss'
import '@styles/styles.scss'
import { createPinia } from 'pinia'
import { createApp } from 'vue'
import DisableAutocomplete from 'vue-disable-autocomplete'
import { ensureLocale, preloadSecondaryLocale } from '@/plugins/i18n/localeLoader'
import { dismissInitialLoader } from '@/utils/dismissInitialLoader'


// Create vue app
const app = createApp(App)


//TODO: need to search to find way via vuejs (8/8/2032)
window.timeOutAfterSubmit = 2000

// app.config.globalProperties.$greet = "HEY YOU";

// window.i18n = i18n;
// Use plugins
app.use(vuetify)
app.use(createPinia())
app.use(router)
app.use(DisableAutocomplete)
app.use(layoutsPlugin)
app.use(i18n)
app.use(abilitiesPlugin, ability, {
  useGlobalProperties: true,
})

async function bootstrap() {
  const initialLocale = localStorage.getItem('getLocale') ?? 'ar'
  if (initialLocale === 'en')
    await ensureLocale('en')

  app.mount('#app')
  dismissInitialLoader()

  preloadSecondaryLocale()

  const idle = typeof window !== 'undefined' && window.requestIdleCallback
    ? window.requestIdleCallback
    : cb => setTimeout(cb, 1)

  idle(() => {
    loadFonts()
    import('@/@iconify/icons-bundle')
  })
}

bootstrap()
