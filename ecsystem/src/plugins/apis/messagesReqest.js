import axios from '@axios'
import { defineStore } from 'pinia'

export const messagesApi = defineStore('messages', {
  actions: {

    fetchAll(params) { return axios.get('/messages', { params }) },

    put(data, onProgress) {
      return new Promise((resolve, reject) => {
        axios.post('/messages/add', data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          },
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
        axios.get(`/messages/file/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

  },
})
