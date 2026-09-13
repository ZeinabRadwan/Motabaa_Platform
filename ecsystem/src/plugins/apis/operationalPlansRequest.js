import axios from '@axios';
import { defineStore } from 'pinia';

export const operationalPlansApi = defineStore('operationalPlansRequest', {
  actions: {
    
    // 👉 Fetch Plans data
    fetchPlans(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/plans', { params })
    },

    // 👉 fetch single Plan
    fetchPlan(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/plans/show/${id}?center_id=`+Number(localStorage.getItem('center'))).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 print Plan
    printPlan(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/plans/pdf/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Update Plan
    putPlan(id, data) {
      return new Promise((resolve, reject) => {
        axios.post(`/plans/put/${id}`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // // 👉 Delete Plan
    deletePlan(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/plans/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // // 👉 Restore Plan
    restorePlan(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/plans/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },
    
    // 👉 Fetch Goals data
    fetchGoals(params) { return axios.get('/plans/goals', { params }) },

    // 👉 fetch single Plan
    fetchGoal(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/plans/goal/show/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Update Plan
    putGoal(id, data) {
      return new Promise((resolve, reject) => {
        axios.post(`/plans/goal/put/${id}`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // 👉 Update Plan
    actionGoal(id, data) {
      return new Promise((resolve, reject) => {
        axios.post(`/plans/goal/action/${id}`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // // 👉 Delete Plan
    deleteGoal(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/plans/goal/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },
  },
})
