import { useSessionStore } from '@/stores/useSessionStore'
import { clearAccountScopedStorage, resetAccountScopedState } from '@core/utils/accountScope'
import { markSessionAliveInTab } from '@core/utils/authStorage'
import { clearReferenceCache } from '@core/utils/referenceCache'

export const IMPERSONATOR_SESSION_KEY = 'impersonatorSession'
export const IMPERSONATION_RESTORE_FLAG = 'impersonationRestoreAttempted'

const sessionStore = () => useSessionStore()

export const getImpersonatorSession = () => {
  try {
    const raw = localStorage.getItem(IMPERSONATOR_SESSION_KEY)

    return raw ? JSON.parse(raw) : null
  }
  catch (e) {
    return null
  }
}

export const isImpersonating = () => !!getImpersonatorSession()

export const saveImpersonatorSession = (extra = {}) => {
  const snapshot = {
    accessToken: localStorage.getItem('accessToken'),
    userData: localStorage.getItem('userData'),
    userAbilities: localStorage.getItem('userAbilities'),
    center: localStorage.getItem('center'),
    center_data: localStorage.getItem('center_data'),
    user_centers: localStorage.getItem('user_centers'),
    ...extra,
  }

  localStorage.setItem(IMPERSONATOR_SESSION_KEY, JSON.stringify(snapshot))
  sessionStorage.removeItem(IMPERSONATION_RESTORE_FLAG)
  sessionStore().setImpersonating(true)
}

export const restoreImpersonatorSession = () => {
  const snapshot = getImpersonatorSession()
  if (!snapshot?.accessToken || !snapshot?.userData)
    return false

  resetAccountScopedState()
  clearAccountScopedStorage()
  clearReferenceCache()

  localStorage.setItem('accessToken', snapshot.accessToken)
  localStorage.setItem('userData', snapshot.userData)
  localStorage.setItem('userAbilities', snapshot.userAbilities || '[]')
  localStorage.setItem('center', snapshot.center || '')
  localStorage.setItem('center_data', snapshot.center_data || '')
  if (snapshot.user_centers)
    localStorage.setItem('user_centers', snapshot.user_centers)
  else
    localStorage.removeItem('user_centers')

  localStorage.removeItem(IMPERSONATOR_SESSION_KEY)
  sessionStore().hydrateFromStorage()

  return true
}

export const clearImpersonatorSession = () => {
  localStorage.removeItem(IMPERSONATOR_SESSION_KEY)
  sessionStorage.removeItem(IMPERSONATION_RESTORE_FLAG)
  sessionStore().setImpersonating(false)
}

export const applyUserSession = ({ token, user, remember, email } = {}) => {
  sessionStore().applyUser({ token, user, remember, email })
}

export const isValidSessionPayload = data => !!(
  data
  && typeof data.token === 'string'
  && data.token.trim() !== ''
  && data.user
  && typeof data.user === 'object'
  && data.user.id != null
)

const sessionPersistedFor = (token, userId) => {
  try {
    const stored = JSON.parse(localStorage.getItem('userData') || 'null')

    return localStorage.getItem('accessToken') === token
      && !!stored
      && String(stored.id) === String(userId)
  }
  catch (e) {
    return false
  }
}

/** Full document load of the SPA so no module-level / Pinia / component state survives. */
export const reloadApp = (path = '/') => {
  window.location.replace(new URL(path, window.location.origin).href)
}

/**
 * The only way to swap identities inside a tab (impersonate / return to admin).
 * Persists the new session, drops all account-scoped state, then hard-reloads.
 *
 * @param {{ token: string, user: object, mode: 'impersonate'|'return', impersonatedUserName?: string }} options
 */
export const switchAccount = ({ token, user, mode, impersonatedUserName } = {}) => {
  if (!isValidSessionPayload({ token, user }))
    throw new Error('invalid_session_payload')

  if (mode === 'impersonate') {
    saveImpersonatorSession({ impersonatedUserName: impersonatedUserName || user.name || '' })
    if (!getImpersonatorSession()?.accessToken) {
      clearImpersonatorSession()
      throw new Error('impersonator_session_not_saved')
    }
  }

  try {
    applyUserSession({ token, user })

    if (!sessionPersistedFor(token, user.id))
      throw new Error('session_not_persisted')

    if (mode === 'impersonate' && !getImpersonatorSession()?.accessToken)
      throw new Error('impersonator_session_lost')
  }
  catch (e) {
    if (mode === 'impersonate')
      restoreImpersonatorSession()

    throw e
  }

  if (mode === 'return')
    clearImpersonatorSession()

  markSessionAliveInTab()
  reloadApp('/')
}

export const impersonateAccount = async (impersonateRequest, target = {}) => {
  const response = await impersonateRequest()
  if (!isValidSessionPayload(response?.data))
    throw new Error('invalid_impersonation_response')

  switchAccount({
    token: response.data.token,
    user: response.data.user,
    mode: 'impersonate',
    impersonatedUserName: target?.name,
  })
}

let returningToAdmin = false

export const returnToAdminAccount = async stopImpersonationRequest => {
  // A second stop request would 401 (token already revoked) and fall through to logout.
  if (returningToAdmin)
    return
  returningToAdmin = true

  let payload = null
  try {
    const response = await stopImpersonationRequest()

    payload = response?.data
  }
  catch (e) {
  }

  if (isValidSessionPayload(payload)) {
    try {
      switchAccount({ token: payload.token, user: payload.user, mode: 'return' })

      return
    }
    catch (e) {
    }
  }

  if (restoreImpersonatorSession()) {
    markSessionAliveInTab()
    reloadApp('/')

    return
  }

  clearImpersonatorSession()
  sessionStore().clearSession()
  reloadApp('/login')
}
