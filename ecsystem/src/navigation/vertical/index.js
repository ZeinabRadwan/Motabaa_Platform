import { buildDashboard } from './dashboard'
import { buildAppAndPages } from './app-and-pages'
import {
  filterParentAssessmentNavItems,
  filterParentIndependentGoalsNavItems,
  filterParentProgramSessionNavItems,
  isParentUser,
} from '@core/utils/staffSessionVisibility'

const PARENT_CENTER_ACTIVITIES_NAV = {
  identifier: 'parent-center-activities',
  title: 'center_activities.menu',
  icon: { icon: 'tabler-calendar-event' },
  action: 'parent',
  subject: 'parent',
  to: { name: 'parent-center-activities' },
}

const PARENT_HIDDEN_TOP_NAV = new Set([
  'cases-menu',
  'center-activities',
  'parents',
  'attendances',
  'employee_affairs',
  'system_settings',
  'settings',
])

function applyParentNavCustomization(items) {
  const filtered = items.filter(item => !PARENT_HIDDEN_TOP_NAV.has(item.identifier))

  filtered.push(PARENT_CENTER_ACTIVITIES_NAV)

  return filterParentAssessmentNavItems(
    filterParentIndependentGoalsNavItems(filterParentProgramSessionNavItems(filtered)),
  )
}

export const buildVerticalNav = (userData, centerId, userAbilities) => {
  const roles = userData?.roles || []
  const isAdminUser = roles.some(item => item.default_name == 'admin')
  const centers = userData?.centers || []
  const currentCenter = centers.find(item => item.id == centerId) || null

  // Menu visibility follows roles/permissions; subscription state (is_paid) is enforced by the route gate, not here.
  const isActiveCenter = !!(centerId && currentCenter && currentCenter.status == 1)

  const dashboardItems = buildDashboard(userAbilities)
  const appItems = buildAppAndPages(currentCenter, isAdminUser)

  if (centerId && (isActiveCenter || isAdminUser)) {
    const items = [...dashboardItems, ...appItems]

    return isParentUser() ? applyParentNavCustomization(items) : items
  }

  if (isAdminUser)
    return [...appItems]

  return []
}

export default buildVerticalNav
