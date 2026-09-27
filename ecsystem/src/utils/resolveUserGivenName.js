/**
 * First token of the user's display name (given / first appropriate name for greetings).
 */
export function resolveUserGivenName(user) {
  if (!user)
    return ''

  const raw = String(user.name || user.fullName || user.username || '').trim()
  if (!raw)
    return ''

  const [first] = raw.split(/\s+/)

  return first || raw
}
