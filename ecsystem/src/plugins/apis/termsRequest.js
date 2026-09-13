import axios from '@axios';
import { defineStore } from 'pinia';

export const termsApi = defineStore('terms', {
  actions: {

    currentTerm() { 
      return axios.get(`/terms/current?center_id=`+Number(localStorage.getItem('center')))
    },

    items(params) { 
      return axios.get(`/terms/items?center_id=`+Number(localStorage.getItem('center')), { params })
    },
    
    // 👉 Fetch terms data
    fetchTerms(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/terms', { params })
    },

    // 👉 fetch single Term
    fetchTerm(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/terms/${id}/show?center_id=`+Number(localStorage.getItem('center'))).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Update Term
    putTerm(data) {
      data['center_id'] = Number(localStorage.getItem('center'));
      return new Promise((resolve, reject) => {
        axios.post(`/terms/put`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // 👉 Delete Term
    deleteTerm(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/terms/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Restore Term
    restoreTerm(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/terms/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },
  },
})
