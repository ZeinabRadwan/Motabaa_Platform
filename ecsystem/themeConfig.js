import { breakpointsVuetify } from '@vueuse/core'
import { VIcon } from 'vuetify/components/VIcon'

// ❗ Logo SVG must be imported with ?raw suffix
import { defineThemeConfig } from '@core'
import { RouteTransitions, Skins } from '@core/enums'
import { AppContentLayoutNav, ContentWidth, FooterType, NavbarType } from '@layouts/enums'
import { BUNDLED_LOGIN_LOGO, resolveSidebarLogoSrc } from '@/utils/branding'

var centerData = localStorage.getItem('center_data') ? JSON.parse(localStorage.getItem('center_data')) : null;
var userData = localStorage.getItem('userData') ? JSON.parse(localStorage.getItem('userData')) : null;

export const { themeConfig, layoutConfig } = defineThemeConfig({
  app: {
    title: centerData ? centerData.title : '',
    login_logo: h('img', { src: BUNDLED_LOGIN_LOGO, style: 'line-height:0; color: rgb(var(--v-global-theme-primary)); width: 200px' }),
    logo: h('img', { src: resolveSidebarLogoSrc(centerData, userData), style: `line-height:0; color: rgb(var(--v-global-theme-primary)); width: ${centerData ? '80px' : '150px'}` }),
    contentWidth: ContentWidth.Boxed,
    contentLayoutNav: AppContentLayoutNav.Vertical,
    overlayNavFromBreakpoint: breakpointsVuetify.md + 16,
    enableI18n: true,
    theme: 'system',
    isRtl: true,
    skin: Skins.Default,
    routeTransition: RouteTransitions.Fade,
    iconRenderer: VIcon,
  },
  navbar: {
    type: NavbarType.Hidden,
    navbarBlur: true,
  },
  footer: { type: FooterType.Static },
  verticalNav: {
    isVerticalNavCollapsed: false,
    defaultNavItemIconProps: { icon: 'tabler-point', size: 14 },
    isVerticalNavSemiDark: false,
  },
  horizontalNav: {
    type: 'sticky',
    transition: 'slide-y-reverse-transition',
  },
  icons: {
    chevronDown: { icon: 'tabler-chevron-down' },
    chevronRight: { icon: 'tabler-chevron-down', size: 18 },
    close: { icon: 'tabler-x' },
    verticalNavPinned: { icon: 'tabler-layout-sidebar-right-collapse', size: 20 },
    verticalNavUnPinned: { icon: 'tabler-layout-sidebar-right-expand', size: 20 },
    sectionTitlePlaceholder: { icon: 'tabler-separator' },
  },
})
