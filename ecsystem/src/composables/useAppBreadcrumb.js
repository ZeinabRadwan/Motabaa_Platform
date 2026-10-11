import i18n from '@/plugins/i18n/index.js'
import { resolveBreadcrumbTrail } from '@/utils/breadcrumb/resolveTrail'

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
    const trail = resolveBreadcrumbTrail(route, router)
    if (!trail.length)
      return []

    return translateTrail(trail)
  })

  /** Ancestors only — current page title is shown in GeneralPageHeader */
  const ancestorItems = computed(() => {
    const list = items.value
    if (!list.length)
      return []

    const last = list[list.length - 1]
    if (last.disabled) {
      if (list.length === 1)
        return []

      return list.slice(0, -1)
    }

    return list
  })

  const visible = computed(() => ancestorItems.value.length > 0)

  return {
    items,
    ancestorItems,
    visible,
  }
}
