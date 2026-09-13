import axios from '@axios';
import { defineStore } from 'pinia';

export const casesApi = defineStore('cases', {
  actions: {

    fetchAll(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/cases', { params }) 
    },

    selectItems(params = {}) {
      params['center_id'] = Number(localStorage.getItem('center'));
      if (typeof params.limit === 'undefined')
        params.limit = 20
      return axios.get('/cases/select-items', { params })
    },

    // 👉 Teachers / specialists that are assigned to cases of the current center
    fetchAssignedStaff(params = {}) {
      params['center_id'] = Number(localStorage.getItem('center'));

      return axios.get('/cases/assigned-staff', { params })
    },

    fetchCase(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/cases/${id}/show?center_id=`+Number(localStorage.getItem('center'))).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    pdfFile(id, pdfType) {
      return new Promise((resolve, reject) => {
        axios.get(`/cases/${id}/pdf/${pdfType}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    add(data) {
      return new Promise((resolve, reject) => {
        axios.post('/cases/create', data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    update(data, id) {
      return new Promise((resolve, reject) => {
        axios.post(`/cases/${id}/update`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    delete(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/cases/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    restore(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/cases/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    fetchFiles(id, data) {
      return new Promise((resolve, reject) => {
        axios.post(`/cases/${id}/files`, data).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    addFile(id, data, onProgress) {
      return new Promise((resolve, reject) => {
        axios.post(`/cases/${id}/add_file`, data,
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

    deleteFile(id, data) {
      return new Promise((resolve, reject) => {
        axios.post(`/cases/${id}/delete_file`, data).then(response => resolve(response)).catch(error => reject(error))
      })
    },
    
    import(data) {
      return new Promise((resolve, reject) => {
        axios.post('/cases/import', data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    fetchImage(id, params=null) {
      return new Promise((resolve, reject) => {
        axios.get(`/cases/fetch_image/${id}`, { params }).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    deleteImage(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/cases/delete_image/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },
  },
})
