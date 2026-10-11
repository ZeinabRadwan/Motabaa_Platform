import i18n from '@/plugins/i18n/index.js'
import { useAppBreadcrumb } from '@/composables/useAppBreadcrumb'
import { isDashboardRoute } from '@/utils/breadcrumb/resolveTrail'

export function useGeneralPageHeader() {
  const route = useRoute()
  const { items } = useAppBreadcrumb()

  const pageTitle = computed(() => {
    const t = i18n.global.t

    if (route.meta?.pageTitleKey)
      return t(route.meta.pageTitleKey)

    if (typeof route.meta?.pageTitle === 'string' && route.meta.pageTitle)
      return route.meta.pageTitle

    const list = items.value
    if (!list.length)
      return ''

    const current = list.find(item => item.disabled) ?? list[list.length - 1]

    return current?.label ?? ''
  })

  const visible = computed(() => {
    if (route.meta?.layout === 'blank')
      return false

    if (route.meta?.pageHeader === false)
      return false

    if (isDashboardRoute(route.name))
      return false

    return Boolean(pageTitle.value)
  })

  const pageSubtitle = computed(() => {
    if (route.meta?.pageSubtitle === false)
      return ''

    if (route.meta?.pageSubtitleKey)
      return i18n.global.t(route.meta.pageSubtitleKey)

    if (typeof route.meta?.pageSubtitle === 'string')
      return route.meta.pageSubtitle

    return ''
  })

  return {
    visible,
    pageTitle,
    pageSubtitle,
  }
}
