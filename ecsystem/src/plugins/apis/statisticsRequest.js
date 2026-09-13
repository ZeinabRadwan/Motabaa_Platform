import axios from '@axios';
import { defineStore } from 'pinia';

export const statisticsApi = defineStore('statisticsRequest', {
  actions: {
    
    // 👉 Fetch Admin Dshboard
    fetchAdminDshboard(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/statistics/admin_dashboard', { params })
    },

    fetchHrDashboard(params = {}) {
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/statistics/hr_dashboard', { params })
    },

    // 👉 Fetch Case
    fetchCase(id) { return axios.get(`/statistics/case/${id}`) },
  },
})
