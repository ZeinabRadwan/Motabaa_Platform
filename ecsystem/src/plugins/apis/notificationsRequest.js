import axios from '@axios'

export function fetchUserNotifications(params = {}) {
  return axios.get('/notifications', { params })
}

export function markUserNotificationRead(id) {
  return axios.post(`/notifications/${id}/read`)
}
