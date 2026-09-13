import axios from '@axios'
import { defineStore } from 'pinia'

export const assessmentEvaluationsApi = defineStore('assessmentEvaluations', {
  actions: {

    fetchAll(params) { return axios.get('/assessment-evaluations', { params }) },
    saveGoals(data) { return axios.post('/assessment-evaluations/put', data ) },
    listWeaks(data) { 
      data['center_id'] = Number(localStorage.getItem('center'));
      return axios.post('/assessment-evaluations/list-weaks', data ) 
    },
    moveWeaks(data) { return axios.post('/assessment-evaluations/move-weaks', data ) },



  },
})
