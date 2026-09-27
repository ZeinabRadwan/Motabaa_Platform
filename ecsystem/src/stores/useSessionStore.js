import { applyRememberMePreference, clearAuthSessionStorage } from '@core/utils/authStorage'
import { clearReferenceCache, invalidateReferenceGroups } from '@core/utils/referenceCache'
import ability, { initialAbility } from '@/plugins/casl/ability'
import { defineStore } from 'pinia'

const parseJson = (key, fallback = null) => {
  try {
    const raw = localStorage.getItem(key)
    if (raw === null || raw === '' || raw === 'undefined')
      return fallback

    return JSON.parse(raw)
  }
  catch (e) {
    return fallback
  }
}

export const buildUserAbilities = user => {
  const roles = ['admin', 'manager', 'specialist', 'teacher', 'parent']
  const userRole = []

  roles.forEach(role => {
    const obj = (user.roles || []).filter(i => i.default_name ? i.default_name.includes(role) : false)
    if (obj.length !== 0)
      userRole.push({ action: role, subject: role })
  })

  if (userRole.length === 0)
    userRole.push({ action: 'default', subject: 'default' })

  const rolesUser = (user.roles || []).map(item => ({
    action: item.default_name,
    subject: item.default_name,
  }))

  const permissions = (user.permissions || []).map(item => ({
    action: item.name,
    subject: item.name,
  }))

  return {
    userAbilities: [
      { action: 'read', subject: 'Auth' },
      { action: 'all-users', subject: 'Auth' },
      ...permissions,
      ...rolesUser,
      userRole[0],
    ],
    userRole,
  }
}

export const useSessionStore = defineStore('session', {
  state: () => ({
    userData: parseJson('userData', null),
    userAbilities: parseJson('userAbilities', null),
    accessToken: localStorage.getItem('accessToken') || '',
    center: localStorage.getItem('center') || '',
    centerData: parseJson('center_data', null),
    userCenters: parseJson('user_centers', null),
    impersonating: !!parseJson('impersonatorSession', null),
    revision: 0,
  }),
  getters: {
    contextKey: state => `${state.userData?.id || 'anon'}:${state.center || 'none'}:${state.revision}`,
    isLoggedIn: state => !!(state.userData && state.accessToken),
    isAdmin: state => state.userData?.role === 'admin',
  },
  actions: {
    persist() {
      if (this.userData)
        localStorage.setItem('userData', JSON.stringify(this.userData))
      else
        localStorage.removeItem('userData')

      if (this.userAbilities)
        localStorage.setItem('userAbilities', JSON.stringify(this.userAbilities))
      else
        localStorage.removeItem('userAbilities')

      if (this.accessToken)
        localStorage.setItem('accessToken', this.accessToken)
      else
        localStorage.removeItem('accessToken')

      localStorage.setItem('center', this.center ?? '')

      if (this.centerData)
        localStorage.setItem('center_data', JSON.stringify(this.centerData))
      else
        localStorage.setItem('center_data', '')

      if (this.userCenters)
        localStorage.setItem('user_centers', JSON.stringify(this.userCenters))
      else
        localStorage.removeItem('user_centers')
    },
    syncAbility() {
      ability.update(this.userAbilities?.length ? this.userAbilities : initialAbility)
    },
    hydrateFromStorage() {
      this.userData = parseJson('userData', null)
      this.userAbilities = parseJson('userAbilities', null)
      this.accessToken = localStorage.getItem('accessToken') || ''
      this.center = localStorage.getItem('center') || ''
      this.centerData = parseJson('center_data', null)
      this.userCenters = parseJson('user_centers', null)
      this.impersonating = !!parseJson('impersonatorSession', null)
      this.syncAbility()
      this.revision += 1
    },
    applyUser({ token, user, remember, email } = {}) {
      const { userAbilities, userRole } = buildUserAbilities(user)
      const isAdminRole = userRole.find(item => item.action === 'admin')

      user.fullName = user.name
      user.role = userRole[0] ? userRole[0].action : 'default'

      this.userAbilities = userAbilities
      this.userData = user
      this.accessToken = token
      if (typeof remember === 'boolean')
        applyRememberMePreference(remember, email || user?.email || user?.phone || '')

      if (user.centers && user.centers.length > 0)
        this.userCenters = user.centers
      else
        this.userCenters = null

      if (user.centers && user.centers.length > 0 && !isAdminRole) {
        const preferred = user.centers.find(center => Number(center.status) === 1)
          || user.centers[0]
        this.center = String(preferred.id)
        this.centerData = { title: preferred.title, image_path: '' }
      }
      else {
        this.center = ''
        this.centerData = null
      }

      this.persist()
      this.syncAbility()
      this.revision += 1
      clearReferenceCache()
    },
    setCenter(selectedCenter) {
      const imagePath = selectedCenter?.logo?.file_url || ''

      this.center = selectedCenter.id
      this.centerData = { title: selectedCenter.title, image_path: imagePath }
      this.persist()
      this.revision += 1
      invalidateReferenceGroups('terms', 'disabilities', 'services', 'evaluation-methods', 'logs')
    },
    setCenterData(centerData) {
      this.centerData = centerData
      if (centerData)
        localStorage.setItem('center_data', JSON.stringify(centerData))
      else
        localStorage.setItem('center_data', '')
    },
    setUserCenters(centers) {
      this.userCenters = centers
      if (centers)
        localStorage.setItem('user_centers', JSON.stringify(centers))
      else
        localStorage.removeItem('user_centers')
    },
    patchUserData(userData) {
      this.userData = userData
      localStorage.setItem('userData', JSON.stringify(userData))
      this.revision += 1
    },
    setImpersonating(value) {
      this.impersonating = !!value
    },
    clearSession() {
      this.userData = null
      this.userAbilities = null
      this.accessToken = ''
      this.center = ''
      this.centerData = null
      this.userCenters = null
      this.impersonating = false

      localStorage.removeItem('userData')
      localStorage.removeItem('center')
      localStorage.removeItem('center_data')
      localStorage.removeItem('user_centers')
      localStorage.removeItem('accessToken')
      localStorage.removeItem('userAbilities')
      clearAuthSessionStorage()

      this.syncAbility()
      this.revision += 1
      clearReferenceCache()
    },
  },
})
