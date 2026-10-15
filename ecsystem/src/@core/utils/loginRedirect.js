export const MEETING_ROOM_INVITE_PATH = '/meeting-rooms/invite'

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
 * Extract invitation token from a full path or path+query string.
 */
export function parseInviteTokenFromFullPath(fullPath) {
  const normalized = normalizeRedirectTarget(fullPath)
  if (! normalized?.startsWith(MEETING_ROOM_INVITE_PATH))
    return null

  const queryIndex = normalized.indexOf('?')
  if (queryIndex === -1)
    return null

  const params = new URLSearchParams(normalized.slice(queryIndex + 1))

  return params.get('token')
}

/**
 * Build login route location preserving the page the user tried to open.
 * Invitation links use separate query keys so `token` is not split off the URL.
 */
export function buildLoginLocation(fromFullPath) {
  const normalized = normalizeRedirectTarget(fromFullPath)
  if (! normalized || normalized === '/login' || normalized.startsWith('/login?'))
    return { name: 'login' }

  const inviteToken = parseInviteTokenFromFullPath(normalized)
  if (inviteToken) {
    return {
      name: 'login',
      query: {
        to: MEETING_ROOM_INVITE_PATH,
        invite_token: inviteToken,
      },
    }
  }

  return { name: 'login', query: { to: normalized } }
}

/**
 * Post-login destination from route query (`to`), with invite token recovery when
 * the return URL was split across query params (legacy or browser parsing).
 */
export function resolvePostLoginRedirect(route, defaultPath = '/') {
  const toPath = normalizeRedirectTarget(route.query?.to)
  const inviteToken = route.query?.invite_token ?? route.query?.token

  if (toPath === MEETING_ROOM_INVITE_PATH && inviteToken) {
    return `${MEETING_ROOM_INVITE_PATH}?token=${encodeURIComponent(String(inviteToken))}`
  }

  let target = toPath

  if (target?.startsWith(MEETING_ROOM_INVITE_PATH) && target.includes('token='))
    return target

  if (target?.startsWith(MEETING_ROOM_INVITE_PATH) && inviteToken) {
    return `${MEETING_ROOM_INVITE_PATH}?token=${encodeURIComponent(String(inviteToken))}`
  }

  return target || defaultPath
}

export function routeIsMeetingRoomInvite(to) {
  return to.matched?.some(route => route.meta?.meetingRoomInvite)
}
