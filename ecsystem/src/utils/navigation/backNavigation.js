import { getBreadcrumbRegistry, inferListRouteName } from '@/utils/breadcrumb/buildRegistry'
import { isDashboardRoute } from '@/utils/breadcrumb/resolveTrail'
import { isParentUser } from '@core/utils/staffSessionVisibility'

/** Parent treatment plan list, restoring the child and the filters carried in the details URL. */
export function parentTreatmentListLocation(route) {
  const query = { case: String(route.params.case) }
  for (const key of ['term', 'service_type', 'feild']) {
    if (route.query?.[key] != null && route.query[key] !== '')
      query[key] = String(route.query[key])
  }

  return { name: 'services-list', query }
}

/** Explicit back targets for routes where list inference is wrong or ambiguous. */
export const BACK_NAV_ROUTE_OVERRIDES = {
  'goals-details-case-term-goal': route => ({
    name: 'goals-list-case-term',
    params: { case: route.params.case, term: route.params.term },
  }),
  'services-details-case-goal': parentTreatmentListLocation,
  'goals-sessions-case-term-goal': { name: 'goals-list' },
  'services-sessions-case-term-goal': { name: 'services-list' },
  'independent_goals-sessions-case-term-goal': { name: 'independent_goals-list' },
  'goals-list-case-term': route => (
    isParentUser()
      ? { name: 'dashboards-parent' }
      : { name: 'goals-list' }
  ),
  'services-list-case-term-service_type': { name: 'services-list' },
  'independent_goals-list-case-term': route => (
    isParentUser()
      ? { name: 'dashboards-parent' }
      : { name: 'independent_goals-list' }
  ),
  'questionnaires-questions-id': route => {
    if (route.query.to)
      return { path: String(route.query.to) }

    return isParentUser()
      ? { name: 'dashboards-parent' }
      : { name: 'questionnaires-list' }
  },
  'cases-view-tab-id': () => (isParentUser() ? { name: 'dashboards-parent' } : { name: 'cases-list' }),
  'cases-view-id': () => (isParentUser() ? { name: 'dashboards-parent' } : { name: 'cases-list' }),
  'payments-list-case-term-fee': { name: 'payments-list' },
  'plans-tab-id-view': { name: 'plans-list' },
  'plans-list-tab': { name: 'plans-list' },
  'user-view-tab-id': { name: 'user-list' },
  'meeting-rooms-invite': { name: 'meeting-rooms-list' },
}

const AUTH_ROUTE_PREFIXES = [
  '/login',
  '/sign-up',
  '/forgot-password',
  '/reset-password',
  '/activation_page',
]

function normalizeBackTo(backTo, route) {
  if (typeof backTo === 'string')
    return backTo.startsWith('/') ? { path: backTo } : { name: backTo }

  if (backTo && typeof backTo === 'object')
    return { ...backTo }

  return null
}

function resolveOverride(route) {
  const entry = BACK_NAV_ROUTE_OVERRIDES[route.name]
  if (!entry)
    return null

  if (typeof entry === 'function')
    return entry(route)

  return entry
}

/**
 * Registered sidebar leaf with no dynamic params (top-level list / hub pages).
 */
export function isTopLevelNavLeafRoute(route) {
  const name = route.name
  if (!name)
    return false

  const registry = getBreadcrumbRegistry()
  if (!registry.has(name))
    return false

  return Object.keys(route.params || {}).length === 0
}

/**
 * Resolve router.push location for context-aware back navigation.
 * @returns {import('vue-router').RouteLocationRaw | null}
 */
export function resolveBackDestination(route) {
  if (route.query?.to) {
    const path = String(route.query.to)

    return path.startsWith('/') ? { path } : { path: `/${path}` }
  }

  if (route.meta?.backTo != null)
    return normalizeBackTo(route.meta.backTo, route)

  const override = resolveOverride(route)
  if (override)
    return override

  const registry = getBreadcrumbRegistry()
  const listRouteName = inferListRouteName(route.name, registry)

  if (listRouteName && listRouteName !== route.name)
    return { name: listRouteName }

  return null
}

export function shouldShowBackNavigation(route) {
  if (route.meta?.backNavigation === false)
    return false

  if (route.meta?.layout === 'blank')
    return false

  if (isDashboardRoute(route.name))
    return false

  const path = route.path || ''
  if (AUTH_ROUTE_PREFIXES.some(prefix => path === prefix || path.startsWith(`${prefix}/`)))
    return false

  if (isTopLevelNavLeafRoute(route))
    return false

  return resolveBackDestination(route) != null
}
