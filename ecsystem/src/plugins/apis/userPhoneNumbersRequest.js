import axios from '@axios'

export function fetchUserPhoneNumbers(userId) {
  return axios.get(`/users/${userId}/phone-numbers`)
}

export function addUserPhoneNumber(userId, payload) {
  return axios.post(`/users/${userId}/phone-numbers`, payload)
}

export function updateUserPhoneNumber(userId, numberId, payload) {
  return axios.put(`/users/${userId}/phone-numbers/${numberId}`, payload)
}

export function removeUserPhoneNumber(userId, numberId) {
  return axios.delete(`/users/${userId}/phone-numbers/${numberId}`)
}

export function setPrimaryUserPhoneNumber(userId, numberId, markVerified = false) {
  return axios.post(`/users/${userId}/phone-numbers/${numberId}/primary`, { mark_verified: markVerified })
}

export function verifyUserPhoneNumber(userId, numberId, verified) {
  return axios.post(`/users/${userId}/phone-numbers/${numberId}/verify`, { verified })
}
