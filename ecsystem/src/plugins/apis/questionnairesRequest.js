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
    isQuestionnaireOpen(data) { 
      data['center_id'] = Number(localStorage.getItem('center'));
      return axios.post(`/questionnaires/task`, data) 
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
      
      const token = localStorage.getItem('accessToken')
      var url = `/questionnaires/${data.task_id}/fetch/questions`
      if(token)
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
    addAnswers(task_id, data) {
      
      const token = localStorage.getItem('accessToken')
      var addAnswersURL = `/questionnaires/${task_id}/add_answers`
      if(token)
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
