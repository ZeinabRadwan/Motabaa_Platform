import i18n from '@/plugins/i18n/index.js'
import router from '@/router'
import { useSessionStore } from '@/stores/useSessionStore'
import axios from 'axios'
import {
  IMPERSONATION_RESTORE_FLAG,
  clearImpersonatorSession,
  getImpersonatorSession,
  restoreImpersonatorSession,
} from '@core/utils/impersonation'
import { translateApiValidationMessage } from '@core/utils/termOverlap'

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


// ℹ️ Add request interceptor to send the authorization header on each subsequent request after login
axiosIns.interceptors.request.use(config => {
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

  if (error.response?.status === 422 || error.response?.status === 403) {
    const errorResponse = error.response.data;
    const errorMessages = [];

    // Iterate over the properties in the "errors" object and concatenate error messages
    for (const key in errorResponse.errors || {}) {
      if (errorResponse.errors.hasOwnProperty(key)) {
        let message = errorResponse.errors[key][0];
        message = translateApiValidationMessage(
          message,
          keyName => i18n.global.t(keyName),
          i18n.global.locale.value,
        )
        errorMessages.push(message);
      }
    }
    if (errorMessages.length) {
      const concatenatedErrors = errorMessages.join("<br />");

      window.Snackbar.value.exposevisibleSnackbar(concatenatedErrors, 'error', 8000);
    }
  }
  // Handle error
  if (error.response?.status === 401) {
    const payload = error.response?.data || {}
    const isUnauthenticated = payload.message === 'Unauthenticated.'
      || !localStorage.getItem('accessToken')

    // Module middleware used to return 401 for missing permissions.
    // That is not a dead session — especially while impersonating.
    if (!isUnauthenticated && payload.errors !== undefined) {
      return Promise.reject(error)
    }

    const impersonatorSession = getImpersonatorSession()
    if (impersonatorSession && sessionStorage.getItem(IMPERSONATION_RESTORE_FLAG) !== '1') {
      sessionStorage.setItem(IMPERSONATION_RESTORE_FLAG, '1')
      if (restoreImpersonatorSession()) {
        router.replace('/')

        return Promise.reject(error)
      }
    }

    clearImpersonatorSession()
    clearReferenceCache()
    useSessionStore().clearSession()

    router.push('/login')

    return Promise.reject(error)
  }
  else {
    return Promise.reject(error)
  }
})
export default axiosIns
