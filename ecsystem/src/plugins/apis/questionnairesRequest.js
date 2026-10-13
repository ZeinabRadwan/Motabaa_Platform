import axios from '@axios';
import { defineStore } from 'pinia';

export const questionnairesApi = defineStore('questionnaires', {
  actions: {
    
    // 👉 Fetch Questionnaires data
    fetchQuestionnaires(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/questionnaires', { params })
    },

    // 👉 fetch single Questionnaire
    fetchQuestionnaire(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/questionnaires/${id}/show?center_id=`+Number(localStorage.getItem('center'))).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 is Questionnaire open
    isQuestionnaireOpen(data = {}) { 
      data['center_id'] = Number(localStorage.getItem('center'));
      return axios.get(`/questionnaires/task`, { params: data }) 
    },

    fetchRespondentAvailableTasks(params = {}) {
      params['center_id'] = Number(localStorage.getItem('center'));

      return axios.get('/questionnaires/respondent/tasks', { params })
    },

    fetchTargetAudienceRoles() {
      return axios.get('/questionnaires/target-audience/roles', {
        params: { center_id: Number(localStorage.getItem('center')) },
      })
    },

    // 👉 Add Questionnaire
    putQuestionnaire(data) {
      data['center_id'] = Number(localStorage.getItem('center'));
      return new Promise((resolve, reject) => {
        axios.post(`/questionnaires/put`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    putCustomQuestionnaire(data) {
      const centerId = Number(localStorage.getItem('center'))
      if (typeof FormData !== 'undefined' && data instanceof FormData)
        data.append('center_id', centerId)
      else
        data['center_id'] = centerId

      return new Promise((resolve, reject) => {
        axios.post(`/questionnaires/put-custom`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // // 👉 Delete Questionnaire
    deleteQuestionnaire(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/questionnaires/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // // 👉 Restore Questionnaire
    restoreQuestionnaire(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/questionnaires/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Fetch Questions
    fetchQuestions(data) {
      const usePublicRespondentApi = Boolean(data.publicAccess)
      const token = localStorage.getItem('accessToken')
      let url = `/questionnaires/${data.task_id}/fetch/questions`
      if (token && ! usePublicRespondentApi)
        url = `/questionnaires/${data.task_id}/fetch/questions_with_user_answer`

      data['center_id'] = Number(localStorage.getItem('center'));
      return new Promise((resolve, reject) => {
        axios.post(url, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // 👉 Fetch Questions With Answers
    questionnaireAnalyticsParams(data = {}) {
      return {
        center_id: Number(localStorage.getItem('center')),
        ...data,
      }
    },

    exportQuestionnaireResponses(taskId, params = {}) {
      return axios.get(`/questionnaires/${taskId}/export/responses`, {
        params: this.questionnaireAnalyticsParams(params),
      })
    },

    exportQuestionnaireAnalyticsPdf(taskId, params = {}) {
      return axios.get(`/questionnaires/${taskId}/export/analytics-pdf`, {
        params: this.questionnaireAnalyticsParams(params),
        responseType: 'blob',
        headers: { Accept: 'application/pdf' },
        silentForbidden: true,
      })
    },

    exportQuestionnaireTemplatePdf(taskId, params = {}) {
      return axios.get(`/questionnaires/${taskId}/export/template-pdf`, {
        params: this.questionnaireAnalyticsParams(params),
        responseType: 'blob',
        headers: { Accept: 'application/pdf' },
        silentForbidden: true,
      })
    },

    searchQuestionnaireRespondents(taskId, params = {}) {
      return axios.get(`/questionnaires/${taskId}/respondents/search`, {
        params: this.questionnaireAnalyticsParams(params),
      })
    },

    fetchQuestionsWithAnswers(data) {
      data['center_id'] = Number(localStorage.getItem('center'));
      return new Promise((resolve, reject) => {
        axios.post(`/questionnaires/fetch/questions_with_answers`, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // 👉 Add Answers
    addAnswers(task_id, data, options = {}) {
      const usePublicRespondentApi = Boolean(options.publicAccess)
      const token = localStorage.getItem('accessToken')
      let addAnswersURL = `/questionnaires/${task_id}/add_answers`
      if (token && ! usePublicRespondentApi)
        addAnswersURL = `/questionnaires/${task_id}/add_user_answers`

      return new Promise((resolve, reject) => {
        axios.post(addAnswersURL, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },
  },
})
