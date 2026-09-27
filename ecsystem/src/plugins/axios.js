import i18n from '@/plugins/i18n/index.js'
import router from '@/router'
import { useSessionStore } from '@/stores/useSessionStore'
import axios from 'axios'
import {
  clearImpersonatorSession,
  getImpersonatorSession,
} from '@core/utils/impersonation'
import { translateApiValidationMessage } from '@core/utils/termOverlap'
import { currentCenterId } from '@core/utils/centerContext'

import {
  clearReferenceCache,
  getCachedReferenceResponse,
  getInflightReferenceRequest,
  invalidateReferenceForRequest,
  referenceCacheKey,
  storeReferenceResponse,
  trackInflightReferenceRequest,
} from '@core/utils/referenceCache'

const axiosIns = axios.create({
  baseURL: import.meta.env.VITE_APP_API_URL,
  timeout: 600000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

const unsetContentType = headers => {
  if (!headers)
    return

  if (typeof headers.delete === 'function') {
    headers.delete('Content-Type')
    headers.delete('content-type')

    return
  }

  delete headers['Content-Type']
  delete headers['content-type']
}


const rewriteInvalidCenterId = config => {
  const resolved = currentCenterId()
  const isMissing = value => value === 0 || value === '0' || value === '' || value == null || Number.isNaN(Number(value))

  if (config.params && typeof config.params === 'object' && isMissing(config.params.center_id)) {
    if (resolved)
      config.params.center_id = resolved
    else
      delete config.params.center_id
  }

  if (typeof config.url === 'string' && /(?:\?|&)center_id=(?:0|null|NaN|undefined)(?:&|$)/i.test(config.url)) {
    if (resolved)
      config.url = config.url.replace(/center_id=(?:0|null|NaN|undefined)/i, `center_id=${resolved}`)
    else
      config.url = config.url.replace(/([?&])center_id=(?:0|null|NaN|undefined)&?/i, (match, sep) => sep === '?' ? '?' : '')
  }

  if (config.data && typeof config.data === 'object' && !(config.data instanceof FormData) && isMissing(config.data.center_id)) {
    if (resolved)
      config.data.center_id = resolved
    else
      delete config.data.center_id
  }
}

// ℹ️ Add request interceptor to send the authorization header on each subsequent request after login
axiosIns.interceptors.request.use(config => {
  rewriteInvalidCenterId(config)

  // Instance default Content-Type is application/json. Axios 1 then JSON.stringifies
  // FormData (File fields become {}), which is what produced image/video/file: {}.
  if (config.data instanceof FormData)
    unsetContentType(config.headers)

  config.headers['Accept-Language'] = i18n.global.locale.value;

  // Retrieve token from localStorage
  const token = localStorage.getItem('accessToken')
  // If token is found
  if (token) {
    // Get request headers and if headers is undefined assign blank object
    config.headers = config.headers || {}

    // Set authorization header
    // ℹ️ JSON.parse will convert token to string
    config.headers.Authorization = token ? `Bearer ${token}` : ''
  }

  const cached = getCachedReferenceResponse(config)
  if (cached) {
    config.adapter = () => Promise.resolve(cached)

    return config
  }

  const inflight = getInflightReferenceRequest(config)
  if (inflight) {
    config.adapter = () => inflight.then(response => ({
      ...response,
      data: response.data === undefined ? undefined : JSON.parse(JSON.stringify(response.data)),
      config,
    }))

    return config
  }

  if (referenceCacheKey(config)) {
    let resolveRequest
    let rejectRequest
    const pending = new Promise((resolve, reject) => {
      resolveRequest = resolve
      rejectRequest = reject
    })

    config.__referencePending = { resolve: resolveRequest, reject: rejectRequest }
    trackInflightReferenceRequest(config, pending)
    pending.catch(() => {})
  }

  // Return modified config
  return config
})

// ℹ️ Add response interceptor to handle 401 response
axiosIns.interceptors.response.use(response => {
  if (response.config?.__referencePending)
    response.config.__referencePending.resolve(response)

  if (!response.request?.fromReferenceCache)
    storeReferenceResponse(response.config, response)
  invalidateReferenceForRequest(response.config)

  return response
}, error => {
  if (error.config?.__referencePending)
    error.config.__referencePending.reject(error)

  const status = error.response?.status
  const silentForbidden = !!(error.config?.silentForbidden)

  // 403 Forbidden is an authorization miss, never a dead session.
  // 422 is validation. Neither should log the user out.
  if ((status === 422 || status === 403) && !silentForbidden)
    notifyResponseErrors(error.response?.data)

  if (status === 401 && isAuthenticationFailure(error)) {
    const currentToken = localStorage.getItem('accessToken')
    const usedToken = bearerTokenFromConfig(error.config)

    // In-flight requests from a previous identity (impersonation / restore)
    // must not log out the session that is now active.
    if (usedToken && currentToken && usedToken !== currentToken)
      return Promise.reject(error)

    // Keep the impersonated Parent session. Returning to admin is explicit
    // via "Return to Admin Account", not a side effect of a 401/403 on Goal View.
    if (getImpersonatorSession())
      return Promise.reject(error)

    clearImpersonatorSession()
    clearReferenceCache()
    useSessionStore().clearSession()

    router.push('/login')
  }

  return Promise.reject(error)
})

function notifyResponseErrors(payload) {
  try {
    const errors = payload && typeof payload === 'object' ? payload.errors : null
    if (!errors || typeof errors !== 'object')
      return

    const errorMessages = []
    for (const key in errors) {
      if (!Object.prototype.hasOwnProperty.call(errors, key))
        continue

      const first = Array.isArray(errors[key]) ? errors[key][0] : errors[key]
      if (!first)
        continue

      errorMessages.push(translateApiValidationMessage(
        first,
        keyName => i18n.global.t(keyName),
        i18n.global.locale.value,
      ))
    }

    if (!errorMessages.length)
      return

    window.Snackbar?.value?.exposevisibleSnackbar?.(errorMessages.join('<br />'), 'error', 8000)
  }
  catch (e) {
    // Optional UI; never let error rendering break navigation or auth state.
  }
}

function bearerTokenFromConfig(config) {
  if (!config?.headers)
    return ''

  const header = config.headers.Authorization
    || config.headers.authorization
    || (typeof config.headers.get === 'function' ? config.headers.get('Authorization') : '')

  const match = String(header || '').match(/^Bearer\s+(.+)$/i)

  return match ? match[1].trim() : ''
}

function isAuthenticationFailure(error) {
  if (error.response?.status !== 401)
    return false

  if (!localStorage.getItem('accessToken'))
    return true

  const payload = error.response?.data
  const message = typeof payload === 'string'
    ? payload
    : String(payload?.message || payload?.error || '')

  const normalized = message.trim()

  // Sanctum / Laravel Authenticate middleware. Permission denials must not match.
  if (/^unauthenticated\.?$/i.test(normalized))
    return true

  if (/user is not logged in/i.test(normalized))
    return true

  return false
}

export default axiosIns
