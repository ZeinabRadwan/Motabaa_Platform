import axios from '@axios';
import { defineStore } from 'pinia';

export const employeeLeavesApi = defineStore('employeeLeavesRequest', {
  actions: {
    fetchAll(params) {
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/employee_leaves', { params })
    },

    balance(params) {
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/employee_leaves/balance', { params })
    },

    put(data) {
      data['center_id'] = Number(localStorage.getItem('center'));
      return axios.post('/employee_leaves/put', data)
    },

    review(id, status) {
      return axios.post(`/employee_leaves/${id}/review`, {
        status,
        center_id: Number(localStorage.getItem('center')),
      })
    },

    delete(id) {
      return axios.delete(`/employee_leaves/${id}`)
    },
  },
})
