import { useThemeConfig } from '@core/composable/useThemeConfig'
import { isParentUser } from '@core/utils/staffSessionVisibility'
import { injectionKeyToggleOverlayNav } from '@layouts'

/**
 * Parent accounts: expanded sidebar on desktop (content pushes, no overlay drawer);
 * overlay drawer stays closed by default on small screens until the user opens it.
 */
export function useParentLayoutNav() {
  if (!isParentUser())
    return

  const { isVerticalNavCollapsed, isLessThanOverlayNavBreakpoint } = useThemeConfig()
  const { width: windowWidth } = useWindowSize()
  const toggleOverlayNav = inject(injectionKeyToggleOverlayNav, null)

  const syncDesktopExpanded = () => {
    if (!isLessThanOverlayNavBreakpoint.value(windowWidth.value))
      isVerticalNavCollapsed.value = false
  }

  onMounted(() => {
    syncDesktopExpanded()
  })

  watch(windowWidth, () => {
    syncDesktopExpanded()
    if (!isLessThanOverlayNavBreakpoint.value(windowWidth.value) && toggleOverlayNav)
      toggleOverlayNav(false)
  })
}
