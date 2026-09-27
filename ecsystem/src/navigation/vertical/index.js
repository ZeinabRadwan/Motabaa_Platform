import { buildDashboard } from './dashboard'
import { buildAppAndPages } from './app-and-pages'

export const buildVerticalNav = (userData, centerId, userAbilities) => {
  const roles = userData?.roles || []
  const isAdminUser = roles.some(item => item.default_name == 'admin')
  const centers = userData?.centers || []
  const currentCenter = centers.find(item => item.id == centerId) || null
  const isActiveCenter = !!(centerId && currentCenter && currentCenter.status == 1 && currentCenter.is_paid)

  const dashboardItems = buildDashboard(userAbilities)
  const appItems = buildAppAndPages(currentCenter, isAdminUser)

  if (centerId && (isActiveCenter || isAdminUser))
    return [...dashboardItems, ...appItems]

  if (isAdminUser)
    return [...dashboardItems, ...appItems]

  return []
}

export default buildVerticalNav
