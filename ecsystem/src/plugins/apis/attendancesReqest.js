import axios from '@axios';
import { defineStore } from 'pinia';

export const attendancesApi = defineStore('attendances', {
  actions: {

    fetchAll(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/attendances', { params }) 
    },

    export(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/attendances/export', { params }) },

    fetchAllCase(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/attendances/case', { params }) 
    },

    put(data, id = '-1') {
      return new Promise((resolve, reject) => {
        axios.post('/attendances/put/' + id, data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },
    
    putFile(id, data, onProgress) {
      return new Promise((resolve, reject) => {
        axios.post('/attendances/put/'+ id, data,
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

  },
})
