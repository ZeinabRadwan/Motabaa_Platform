import axios from '@axios'
import { defineStore } from 'pinia'

export const useCoursesListStore = defineStore('CouresListStore', {
  actions: {
    
    // 👉 Fetch courses data
    fetchCourses(params) { return axios.get('/courses', { params }) },

    // 👉 Add Course
    addCourse(data) {
      console.log(data)
      return new Promise((resolve, reject) => {
        axios.post('/courses/create', data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },
    
    // 👉 Update Course
    updateCourse(data, id) {
      return new Promise((resolve, reject) => {
        axios.post('/courses/update/'+id, data,
        {
          headers: {
            "Content-Type": "multipart/form-data",
          }
        }).then(response => resolve(response))
          .catch(error => reject(error))
      })
    },

    // 👉 Fetch Course
    fetchCourse(id) {
      return new Promise((resolve, reject) => {
        axios.get(`/courses/${id}/show`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Delete Course
    deleteCourse(id) {
      return new Promise((resolve, reject) => {
        axios.delete(`/courses/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Delete Course
    restoreCourse(id) {
      return new Promise((resolve, reject) => {
        axios.patch(`/courses/${id}`).then(response => resolve(response)).catch(error => reject(error))
      })
    },
  },
})
