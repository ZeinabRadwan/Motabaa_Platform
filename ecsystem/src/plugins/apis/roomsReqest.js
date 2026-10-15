import axios from '@axios';
import { defineStore } from 'pinia';
import { withResolvedCenterId } from '@core/utils/centerContext';

export const roomsApi = defineStore('rooms', {
  actions: {

    fetchAll(params) { 
      params['center_id'] = withResolvedCenterId(params?.center_id);
      return axios.get('/meeting-rooms', { params })
    },

    show(id) {
      return new Promise((resolve, reject) => {
        const centerId = withResolvedCenterId()
        axios.get(`/meeting-rooms/${id}/show`, { params: { center_id: centerId } }).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    put(data, id) {
      data['center_id'] = withResolvedCenterId(data?.center_id);
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

    invitationStatus(token) {
      return axios.get('/meeting-rooms/invitations/status', { params: { token } })
    },

    redeemInvitation(token) {
      return axios.post('/meeting-rooms/invitations/redeem', { token })
    },

    fetchInvitationLink(roomId, rotate = false) {
      const centerId = withResolvedCenterId()
      return axios.get(`/meeting-rooms/${roomId}/invitation-link`, {
        params: { center_id: centerId, rotate: rotate ? 1 : 0 },
      })
    },

  },
})
