import axios from '@axios';
import { defineStore } from 'pinia';

export const feesApi = defineStore('fees', {
  actions: {
    
    // 👉 Fetch Fees data
    fetchFees(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/fees', { params }) 
    },

    fetchFeesItems(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/fees/items', { params }) 
    },

    // 👉 fetch single Fee
    fetchFee(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/fees/${id}/show?center_id=`+Number(localStorage.getItem('center'))).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Add Fee
    putFee(data) {
      return new Promise((resolve, reject) => {
        axios.post(`/fees/put`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // // 👉 Delete Fee
    deleteFee(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/fees/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    restoreFee(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/fees/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },
  },
})
