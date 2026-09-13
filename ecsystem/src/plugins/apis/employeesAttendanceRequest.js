import axios from '@axios';
import { defineStore } from 'pinia';

export const employeesAttendanceApi = defineStore('employeesAttendanceRequest', {
  actions: {
    
    // 👉 Fetch Attendance data
    fetchAll(params) { 
      params['center_id'] = Number(localStorage.getItem('center'));
      return axios.get('/employees_attendance', { params }) 
    },

    // 👉 fetch single Attendance
    fetchAttendance(id, params) {
      params['center_id'] = Number(localStorage.getItem('center'));
      return new Promise((resolve, reject) => {
        axios.get(`/employees_attendance/show/${id}`, { params }).then(response => resolve(response)).catch(error => reject(error))
      })
    },

    // 👉 Update Attendance
    putAttendance(data, onProgress) {
      return new Promise((resolve, reject) => {
        axios.post(`/employees_attendance/put`, data,
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
  },
})
