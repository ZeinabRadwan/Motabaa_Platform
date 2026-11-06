import axios from '@axios';
import { defineStore } from 'pinia';

export const goalsApi = defineStore('goals', {
  actions: {

    fetchAll(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/goals', { params }) 
    },

    selectItems(params = {}) {
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/goals/select-items', { params })
    },

    selectCaseItems(params = {}) {
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/goals/select-case-items', { params })
    },

    fetchFilterItems(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/goals/filter', { params })
    },

    fetchParentTreatmentCategories(params) {
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/goals/parent-treatment-categories', { params })
    },
    
    fetchParentDetails(id, params = {}) {
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get(`/goals/parent-details/${id}`, { params, silentForbidden: true })
    },

    addParentMessage(id, { caseId, section, field, file, fileName, content }, onProgress, signal) {
      const formData = new FormData()
      formData.append('center_id', String(Number(localStorage.getItem('center'))))
      formData.append('case_id', String(caseId))
      formData.append('section', section)
      formData.append(field, file, fileName || file.name)
      if (content)
        formData.append('content', content)

      return axios.post(`/goals/parent-details/${id}/messages`, formData, {
        silentForbidden: true,
        signal,

        // Large videos on mobile networks can take longer than the instance default.
        timeout: 0,
        onUploadProgress: event => {
          const total = event.total || file.size
          if (onProgress && total)
            onProgress(Math.min(100, Math.round((event.loaded / total) * 100)))
        },
      })
    },

    addParentMessageChunk(id, { caseId, section, field, uploadId, file, fileName, chunk, content }, onProgress, signal) {
      const formData = new FormData()

      formData.append('center_id', String(Number(localStorage.getItem('center'))))
      formData.append('case_id', String(caseId))
      formData.append('section', section)
      formData.append('field', field)
      formData.append('upload_id', uploadId)
      formData.append('file_name', fileName || file.name)
      formData.append('total_size', String(file.size))
      formData.append('total_chunks', String(chunk.totalChunks))
      formData.append('chunk_index', String(chunk.index))
      formData.append('chunk', chunk.blob, 'chunk')
      if (chunk.isLast && content)
        formData.append('content', content)

      return axios.post(`/goals/parent-details/${id}/messages/chunks`, formData, {
        silentForbidden: true,
        signal,
        timeout: 180000,
        onUploadProgress: event => onProgress?.(event.loaded),
      })
    },

    fetchSteps(params) { return axios.get('/goals/steps', { params }) },
    fetchPeriodStatus(params) { return axios.get('/goals/period_status', { params }) },
    fetchEvaluationsSteps(params) { return axios.get('/goals/evaluations_steps', { params }) },

    // 👉 fetch single Goal
    fetchGoal(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/goals/show/${id}?center_id=`+Number(localStorage.getItem('center'))).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    put(data, id) {
      return new Promise((resolve, reject) => {
        axios.put('/goals/put/' + id, data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    transferGoal(data, id) {
      return new Promise((resolve, reject) => {
        axios.post('/goals/transfer_goal/'+id, data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    periodAction(data) {
      return new Promise((resolve, reject) => {
        axios.post('/goals/period_action', data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    update(data) {
      return new Promise((resolve, reject) => {
        axios.put('/goals/update_eval', data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    startSession(id, data) {
      return new Promise((resolve, reject) => {
        axios.put('/goals/start_session/' + id, data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    endSession(id, data) {
      return new Promise((resolve, reject) => {
        axios.put('/goals/end_session/' + id, data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    putSteps(data, id = null) {
      return new Promise((resolve, reject) => {
        axios.put('/goals/steps/put' + (id ? '/'+id : ''), data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    delete(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/goals/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    restore(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/goals/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    deleteSteps(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/goals/steps/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },
    
    restoreSteps(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/goals/steps/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    reOrderSteps(id, data) {
      return new Promise((resolve, reject) => {
        axios.put(`/goals/steps/reorder/${id}`, data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    putEvaluationStep(data) {
      return new Promise((resolve, reject) => {
        axios.put('/goals/evaluations_steps/put', data)
          .then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    deleteEvaluationStep(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/goals/evaluations_steps/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

  },
})
