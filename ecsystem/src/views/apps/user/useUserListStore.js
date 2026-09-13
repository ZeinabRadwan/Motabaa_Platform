import axios from '@axios';
import { defineStore } from 'pinia';

export const useUserListStore = defineStore('UserListStore', {
  actions: {
    // 👉 Fetch users data
    fetchUsers(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/users', { params }) 
    },

    // 👉 Add User
    registerUser(data) {
      return new Promise((resolve, reject) => {
        axios.post('/users/register', data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // 👉 Add User
    addUser(data) {
      return new Promise((resolve, reject) => {
        axios.post('/users/create', data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    updateUser(data, id) {
      return new Promise((resolve, reject) => {
        axios.post('/users/update/'+id, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    changepassowrdUser(data, id) {
      return new Promise((resolve, reject) => {
        axios.put('/users/change-password/'+id, data)
        .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    impersonateUser(id) {
      return new Promise((resolve, reject) => {
        axios.post(`/users/impersonate/${id}`)
        .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    stopImpersonation() {
      return new Promise((resolve, reject) => {
        axios.post('/users/stop-impersonation')
        .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // 👉 fetch single user
    fetchUser(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/users/${id}/show?center_id=`+Number(localStorage.getItem('center'))).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Delete User
    deleteUser(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/users/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    restoreUser(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/users/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    selectItems(params) {
      if(typeof params['center_id'] === 'undefined')
        params['center_id'] = Number(localStorage.getItem('center'));

      return new Promise((resolve, reject) => {
        axios.get(`/users/select-items`, { params }).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    searchItems(params = {}) {
      if(typeof params['center_id'] === 'undefined')
        params['center_id'] = Number(localStorage.getItem('center'));
      if (typeof params.limit === 'undefined')
        params.limit = 20

      return new Promise((resolve, reject) => {
        axios.get(`/users/search-items`, { params }).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    fetchRolesUsers(params) {
      params['center_id'] = Number(localStorage.getItem('center'));
      return new Promise((resolve, reject) => {
        axios.get(`/users/fetch_roles_users`, { params }).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    fetchFiles(id, data) {
      return new Promise((resolve, reject) => {
        axios.post(`/users/${id}/files`, data).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    addFile(id, data, onProgress) {
      return new Promise((resolve, reject) => {
        axios.post(`/users/${id}/add_file`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          },
          onUploadProgress: progressEvent => {
            var progress= parseInt(Math.round((progressEvent.loaded / progressEvent.total) * 100));
            if (onProgress)
              onProgress(progress);
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    deleteFile(id, data) {
      return new Promise((resolve, reject) => {
        axios.post(`/users/${id}/delete_file`, data).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    updateFile(id, data) {
      return new Promise((resolve, reject) => {
        axios.post(`/users/${id}/update_file`, data).then(response => resolve(response)).catch(error => reject(error))
      })
    },
    
    import(data) {
      return new Promise((resolve, reject) => {
        axios.post('/users/import', data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    async fetchImage(id, params=null) {
      return await axios.get(`/users/fetch_image/${id}`, { params })
    },

    fetchImageWhileWaiting(id, params=null) {
      return new Promise((resolve, reject) => {
        axios.get(`/users/fetch_image/${id}`, { params }).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    deleteImage(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/users/delete_image/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },
  },
})
