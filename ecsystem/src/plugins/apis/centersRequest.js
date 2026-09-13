import axios from '@axios';
import { defineStore } from 'pinia';

export const centersApi = defineStore('centers', {
  actions: {
    
    // 👉 Fetch Centers data
    fetchCenters(params) { return axios.get('/centers', { params }) },

    items(params) { 
      return axios.get(`/centers/items`, { params })
    },

    // 👉 fetch single Center
    fetchCenter(id, params={}) {
      return new Promise((resolve, reject) => {
        axios.get(`/centers/${id}/show`, { params }).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Update Center
    putCenter(data) {
      return new Promise((resolve, reject) => {
        axios.post(`/centers/put`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // 👉 Delete Center
    deleteCenter(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/centers/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Delete Center
    deleteManager(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/centers/${id}/manager`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Delete Center
    deleteLogo(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/centers/${id}/logo`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Restore Center
    restoreCenter(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/centers/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Restore Center
    activateCenter(id) {
      return new Promise((resolve, reject) => {
        axios.post(`/centers/${id}/activate`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Calculat Center Payment
    calculatCenterPayment(id, params={}) {
      return axios.get(`/centers/${id}/calculate_payment`, { params }) 
    },

    // 👉 fetch Packages
    fetchPackages(params={}) {
      return axios.get('/centers/packages', { params }) 
    },
    
    // 👉 Fetch Centers data
    fetchPayments(params) {
      return axios.get('/centers/payments', { params }) 
    },

    // 👉 Update Center
    putPayment(data) {
      return new Promise((resolve, reject) => {
        axios.post(`/centers/payments/put`, data,
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
        axios.post(`/centers/payments/${id}/action`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        })
        .then(response => resolve(response))
        .catch(error => reject(error))
      })
    },

    // 👉 Delete Center
    deletePayment(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/centers/payments/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Restore Center
    restorePayment(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/centers/payments/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },
    
    // 👉 Fetch Centers data
    fetchUsers(params) { return axios.get('/centers/users', { params }) },

    // 👉 Update Center
    putUser(data) {
      return new Promise((resolve, reject) => {
        axios.post(`/centers/users/put`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // 👉 Delete Center
    deleteUser(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/centers/users/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },
  },
})
