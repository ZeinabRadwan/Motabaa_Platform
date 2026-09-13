import axios from '@axios';
import { defineStore } from 'pinia';

export const roomsApi = defineStore('rooms', {
  actions: {

    fetchAll(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/meeting-rooms', { params })
    },

    show(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/meeting-rooms/${id}/show?center_id=`+Number(localStorage.getItem('center'))).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    put(data, id) {
      data['center_id'] = Number(localStorage.getItem('center'));
      return new Promise((resolve, reject) => {
        axios.put('/meeting-rooms/put/' + id, data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    startNow(data) { return axios.post('/meeting-rooms/start_now', data ) },
    startLater(data) { return axios.post('/meeting-rooms/start_later', data ) },


    delete(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/meeting-rooms/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },
    restore(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/meeting-rooms/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

  },
})
