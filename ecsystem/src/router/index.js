import { canNavigate } from '@layouts/plugins/casl'
import { setupLayouts } from 'virtual:generated-layouts'
import { createRouter, createWebHistory } from 'vue-router'
import routes from '~pages'
import { isAdmin, isUserLoggedIn } from './utils'
import { purgeExpiredSession } from '@core/utils/authStorage'

purgeExpiredSession()

const router = createRouter({
  history: createWebHistory(),
  routes: [
    // ℹ️ We are redirecting to different pages based on role.
    // NOTE: Role is just for UI purposes. ACL is based on abilities.
    {
      path: '/',
      redirect: to => {
        const userData = JSON.parse(localStorage.getItem('userData') || '{}')
        const userRole = (userData && userData.role) ? userData.role : null
        const userCenter = localStorage.getItem('center')

        if (userCenter) {
          if (userRole === 'admin' || userRole === 'manager')
            return { name: 'dashboards-analytics' }
          if (userRole === 'specialist')
            return { name: 'dashboards-specialist' }
          if (userRole === 'parent')
            return { name: 'dashboards-parent' }
          if (userRole === 'teacher')
            return { name: 'dashboards-teacher' }
          if (userRole === 'default')
            return { name: 'dashboards-analytics' }
        }
        else if (userRole === 'admin') {
          return { name: 'dashboards-admin' }
        }
        else {
          return { name: 'dashboards-centers' }
        }

        return { name: 'login', query: to.query }
      },
    },
    ...setupLayouts(routes),
  ],
})


// Docs: https://router.vuejs.org/guide/advanced/navigation-guards.html#global-before-guards
router.beforeEach(to => {
  
  const userData = JSON.parse(localStorage.getItem('userData') || '{}')
  const userCenter = localStorage.getItem('center')

  const isLoggedIn = isUserLoggedIn()
  const isUserAdmin = isAdmin()
  const isUserInActiveCenter = (userCenter && userData.centers && userData.centers.find(item => item.id == userCenter && item.status == 1 && item.is_paid))
  const center = (userData.centers ? userData.centers.find(item => item.id == userCenter) : '')

  if(to.path && to.path == '/close-tab'){
    window.close();
  }
  /*
  
    ℹ️ Commented code is legacy code
  
    if (!canNavigate(to)) {
      // Redirect to login if not logged in
      // ℹ️ Only add `to` query param if `to` route is not index route
      if (!isLoggedIn)
        return next({ name: 'login', query: { to: to.name !== 'index' ? to.fullPath : undefined } })
  
      // If logged in => not authorized
      return next({ name: 'not-authorized' })
    }
  
    // Redirect if logged in
    if (to.meta.redirectIfLoggedIn && isLoggedIn)
      next('/')
  
    return next()
  
    */
  if (canNavigate(to)) {
    if(isLoggedIn) {
      if (!isUserInActiveCenter && !isUserAdmin && to.name != 'dashboards-centers' && to.name != 'centers-payments-put-center-package') {
        return { name: 'dashboards-centers' }
      }
      else if(center && Number(center.package_id) == 2 && !isUserAdmin) {

        var notAccessibleRoutes = ['independent_goals-', 'attendances-', 'plans-', 'fees-', 'payments-', 'questionnaires-', 'employees_attendance-'];
        if(notAccessibleRoutes.some(word => to.name.toLowerCase().includes(word.toLowerCase())) && !to.name.toLowerCase().includes('centers-payments')) {
          return { name: 'not-authorized' }
        }
        else if (to.meta.redirectIfLoggedIn) {
          return '/'
        }
      }
      else if(center && Number(center.package_id) == 3 && !isUserAdmin) {

        var notAccessibleRoutes = ['plans-', 'questionnaires-', 'employees_attendance-'];
        if(notAccessibleRoutes.some(word => to.name.toLowerCase().includes(word.toLowerCase())) && !to.name.toLowerCase().includes('centers-payments')) {
          return { name: 'not-authorized' }
        }
        else if (to.meta.redirectIfLoggedIn) {
          return '/'
        }
      }
      else if (to.meta.redirectIfLoggedIn) {
        return '/'
      }
    }
  }
  else {
    if (isLoggedIn)
      return { name: 'not-authorized' }
    else
      return { name: 'login', query: { to: to.name !== 'index' ? to.fullPath : undefined } }
  }
})
export default router
