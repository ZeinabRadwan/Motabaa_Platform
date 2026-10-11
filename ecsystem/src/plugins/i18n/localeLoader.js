import i18n from './index.js'

let enLoadPromise = null

/** Load English messages on demand (Arabic is bundled at startup). */
export function ensureLocale(locale) {
  if (locale !== 'en')
    return Promise.resolve()

  if (i18n.global.availableLocales.includes('en'))
    return Promise.resolve()

  if (!enLoadPromise) {
    enLoadPromise = import('./locales/en.json').then(mod => {
      i18n.global.setLocaleMessage('en', mod.default)
    })
  }

  return enLoadPromise
}

export function preloadSecondaryLocale() {
  if (typeof window === 'undefined')
    return

  const initial = localStorage.getItem('getLocale') ?? 'ar'
  if (initial === 'en') {
    ensureLocale('en')

    return
  }

  const idle = window.requestIdleCallback || (cb => setTimeout(cb, 2500))

  idle(() => {
    ensureLocale('en').catch(() => {})
  })
}
