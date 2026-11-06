import {
  buildDynamicTrail,
  getBreadcrumbRegistry,
} from '@/utils/breadcrumb/buildRegistry'
import { isParentCaseProfileRoute, isParentUser } from '@core/utils/staffSessionVisibility'

export const DASHBOARD_ROUTE_NAMES = [
  'dashboards-analytics',
  'dashboards-admin',
  'dashboards-centers',
  'dashboards-specialist',
  'dashboards-teacher',
  'dashboards-parent',
  'dashboards-default',
]

export function isDashboardRoute(routeName) {
  return DASHBOARD_ROUTE_NAMES.includes(routeName)
}

/**
 * Raw breadcrumb trail for the current route (untranslated titleKeys).
 */
export function resolveBreadcrumbTrail(route, router) {
  if (route.meta?.layout === 'blank')
    return []

  if (route.meta?.breadcrumb === false)
    return []

  if (Array.isArray(route.meta?.breadcrumbItems))
    return route.meta.breadcrumbItems

  if (isParentCaseProfileRoute(route.name) && isParentUser()) {
    return [
      { titleKey: 'Dashboards', routeName: '__home__' },
      { titleKey: 'View', routeName: null, isCurrent: true },
    ]
  }

  const registry = getBreadcrumbRegistry()
  let trail = registry.get(route.name)

  if (!trail)
    trail = buildDynamicTrail(route, registry, router)

  return trail?.length ? trail : []
}
