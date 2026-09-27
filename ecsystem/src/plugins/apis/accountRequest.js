import axios from '@axios'

export function fetchAccountProfile() {
  return axios.get('/account/profile')
}

export function updateAccountProfile(formData) {
  return axios.post('/account/profile', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export function updateAccountPassword(payload) {
  return axios.put('/account/password', payload)
}
