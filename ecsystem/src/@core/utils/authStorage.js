const REMEMBER_FLAG = 'rememberMe'
const REMEMBERED_EMAIL = 'rememberedEmail'
const SESSION_ONLY_KEY = 'sessionOnly'
const SESSION_ALIVE_KEY = 'sessionAlive'
const SESSION_KEYS = [
  'accessToken',
  'userData',
  'userAbilities',
  'center',
  'center_data',
  'user_centers',
]

export function isRememberMeEnabled() {
  return localStorage.getItem(REMEMBER_FLAG) === '1'
}

export function getRememberedEmail() {
  return localStorage.getItem(REMEMBERED_EMAIL) || ''
}

export function applyRememberMePreference(enabled, email = '') {
  if (enabled) {
    localStorage.setItem(REMEMBER_FLAG, '1')
    localStorage.removeItem(SESSION_ONLY_KEY)
    sessionStorage.removeItem(SESSION_ALIVE_KEY)
    if (email)
      localStorage.setItem(REMEMBERED_EMAIL, email)
  }
  else {
    localStorage.removeItem(REMEMBER_FLAG)
    localStorage.removeItem(REMEMBERED_EMAIL)
    localStorage.setItem(SESSION_ONLY_KEY, '1')
    sessionStorage.setItem(SESSION_ALIVE_KEY, '1')
  }
}

export function clearAuthSessionStorage() {
  SESSION_KEYS.forEach(key => localStorage.removeItem(key))
  localStorage.removeItem(SESSION_ONLY_KEY)
  sessionStorage.removeItem(SESSION_ALIVE_KEY)
}

export function purgeExpiredSession() {
  if (typeof window === 'undefined')
    return

  // New tabs do not inherit sessionStorage. A still-valid local session
  // (especially admin impersonating a parent) must not look like a closed browser.
  const hasToken = !!localStorage.getItem('accessToken')
  const impersonating = !!localStorage.getItem('impersonatorSession')
  if (hasToken && (impersonating || window.opener)) {
    sessionStorage.setItem(SESSION_ALIVE_KEY, '1')

    return
  }

  if (localStorage.getItem(SESSION_ONLY_KEY) === '1' && sessionStorage.getItem(SESSION_ALIVE_KEY) !== '1')
    clearAuthSessionStorage()
}

purgeExpiredSession()
