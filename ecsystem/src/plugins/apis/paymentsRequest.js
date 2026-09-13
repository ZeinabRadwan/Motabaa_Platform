import axios from '@axios';
import { defineStore } from 'pinia';

export const paymentsApi = defineStore('payments', {
  actions: {
    
    // 👉 Fetch Payments data
    fetchPayments(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/payments', { params }) 
    },

    // 👉 fetch single Payment
    fetchPayment(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/payments/${id}/show?center_id=`+Number(localStorage.getItem('center'))).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Add Payment
    putPayment(data) {
      return new Promise((resolve, reject) => {
        axios.post(`/payments/put`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // 👉 Add Pay Payment
    actionPayment(id, data) {
      return new Promise((resolve, reject) => {
        axios.post(`/payments/${id}/action`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        })
        .then(response => resolve(response))
        .catch(error => reject(error))
      })
    },

    // // 👉 Delete Payment
    deletePayment(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/payments/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    fetchFile(id, params=null) {
      return new Promise((resolve, reject) => { 
        axios.get(`/payments/fetch_file/${id}`, { params }).then(response => resolve(response)).catch(error => reject(error))
      })
    },
  },
})
