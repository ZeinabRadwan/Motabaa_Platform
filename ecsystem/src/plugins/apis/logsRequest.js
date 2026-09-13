import axios from '@axios';
import { defineStore } from 'pinia';

export const logsApi = defineStore('logsRequest', {
  actions: {
    
    fetchLogs(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/logs', { params })
    },

    fetchModels() { 
      return axios.get(`/logs/models?center_id=`+Number(localStorage.getItem('center')))
    },

    fetchTypes() { return axios.get('/logs/types') },

    fetchStatistics(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/logs/statistics', { params })
    },
  },
})
