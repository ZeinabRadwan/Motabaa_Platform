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

const appendMessageField = (formData, key, value) => {
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

export const messagesApi = defineStore('messages', {
  actions: {

    fetchAll(params) { return axios.get('/messages', { params }) },

    put(data, onProgress) {
      return new Promise((resolve, reject) => {
        const formData = data instanceof FormData ? data : new FormData()
        if (!(data instanceof FormData) && data && typeof data === 'object') {
          Object.keys(data).forEach(key => {
            appendMessageField(formData, key, data[key])
          })
        }

        axios.post('/messages/add', formData, {
          onUploadProgress: progressEvent => {
            var progress= parseInt(Math.round((progressEvent.loaded / progressEvent.total) * 100));
            if (onProgress)
              onProgress(progress);
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // 👉 Delete Message
    delete(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/messages/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 fetch single Questionnaire
    fetchFile(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/messages/file/${id}`, { silentForbidden: true }).then(response => resolve(response)).catch(error => reject(error))
      })
    },

  },
})
