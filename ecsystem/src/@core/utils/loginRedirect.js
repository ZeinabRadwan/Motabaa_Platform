/**
 * Safe in-app paths only (no open redirects).
 */
export function normalizeRedirectTarget(raw) {
  if (raw === undefined || raw === null || raw === '')
    return null

  const path = Array.isArray(raw) ? raw[0] : String(raw)
  const trimmed = path.trim()
  if (! trimmed.startsWith('/') || trimmed.startsWith('//'))
    return null

  return trimmed
}

/**
 * Build login route location preserving the page the user tried to open.
 */
export function buildLoginLocation(fromFullPath) {
  const to = normalizeRedirectTarget(fromFullPath)
  if (! to || to === '/login' || to.startsWith('/login?'))
    return { name: 'login' }

  return { name: 'login', query: { to } }
}

/**
 * Post-login destination from route query (`to`), with invite token recovery when
 * the return URL was split across query params.
 */
export function resolvePostLoginRedirect(route, defaultPath = '/') {
  let target = normalizeRedirectTarget(route.query?.to)

  if (target && target.startsWith('/meeting-rooms/invite') && ! target.includes('token=')) {
    const inviteToken = route.query?.token
    if (inviteToken)
      target = `/meeting-rooms/invite?token=${encodeURIComponent(String(inviteToken))}`
  }

  return target || defaultPath
}
