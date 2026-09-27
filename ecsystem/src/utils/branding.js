import centerSidebarLogo from '@images/logo.png'
import loginLogo from '@images/athar.png'
import managerSidebarLogo from '@images/1logo.webp'
import adminSidebarLogo from '@images/athar.png'

export const BUNDLED_CENTER_SIDEBAR_LOGO = centerSidebarLogo
export const BUNDLED_MANAGER_SIDEBAR_LOGO = managerSidebarLogo
export const BUNDLED_ADMIN_SIDEBAR_LOGO = adminSidebarLogo
export const BUNDLED_LOGIN_LOGO = loginLogo

const CENTER_BUNDLED_SIDEBAR_TITLES = [
  'مركز التميز الشامل للرعاية النهارية',
]

const userRoleNames = userData => (userData?.roles || []).map(role => String(role.default_name || '').toLowerCase())

export function resolveSidebarLogoSrc(centerData, userData) {
  const roles = userRoleNames(userData)

  if (roles.includes('admin') || userData?.role === 'admin')
    return BUNDLED_ADMIN_SIDEBAR_LOGO

  if (roles.includes('manager'))
    return BUNDLED_MANAGER_SIDEBAR_LOGO

  const title = String(centerData?.title || '').trim()
  if (CENTER_BUNDLED_SIDEBAR_TITLES.includes(title))
    return BUNDLED_CENTER_SIDEBAR_LOGO

  if (centerData?.image_path)
    return centerData.image_path

  return BUNDLED_LOGIN_LOGO
}

export function sidebarLogoWidth(userData) {
  const roles = userRoleNames(userData)
  if (!roles.includes('admin') && roles.includes('manager'))
    return '160px'

  return '80px'
}
