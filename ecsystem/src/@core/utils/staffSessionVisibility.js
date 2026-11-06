import ability from '@/plugins/casl/ability'
import { can } from '@layouts/plugins/casl'
import { useSessionStore } from '@/stores/useSessionStore'

export const PARENT_REHAB_ASSESSMENT_PERMISSION = 'access_parent-education-evaluations'
export const PARENT_TREATMENT_ASSESSMENT_PERMISSION = 'access_parent-treatment-evaluations'
export const PARENT_INDEPENDENT_ASSESSMENT_PERMISSION = 'access_parent-independent-evaluations'

const PARENT_ASSESSMENT_NAV_PERMISSIONS = {
  'goals-assessments': PARENT_REHAB_ASSESSMENT_PERMISSION,
  'services-assessments': PARENT_TREATMENT_ASSESSMENT_PERMISSION,
  'independent_goals-assessments': PARENT_INDEPENDENT_ASSESSMENT_PERMISSION,
}

const PARENT_ROLE_KEY = 'parent'

/** Rehabilitation + support services session routes (not independent skills). */
export const PARENT_BLOCKED_PROGRAM_SESSION_ROUTE_PREFIXES = ['goals-sessions', 'services-sessions']

export const PARENT_BLOCKED_PROGRAM_SESSION_NAV_IDENTIFIERS = ['goals-sessions', 'services-sessions']

/** Independent skills session index (staff filter UI) — parents use list → skill content. */
export const PARENT_BLOCKED_INDEPENDENT_SESSIONS_LIST_ROUTE = 'independent_goals-sessions'

export const PARENT_HIDDEN_INDEPENDENT_GOALS_NAV_IDENTIFIERS = new Set([
  'independent_goals-sessions',
])

/** Existing case profile page; parents open it for their linked children (API enforces the link). */
export const PARENT_CASE_PROFILE_ROUTE_NAMES = ['cases-view-tab-id']

export function isParentCaseProfileRoute(routeName) {
  return PARENT_CASE_PROFILE_ROUTE_NAMES.includes(String(routeName || ''))
}

/** Profile route location for a linked child (case id). */
export function parentCaseProfileLocation(caseId, tab = 'info') {
  return { name: 'cases-view-tab-id', params: { tab, id: String(caseId) } }
}

export function isParentBlockedProgramSessionRoute(routeName) {
  const name = String(routeName || '').toLowerCase()

  return PARENT_BLOCKED_PROGRAM_SESSION_ROUTE_PREFIXES.some(prefix => {
    const key = prefix.toLowerCase()

    return name === key || name.startsWith(`${key}-`)
  })
}

/** Hide rehabilitation + support services session items from parent sidebar. */
export function filterParentProgramSessionNavItems(items) {
  if (!isParentUser()) {
    return items
  }

  const hidden = new Set(PARENT_BLOCKED_PROGRAM_SESSION_NAV_IDENTIFIERS)

  return items.map(item => {
    if (!item.children?.length) {
      return item
    }

    return {
      ...item,
      children: item.children.filter(child => !hidden.has(child.identifier)),
    }
  })
}

export function isParentBlockedIndependentSessionsListRoute(routeName) {
  if (!isParentUser()) {
    return false
  }

  return String(routeName || '').toLowerCase() === PARENT_BLOCKED_INDEPENDENT_SESSIONS_LIST_ROUTE
}

export function filterParentIndependentGoalsNavItems(items) {
  if (!isParentUser()) {
    return items
  }

  return items.map(item => {
    if (item.identifier !== 'independent_goals' || !item.children?.length) {
      return item
    }

    return {
      ...item,
      children: item.children.filter(child => !PARENT_HIDDEN_INDEPENDENT_GOALS_NAV_IDENTIFIERS.has(child.identifier)),
    }
  })
}

/** CASL check without Vue instance (nav guards, composables). */
export function abilityCan(action) {
  if (!action)
    return false

  const moduleKey = action.includes('_') ? action.slice(action.indexOf('_') + 1) : action
  if (ability.can(`admin_${moduleKey}`, `admin_${moduleKey}`))
    return true

  return ability.can(action, action)
}

export function abilityCanAny(actions) {
  if (!Array.isArray(actions) || actions.length === 0)
    return false

  return actions.some(action => abilityCan(action))
}

export function parentCanAccessRehabAssessment() {
  if (!isParentUser())
    return true

  return abilityCan(PARENT_REHAB_ASSESSMENT_PERMISSION)
}

export function parentCanAccessTreatmentAssessment() {
  if (!isParentUser())
    return true

  return abilityCan(PARENT_TREATMENT_ASSESSMENT_PERMISSION)
}

export function parentCanAccessIndependentAssessment() {
  if (!isParentUser())
    return true

  return abilityCan(PARENT_INDEPENDENT_ASSESSMENT_PERMISSION)
}

/** Hide parent assessment nav when the parent-portal permission is off. */
export function filterParentAssessmentNavItems(items) {
  if (!isParentUser())
    return items

  return items.map(item => {
    if (!item.children?.length)
      return item

    return {
      ...item,
      children: item.children.filter(child => {
        const parentPerm = PARENT_ASSESSMENT_NAV_PERMISSIONS[child.identifier]
        if (!parentPerm)
          return true

        return abilityCan(parentPerm)
      }),
    }
  })
}

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

/**
 * Session goal/case filters: teacher_id must only apply for teacher accounts (or admin picker).
 * Specialists must not send their user id as teacher_id (would require TEACHER pivot on cases).
 */
export const sessionTeacherFilterId = teacherValue => {
  if (can('admin', 'admin')) {
    return teacherValue || undefined
  }
  if (can('teacher', 'teacher')) {
    return teacherValue ?? undefined
  }

  return undefined
}

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
