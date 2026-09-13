import { useSessionStore } from '@/stores/useSessionStore'

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

export const returnToAdminAccount = async stopImpersonationRequest => {
  try {
    const response = await stopImpersonationRequest()
    if (response?.data?.token && response?.data?.user) {
      clearImpersonatorSession()
      applyUserSession({
        token: response.data.token,
        user: response.data.user,
      })

      return '/'
    }
  }
  catch (e) {
  }

  if (restoreImpersonatorSession())
    return '/'

  sessionStore().clearSession()

  return '/login'
}
