/**
 * Browser geolocation for website attendance. A position is read once, when the employee presses check-in or
 * check-out, and at each periodic location check while checked in and the page is visible; nothing is tracked in
 * the background (browsers suspend or stop pages that are hidden or closed).
 *
 * Errors are rejected as { code } with one of the codes the API accepts as `location_error`:
 * permission_denied, position_unavailable, timeout, unsupported, insecure_context.
 */

const ERROR_CODES = { 1: 'permission_denied', 2: 'position_unavailable', 3: 'timeout' }

export const GEOLOCATION_TIMEOUT_MS = 20000

export const getBrowserPosition = ({ timeout = GEOLOCATION_TIMEOUT_MS } = {}) => new Promise((resolve, reject) => {
  if (typeof window !== 'undefined' && window.isSecureContext === false) {
    reject({ code: 'insecure_context' })

    return
  }
  if (typeof navigator === 'undefined' || !navigator.geolocation) {
    reject({ code: 'unsupported' })

    return
  }

  navigator.geolocation.getCurrentPosition(
    position => resolve({
      latitude: position.coords.latitude,
      longitude: position.coords.longitude,
      accuracy: Math.round(position.coords.accuracy * 100) / 100,
    }),
    error => reject({ code: ERROR_CODES[error?.code] || 'position_unavailable' }),

    // maximumAge 0: never reuse a cached fix from earlier (possibly elsewhere).
    { enableHighAccuracy: true, timeout, maximumAge: 0 },
  )
})

/** 'granted' | 'denied' | 'prompt', or null when the Permissions API is not available. */
export const geolocationPermissionState = async () => {
  try {
    if (!navigator.permissions?.query)
      return null
    const status = await navigator.permissions.query({ name: 'geolocation' })

    return status.state
  }
  catch {
    return null
  }
}

/** Calls onChange(state) whenever the geolocation permission changes; returns an unsubscribe function. */
export const watchGeolocationPermission = async onChange => {
  try {
    if (!navigator.permissions?.query)
      return () => {}
    const status = await navigator.permissions.query({ name: 'geolocation' })
    const handler = () => onChange(status.state)

    onChange(status.state)
    status.addEventListener('change', handler)

    return () => status.removeEventListener('change', handler)
  }
  catch {
    return () => {}
  }
}
