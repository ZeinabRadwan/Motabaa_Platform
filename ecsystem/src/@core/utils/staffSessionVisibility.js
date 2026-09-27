import ability from '@/plugins/casl/ability'
import { useSessionStore } from '@/stores/useSessionStore'

const PARENT_ROLE_KEY = 'parent'

/** Same admin-module promotion as @layouts/plugins/casl `can()`. */
function moduleCan(action) {
  const moduleKey = action.includes('_') ? action.slice(action.indexOf('_') + 1) : action
  if (ability.can(`admin_${moduleKey}`, `admin_${moduleKey}`)) {
    return true
  }

  return ability.can(action, action)
}

/**
 * Staff who may use session run controls: module access or run/edit permission (not parents).
 * Session routes gate on access_*; run UI must stay available for those users.
 */
function canRunSessions(accessPermission, editPermission) {
  if (isParentUser()) {
    return false
  }

  return moduleCan(editPermission) || moduleCan(accessPermission)
}

export const canRunEducationSessions = () => canRunSessions(
  'access_education-sessions',
  'edit_education-sessions',
)

export const canRunTreatmentSessions = () => canRunSessions(
  'access_treatment-sessions',
  'edit_treatment-sessions',
)

export const canRunIndependentSessions = () => canRunSessions(
  'access_independent-sessions',
  'edit_independent-sessions',
)

function readSessionUser() {
  try {
    const fromStore = useSessionStore().userData
    if (fromStore && typeof fromStore === 'object') {
      return fromStore
    }
  }
  catch (e) {
    // Pinia may be unavailable outside setup
  }

  try {
    const raw = localStorage.getItem('userData')
    if (!raw || raw === 'undefined') {
      return null
    }

    return JSON.parse(raw)
  }
  catch (e) {
    return null
  }
}

function normalizeRoles(roles) {
  if (Array.isArray(roles)) {
    return roles
  }
  if (roles && typeof roles === 'object') {
    return Object.values(roles)
  }

  return []
}

/**
 * Parent-only account: authoritative flag from login API when present, else same rule as backend
 * (Spatie default_name "parent" on every role — no staff role mixed in).
 * Staff session actions remain gated by existing CASL permissions only.
 */
export const isParentUser = () => {
  const user = readSessionUser()
  if (!user) {
    return false
  }

  if (typeof user.is_parent_user === 'boolean') {
    return user.is_parent_user
  }

  const roles = normalizeRoles(user.roles)
  if (roles.length === 0) {
    return false
  }

  let hasParentRole = false

  for (const role of roles) {
    const key = role?.default_name ?? null
    if (key === PARENT_ROLE_KEY) {
      hasParentRole = true
      continue
    }

    return false
  }

  return hasParentRole
}

/** Goal autocomplete label; staff see session count in parentheses. */
export const goalAutocompleteTitle = (item, { selectedCase = null } = {}) => {
  const title = isParentUser()
    ? item.title
    : `${item.title} (${item.sessions_count ?? 0})`

  return selectedCase || !item.case?.name ? title : `${item.case.name} — ${title}`
}

/** Independent session table columns hidden from parents. */
export const filterIndependentSessionHeaders = headers => {
  if (!isParentUser()) {
    return headers
  }

  return headers.filter(header => !['date', 'day', 'time'].includes(header.key))
}
