import axios from '@axios';
import { defineStore } from 'pinia';

export const assessmentsApi = defineStore('assessments', {
  actions: {

    fetchAll(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/assessments', {params}) 
    },
    fetchEvaluationMethodsl() { return axios.get('/assessments/evaluation-methods') },

    put(data, id) {
      data['center_id'] = Number(localStorage.getItem('center'));
      return new Promise((resolve, reject) => {
        axios.put('/assessments/put/' + id, data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    delete(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/assessments/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Restore Term
    restore(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/assessments/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },
    
    import(data) {
      return new Promise((resolve, reject) => {
        axios.post('/assessments/import', data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

  },
})
