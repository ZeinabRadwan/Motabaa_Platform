import i18n from '@/plugins/i18n/index.js'
import {
  buildDynamicTrail,
  getBreadcrumbRegistry,
} from '@/utils/breadcrumb/buildRegistry'

function resolveLink(routeName) {
  if (!routeName || routeName === '__home__')
    return { path: '/' }

  return { name: routeName }
}

function translateTrail(trail) {
  const t = i18n.global.t

  return trail.map((crumb, index) => {
    const isLast = index === trail.length - 1
    const isCurrent = crumb.isCurrent ?? isLast

    return {
      label: t(crumb.titleKey),
      to: !isCurrent && crumb.routeName != null ? resolveLink(crumb.routeName) : null,
      disabled: isCurrent,
    }
  })
}

export function useAppBreadcrumb() {
  const route = useRoute()
  const router = useRouter()

  const items = computed(() => {
    if (route.meta?.layout === 'blank')
      return []

    if (route.meta?.breadcrumb === false)
      return []

    if (Array.isArray(route.meta?.breadcrumbItems))
      return route.meta.breadcrumbItems

    const registry = getBreadcrumbRegistry()
    let trail = registry.get(route.name)

    if (!trail)
      trail = buildDynamicTrail(route, registry, router)

    if (!trail?.length)
      return []

    return translateTrail(trail)
  })

  const visible = computed(() => items.value.length > 0)

  return {
    items,
    visible,
  }
}
