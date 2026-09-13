import { buildAppAndPages } from '../vertical/app-and-pages'

export const buildHorizontalNav = (userData, centerId) => {
  if (!centerId)
    return []

  const roles = userData?.roles || []
  const isUserAdmin = roles.some(item => item.default_name == 'admin')
  const currentCenter = (userData?.centers || []).find(item => item.id == centerId) || null

  return buildAppAndPages(currentCenter, isUserAdmin)
}

export default buildHorizontalNav
