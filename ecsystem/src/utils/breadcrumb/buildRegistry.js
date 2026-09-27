import { APP_AND_PAGES_TEMPLATE } from '@/navigation/vertical/app-and-pages'

const DASHBOARD_ROUTE_NAMES = [
  'dashboards-analytics',
  'dashboards-admin',
  'dashboards-centers',
  'dashboards-specialist',
  'dashboards-teacher',
  'dashboards-parent',
  'dashboards-default',
]

/** Routes not represented in the sidebar tree (or need explicit trails). */
export const BREADCRUMB_ROUTE_OVERRIDES = {
  'account-profile': {
    titleKey: 'account.profile_title',
  },
}

function navToRouteName(to) {
  if (!to)
    return null
  if (typeof to === 'string')
    return to

  return to.name ?? null
}

function cloneCrumb(def) {
  return { ...def }
}

function buildFromNavTemplate() {
  /** @type {Map<string, Array<{ titleKey: string, routeName?: string | null, isCurrent?: boolean }>>} */
  const registry = new Map()

  const register = (routeName, ancestors, currentTitleKey) => {
    if (!routeName)
      return

    const trail = [
      { titleKey: 'Dashboards', routeName: '__home__' },
      ...ancestors.map(a => ({
        titleKey: a.titleKey,
        routeName: a.routeName ?? null,
      })),
      { titleKey: currentTitleKey, routeName: null, isCurrent: true },
    ]

    registry.set(routeName, trail)
  }

  const walk = (items, ancestors) => {
    for (const item of items) {
      if (item.children?.length) {
        const nextAncestors = [
          ...ancestors,
          { titleKey: item.title, routeName: navToRouteName(item.to) },
        ]

        walk(item.children, nextAncestors)
      }
      else if (item.to) {
        register(navToRouteName(item.to), ancestors, item.title)
      }
    }
  }

  walk(APP_AND_PAGES_TEMPLATE, [])

  for (const name of DASHBOARD_ROUTE_NAMES) {
    registry.set(name, [{ titleKey: 'Dashboards', routeName: null, isCurrent: true }])
  }

  for (const [routeName, override] of Object.entries(BREADCRUMB_ROUTE_OVERRIDES)) {
    registry.set(routeName, [
      { titleKey: 'Dashboards', routeName: '__home__' },
      { titleKey: override.titleKey, routeName: null, isCurrent: true },
    ])
  }

  return registry
}

let cachedRegistry = null

export function getBreadcrumbRegistry() {
  if (!cachedRegistry)
    cachedRegistry = buildFromNavTemplate()

  return cachedRegistry
}

export function inferListRouteName(routeName, registry) {
  if (!routeName)
    return null

  if (registry.has(routeName))
    return routeName

  if (routeName.includes('-put-')) {
    const base = routeName.split('-put-')[0]
    const listName = `${base}-list`
    if (registry.has(listName))
      return listName
  }

  const actionMatch = routeName.match(/^(.+?)-(?:edit|view|add|change-password)(?:-.*|$)/)
    || routeName.match(/^(.+?)_change-password(?:-.*|$)/)

  if (actionMatch) {
    const base = actionMatch[1]
    const listName = `${base}-list`
    if (registry.has(listName))
      return listName
    if (registry.has(base))
      return base
  }

  const parts = routeName.split('-')
  while (parts.length > 1) {
    parts.pop()
    const base = parts.join('-')
    const listCandidate = `${base}-list`
    if (registry.has(listCandidate))
      return listCandidate
    if (registry.has(base))
      return base
  }

  return null
}

export function inferActionTitleKey(route) {
  const path = route.path || ''
  const name = String(route.name || '')

  if (path.includes('change-password') || name.includes('change-password'))
    return 'Change Password'

  if (/\/put\/0(\/|$)/.test(path) || /\/add(\/|$)/.test(path) || name.includes('-add'))
    return 'Add'

  if (path.includes('/edit/') || path.includes('/put/') || name.includes('-edit'))
    return 'Edit'

  if (path.includes('/view/') || name.includes('-view'))
    return 'View'

  if (/\d+$/.test(path) || name.endsWith('-id'))
    return 'View'

  return null
}

function trailWithAction(listTrail, listRouteName, actionKey) {
  const trail = listTrail.map(cloneCrumb)
  const last = trail[trail.length - 1]

  if (last?.isCurrent) {
    last.isCurrent = false
    last.routeName = listRouteName
  }

  trail.push({ titleKey: actionKey, routeName: null, isCurrent: true })

  return trail
}

export function buildDynamicTrail(route, registry, router) {
  const listRouteName = inferListRouteName(route.name, registry)

  if (listRouteName) {
    const listTrail = registry.get(listRouteName)
    const actionKey = inferActionTitleKey(route)

    if (listTrail?.length && actionKey)
      return trailWithAction(listTrail, listRouteName, actionKey)
  }

  if (!router)
    return null

  const path = route.path
  let best = null

  for (const [name, trail] of registry.entries()) {
    const record = router.getRoutes().find(r => r.name === name)
    if (!record?.path)
      continue

    const recordPath = record.path.replace(/\/+$/, '')
    if (path === recordPath || path.startsWith(`${recordPath}/`)) {
      if (!best || recordPath.length > best.recordPath.length)
        best = { name, trail, recordPath }
    }
  }

  if (!best || path === best.recordPath)
    return null

  const actionKey = inferActionTitleKey(route) || 'View'

  return trailWithAction(best.trail, best.name, actionKey)
}
