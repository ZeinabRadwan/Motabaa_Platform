import axios from '@axios'
import { defineStore } from 'pinia'

export const useRoleStore = defineStore('RoleStore', {
  actions: {
    fetchRoles(params) { return axios.get('/roles', { params }) },

    fetchOnlyRoles() { return axios.get('/roles/get/all') },

    addRole(data) {
      return new Promise((resolve, reject) => {
        axios.put('/roles', data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },
    getPermissonsRole(id) {
      return new Promise((resolve, reject) => {
        axios.get('/roles/'+id)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    updateRole(id, data) {
      return new Promise((resolve, reject) => {
        axios.put('/roles/'+id, data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    deleteRole(id) {
      return new Promise((resolve, reject) => {
        axios.delete('/roles/'+id)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },
  },
})
