import axios from '@axios';
import { defineStore } from 'pinia';

export const disabilityTypeApi = defineStore('disabilityType', {
  actions: {

    fetch() { return axios.get(`/disabilities?center_id=`+Number(localStorage.getItem('center'))) },

    add(data) {
      data['center_id'] = Number(localStorage.getItem('center'));
      return new Promise((resolve, reject) => {
        axios.post('/disabilities/put', data)
            .then(response => resolve(response))
            .catch(error => reject(error))
      })
    },


  },
})
