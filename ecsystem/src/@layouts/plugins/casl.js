import ability from '@/plugins/casl/ability'
import { canAccessCasesList } from '@core/utils/caseListAccess'
import { useSessionStore } from '@/stores/useSessionStore'

/**
 * Returns ability result if ACL is configured or else just return true
 * We should allow passing string | undefined to can because for admin ability we omit defining action & subject
 *
 * Useful if you don't know if ACL is configured or not
 * Used in @core files to handle absence of ACL without errors
 *
 * @param {String} action CASL Actions // https://casl.js.org/v4/en/guide/intro#basics
 * @param {String} subject CASL Subject // https://casl.js.org/v4/en/guide/intro#basics
 */
export const can = (action, subject) => {
  const vm = getCurrentInstance()
  if (!vm)
    return false
  if(!action) return false
  const localCan = vm.proxy && '$can' in vm.proxy
  if(localCan){
    const moduleKey = action.includes('_') ? action.slice(action.indexOf('_') + 1) : action
    if(vm.proxy?.$can('admin_'+moduleKey, 'admin_'+moduleKey)){
      return true;
    }
    return vm.proxy?.$can(action, subject);
  }
  return true
  // return localCan ? vm.proxy?.$can(action, subject) : true
}

/**
 * Check if user can view item based on it's ability
 * Based on item's action and subject & Hide group if all of it's children are hidden
 * @param {Object} item navigation object item
 */
export const canViewNavMenuGroup = item => {
  const hasAnyVisibleChild = item.children.some(i => can(i.action, i.subject))

  // If subject and action is defined in item => Return based on children visibility (Hide group if no child is visible)
  // Else check for ability using provided subject and action along with checking if has any visible child
  if (!(item.action && item.subject))
    return hasAnyVisibleChild

  return can(item.action, item.subject) && hasAnyVisibleChild
}
export const canNavigate = to => {
  try {
    if(to.matched.some(route => {
      const action = route.meta.action || ''
      const subject = route.meta.subject || ''
      const actionModule = action.includes('_') ? action.slice(action.indexOf('_') + 1) : action
      const subjectModule = subject.includes('_') ? subject.slice(subject.indexOf('_') + 1) : subject
      return ability.can('admin_'+actionModule, 'admin_'+subjectModule)
    })){
      return true;
    }
  } catch (error) { }
  return to.matched.some(route => {
    if (route.meta.caseListAccess) {
      return canAccessCasesList()
    }

    const action = route.meta.action || ''
    const subject = route.meta.subject || ''
    if (!action || !subject) {
      return false
    }

    return ability.can(action, subject)
  })
}


export const canDoes = (action) => {
  let userAbilities = null
  try {
    userAbilities = useSessionStore().userAbilities
  }
  catch (e) {
    userAbilities = null
  }
  if (!Array.isArray(userAbilities)) {
    try {
      userAbilities = JSON.parse(localStorage.getItem('userAbilities') || '[]')
    }
    catch (e) {
      userAbilities = []
    }
  }

  return Array.isArray(userAbilities) && userAbilities.some(item => item.action === action && item.subject === action);
}