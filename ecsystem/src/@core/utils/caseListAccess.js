import ability from '@/plugins/casl/ability'

const LIST_PERMISSIONS = [
  'access_cases_mine',
  'access_cases_all',
  'access_cases',
  'admin_cases',
]

function moduleCan(action) {
  const moduleKey = action.includes('_') ? action.slice(action.indexOf('_') + 1) : action
  if (ability.can(`admin_${moduleKey}`, `admin_${moduleKey}`)) {
    return true
  }

  return ability.can(action, action)
}

export const canAccessCasesList = () => LIST_PERMISSIONS.some(moduleCan)

export const canViewAllCenterCases = () => moduleCan('access_cases_all') || moduleCan('admin_cases')
