import { breakpointsVuetify } from '@vueuse/core'

/**
 * Vuetify 3 `lg` is 1280px. VueUse maps `breakpointsVuetify.lg` to 1904 (xl),
 * so use md + 16 for overlay sidebar / mobile toggle behavior.
 */
export const OVERLAY_NAV_BREAKPOINT_PX = breakpointsVuetify.md + 16
