import axios from '@axios'
import { defineStore } from 'pinia'

export const servicesApi = defineStore('services', {
  actions: {

    fetch() { return axios.get('/services') },


  },
})
