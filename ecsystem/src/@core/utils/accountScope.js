/**
 * Account-scoped frontend state: anything that belongs to the signed-in user
 * (and active center) and must never leak into another account's session.
 *
 * Kept free of app imports so the session store can call it without cycles.
 */

const USER_SCOPED_STORAGE_PREFIXES = ['athar_parent_']

const resetHandlers = new Set()

export const registerAccountScopedReset = handler => {
  if (typeof handler === 'function')
    resetHandlers.add(handler)

  return () => resetHandlers.delete(handler)
}

export const resetAccountScopedState = () => {
  resetHandlers.forEach(handler => {
    try {
      handler()
    }
    catch (e) {
      // A broken reset must not block switching accounts.
    }
  })
}

const removePrefixedKeys = storage => {
  try {
    const doomed = []
    for (let i = 0; i < storage.length; i++) {
      const key = storage.key(i)
      if (key && USER_SCOPED_STORAGE_PREFIXES.some(prefix => key.startsWith(prefix)))
        doomed.push(key)
    }
    doomed.forEach(key => storage.removeItem(key))
  }
  catch (e) {
    // Storage may be unavailable (private mode / quota).
  }
}

export const clearAccountScopedStorage = () => {
  removePrefixedKeys(localStorage)
  removePrefixedKeys(sessionStorage)
}

const readUserId = () => {
  try {
    const user = JSON.parse(localStorage.getItem('userData') || 'null')

    return user?.id != null ? String(user.id) : ''
  }
  catch (e) {
    return ''
  }
}

/** `userId:centerId` of the session currently persisted in localStorage. */
export const accountScopeKey = () => `${readUserId() || 'anon'}:${localStorage.getItem('center') || 'none'}`

/**
 * Snapshot of the active identity. `isCurrent()` is false once the token,
 * user or center changed, so late responses from a previous account can be dropped.
 */
export const captureAccountScope = () => {
  const token = localStorage.getItem('accessToken') || ''
  const scope = accountScopeKey()

  return {
    scope,
    isCurrent: () => token === (localStorage.getItem('accessToken') || '') && scope === accountScopeKey(),
  }
}

/**
 * `const beginLoad = createScopedLoader()`; each `beginLoad()` returns `apply(fn)`,
 * which wraps a state writer so it only runs while that load is still the latest
 * one and the account it started under is still active.
 */
export const createScopedLoader = () => {
  let run = 0

  return () => {
    const runId = ++run
    const identity = captureAccountScope()
    const isLive = () => runId === run && identity.isCurrent()

    return assign => (...args) => (isLive() ? assign(...args) : undefined)
  }
}
