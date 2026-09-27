import axios from '@axios'
import { defineStore } from 'pinia'

const pickUploadedFile = value => {
  if (typeof File !== 'undefined' && value instanceof File)
    return value
  if (Array.isArray(value))
    return pickUploadedFile(value[0])
  if (value && typeof value === 'object' && typeof value.length === 'number')
    return pickUploadedFile(value[0])

  return null
}

const appendEntryField = (formData, key, value) => {
  if (value === null || value === undefined || value === '')
    return

  if (key === 'image' || key === 'video' || key === 'file') {
    const file = pickUploadedFile(value)
    if (file)
      formData.append(key, file, file.name)

    return
  }

  if (typeof File !== 'undefined' && value instanceof File)
    return

  if (typeof value === 'object' && !Array.isArray(value))
    return

  formData.append(key, value)
}

const centerIdParam = () => Number(localStorage.getItem('center'))

export const centerActivitiesApi = defineStore('centerActivities', {
  actions: {
    fetchAll(params) {
      params.center_id = centerIdParam()

      return axios.get('/center-activities', { params })
    },

    show(id) {
      return axios.get(`/center-activities/${id}/show`, { params: { center_id: centerIdParam() } })
    },

    put(data, id = -1) {
      const payload = { ...data, center_id: centerIdParam() }

      return axios.put(`/center-activities/put/${id}`, payload)
    },

    delete(id) {
      return axios.delete(`/center-activities/${id}`)
    },

    fetchEntries(activityId, params = {}) {
      params.center_id = centerIdParam()

      return axios.get(`/center-activities/${activityId}/entries`, { params })
    },

    addEntry(activityId, data, onProgress) {
      return new Promise((resolve, reject) => {
        const formData = data instanceof FormData ? data : new FormData()
        if (!(data instanceof FormData) && data && typeof data === 'object') {
          Object.keys(data).forEach(key => {
            appendEntryField(formData, key, data[key])
          })
        }

        axios.post(`/center-activities/${activityId}/entries/add?center_id=${centerIdParam()}`, formData, {
          onUploadProgress: progressEvent => {
            const progress = parseInt(Math.round((progressEvent.loaded / progressEvent.total) * 100))
            if (onProgress)
              onProgress(progress)
          },
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    deleteEntry(id) {
      return axios.delete(`/center-activities/entries/${id}`)
    },

    fetchEntryFile(id) {
      return axios.get(`/center-activities/entries/file/${id}`, { silentForbidden: true })
    },

    fetchParentFeed(params) {
      params.center_id = centerIdParam()

      return axios.get('/center-activities/parent-feed', { params })
    },
  },
})
