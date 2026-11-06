import axios from '@axios'
import { currentCenterId } from '@core/utils/centerContext'

const withCenter = (payload = {}) => ({ ...payload, center_id: currentCenterId() })

const resource = path => ({
  list: params => axios.get(`/hr/${path}`, { params: withCenter(params) }),
  show: id => axios.get(`/hr/${path}/${id}`, { params: withCenter() }),
  create: data => axios.post(`/hr/${path}`, withCenter(data)),
  update: (id, data) => axios.put(`/hr/${path}/${id}`, withCenter(data)),
  remove: id => axios.delete(`/hr/${path}/${id}`, { params: withCenter() }),
  restore: id => axios.patch(`/hr/${path}/${id}/restore`, withCenter()),
})

const activatable = path => ({
  activate: id => axios.patch(`/hr/${path}/${id}/activate`, withCenter()),
  deactivate: id => axios.patch(`/hr/${path}/${id}/deactivate`, withCenter()),
  employees: (id, params) => axios.get(`/hr/${path}/${id}/employees`, { params: withCenter(params) }),
})

export const hrBranchesApi = {
  ...resource('branches'),
  ...activatable('branches'),
  cities: () => axios.get('/hr/branches/cities', { params: withCenter() }),
}
export const hrDepartmentsApi = resource('departments')
export const hrWorkLocationsApi = {
  ...resource('work-locations'),
  ...activatable('work-locations'),
  map: params => axios.get('/hr/work-locations/map', { params: withCenter(params) }),
}

export const hrShiftsApi = {
  ...resource('shifts'),
  activate: id => axios.patch(`/hr/shifts/${id}/activate`, withCenter()),
  deactivate: id => axios.patch(`/hr/shifts/${id}/deactivate`, withCenter()),
  versions: id => axios.get(`/hr/shifts/${id}/versions`, { params: withCenter() }),
  cancelVersion: (id, versionId) => axios.post(`/hr/shifts/${id}/versions/${versionId}/cancel`, withCenter()),
  assign: (id, data) => axios.post(`/hr/shifts/${id}/assign`, withCenter(data)),
}
export const hrShiftExceptionsApi = {
  list: params => axios.get('/hr/shift-exceptions', { params: withCenter(params) }),
  create: data => axios.post('/hr/shift-exceptions', withCenter(data)),
  cancel: id => axios.post(`/hr/shift-exceptions/${id}/cancel`, withCenter()),
}
export const hrShiftScheduleApi = {
  team: params => axios.get('/hr/shift-schedule', { params: withCenter(params) }),
  employee: (employeeId, params) => axios.get(`/hr/employees/${employeeId}/schedule`, { params: withCenter(params) }),
}
export const hrAttendancePoliciesApi = {
  ...resource('attendance-policies'),
  ...activatable('attendance-policies'),
  versions: id => axios.get(`/hr/attendance-policies/${id}/versions`, { params: withCenter() }),
  cancelVersion: (id, versionId) => axios.post(`/hr/attendance-policies/${id}/versions/${versionId}/cancel`, withCenter()),
}

// The axios interceptor does not add center_id to FormData bodies.
const formDataWithCenter = fields => {
  const data = new FormData()

  Object.entries(fields).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '')
      data.append(key, value)
  })
  data.append('center_id', currentCenterId() ?? '')

  return data
}

/** Effective-dated employee records: list, add a new row, cancel a scheduled row. */
const employeeRecords = path => ({
  list: employeeId => axios.get(`/hr/employees/${employeeId}/${path}`, { params: withCenter() }),
  create: (employeeId, data) => axios.post(`/hr/employees/${employeeId}/${path}`, withCenter(data)),
  cancel: (employeeId, id) => axios.post(`/hr/employees/${employeeId}/${path}/${id}/cancel`, withCenter()),
})

export const hrEmployeesApi = {
  list: params => axios.get('/hr/employees', { params: withCenter(params) }),
  candidates: params => axios.get('/hr/employees/candidates', { params: withCenter(params) }),
  show: id => axios.get(`/hr/employees/${id}`, { params: withCenter() }),
  create: data => axios.post('/hr/employees', withCenter(data)),
  update: (id, data) => axios.put(`/hr/employees/${id}`, withCenter(data)),
  updateAttendance: (id, data) => axios.put(`/hr/employees/${id}/attendance`, withCenter(data)),
  attendanceConfigs: id => axios.get(`/hr/employees/${id}/attendance-configs`, { params: withCenter() }),
  cancelAttendanceConfig: (id, configId) => axios.post(`/hr/employees/${id}/attendance-configs/${configId}/cancel`, withCenter()),
  attendanceRules: (id, date) => axios.get(`/hr/employees/${id}/attendance-rules`, { params: withCenter({ date }) }),
  attendanceRecords: (id, params) => axios.get(`/hr/employees/${id}/attendance-records`, { params: withCenter(params) }),
  attendancePunches: (id, params) => axios.get(`/hr/employees/${id}/attendance-punches`, { params: withCenter(params) }),
  deactivate: (id, data) => axios.patch(`/hr/employees/${id}/deactivate`, withCenter(data)),
  activate: (id, data) => axios.patch(`/hr/employees/${id}/activate`, withCenter(data)),
  uploadPhoto: (id, file) => axios.post(`/hr/employees/${id}/photo`, formDataWithCenter({ photo: file })),
  removePhoto: id => axios.post(`/hr/employees/${id}/photo/remove`, withCenter()),
  history: (id, params) => axios.get(`/hr/employees/${id}/history`, { params: withCenter(params) }),
  salaries: employeeRecords('salaries'),
  insurances: employeeRecords('insurances'),
  shifts: employeeRecords('shifts'),
  workAssignments: employeeRecords('work-assignments'),
}

export const hrEmployeeDocumentsApi = {
  list: (employeeId, params) => axios.get(`/hr/employees/${employeeId}/documents`, { params: withCenter(params) }),
  upload: (employeeId, fields) => axios.post(`/hr/employees/${employeeId}/documents`, formDataWithCenter(fields)),
  update: (employeeId, id, data) => axios.put(`/hr/employees/${employeeId}/documents/${id}`, withCenter(data)),
  remove: (employeeId, id) => axios.delete(`/hr/employees/${employeeId}/documents/${id}`, { params: withCenter() }),
  download: (employeeId, id) => axios.get(`/hr/employees/${employeeId}/documents/${id}/download`, {
    params: withCenter(),
    responseType: 'blob',
  }),
}

export const hrSettingsApi = {
  fetch: () => axios.get('/hr/settings', { params: withCenter() }),
  update: settings => axios.put('/hr/settings', withCenter({ settings })),
}

export const hrApprovalsApi = {
  list: params => axios.get('/hr/approvals', { params: withCenter(params) }),
  show: id => axios.get(`/hr/approvals/${id}`, { params: withCenter() }),
  approve: (id, comment) => axios.post(`/hr/approvals/${id}/approve`, withCenter({ comment })),
  reject: (id, comment) => axios.post(`/hr/approvals/${id}/reject`, withCenter({ comment })),
  cancel: (id, comment) => axios.post(`/hr/approvals/${id}/cancel`, withCenter({ comment })),
  workflows: () => axios.get('/hr/approval-workflows', { params: withCenter() }),
  saveWorkflow: data => axios.put('/hr/approval-workflows', withCenter(data)),
}

// Punch errors are shown on the attendance page itself (silentForbidden skips the global 422 / 403 snackbar).
const PUNCH_CONFIG = { silentForbidden: true, timeout: 20000 }

export const hrMyAttendanceApi = {
  status: () => axios.get('/hr/my-attendance', { params: withCenter() }),
  history: params => axios.get('/hr/my-attendance/history', { params: withCenter(params) }),
  checkIn: data => axios.post('/hr/my-attendance/check-in', withCenter(data), PUNCH_CONFIG),
  checkOut: data => axios.post('/hr/my-attendance/check-out', withCenter(data), PUNCH_CONFIG),
  locationCheck: data => axios.post('/hr/my-attendance/location-check', withCenter(data), PUNCH_CONFIG),
}

export const hrLocationMonitoringApi = {
  sessions: params => axios.get('/hr/location-monitoring/sessions', { params: withCenter(params) }),
  employees: params => axios.get('/hr/location-monitoring/employees', { params: withCenter(params) }),
  checks: params => axios.get('/hr/location-monitoring/checks', { params: withCenter(params) }),
  exceptions: params => axios.get('/hr/location-monitoring/exceptions', { params: withCenter(params) }),
  reviewException: (id, data) => axios.post(`/hr/location-monitoring/exceptions/${id}/review`, withCenter(data)),
  devices: params => axios.get('/hr/location-monitoring/devices', { params: withCenter(params) }),
  approveDevice: id => axios.post(`/hr/location-monitoring/devices/${id}/approve`, withCenter()),
  revokeDevice: id => axios.post(`/hr/location-monitoring/devices/${id}/revoke`, withCenter()),
}

// The form preview reports rule violations inline, without the global error snackbar.
const PREVIEW_CONFIG = { silentForbidden: true }

export const hrAttendanceRecordsApi = {
  list: params => axios.get('/hr/attendance-records', { params: withCenter(params) }),
  summary: params => axios.get('/hr/attendance-records/summary', { params: withCenter(params) }),
  employees: params => axios.get('/hr/attendance-records/employees', { params: withCenter(params) }),
  show: id => axios.get(`/hr/attendance-records/${id}`, { params: withCenter() }),
  history: id => axios.get(`/hr/attendance-records/${id}/history`, { params: withCenter() }),
  preview: data => axios.post('/hr/attendance-records/preview', withCenter(data), PREVIEW_CONFIG),
  create: data => axios.post('/hr/attendance-records', withCenter(data)),
  update: (id, data) => axios.put(`/hr/attendance-records/${id}`, withCenter(data)),
  bulk: data => axios.post('/hr/attendance-records/bulk', withCenter(data)),
  importTemplate: params => axios.get('/hr/attendance-records/import-template', { params: withCenter(params) }),
  import: fields => axios.post('/hr/attendance-records/import', formDataWithCenter(fields), { timeout: 120000 }),
}

export const hrHolidaysApi = resource('holidays')

/** Leave types come back as a short unpaginated list; list() adapts it to the server-table shape and filters by q locally. */
export const hrLeaveTypesApi = {
  ...resource('leave-types'),
  list: (params = {}) => axios.get('/hr/leave-types', { params: withCenter({ active_only: params.active_only }) }).then(response => {
    const q = String(params.q ?? '').trim().toLowerCase()

    const rows = (response.data.data ?? []).filter(type => !q
      || [type.code, type.name, type.name_ar, type.name_en].some(value => String(value ?? '').toLowerCase().includes(q)))

    const perPage = Number(params.options?.itemsPerPage) || -1
    const page = Number(params.options?.page) || 1
    const pageRows = perPage > 0 ? rows.slice((page - 1) * perPage, page * perPage) : rows

    return { ...response, data: { data: pageRows, total: rows.length } }
  }),
}

/** Leave request body: files go as attachments[], booleans as 1 / 0 (FormData sends strings). */
const leaveRequestForm = (fields, files = []) => {
  const data = formDataWithCenter(Object.fromEntries(Object.entries(fields).map(([key, value]) => [key, typeof value === 'boolean' ? (value ? 1 : 0) : value])))

  files.forEach(file => data.append('attachments[]', file))

  return data
}

export const hrLeaveRequestsApi = {
  list: params => axios.get('/hr/leave-requests', { params: withCenter(params) }),
  show: id => axios.get(`/hr/leave-requests/${id}`, { params: withCenter() }),
  employees: params => axios.get('/hr/leave-requests/employees', { params: withCenter(params) }),
  preview: data => axios.post('/hr/leave-requests/preview', withCenter(data), PREVIEW_CONFIG),
  create: (fields, files) => axios.post('/hr/leave-requests', leaveRequestForm(fields, files), { timeout: 120000 }),
  cancel: (id, reason) => axios.post(`/hr/leave-requests/${id}/cancel`, withCenter({ reason })),
  attachment: (id, attachmentId) => axios.get(`/hr/leave-requests/${id}/attachments/${attachmentId}`, {
    params: withCenter(),
    responseType: 'blob',
  }),
}

export const hrLeaveBalancesApi = {
  list: params => axios.get('/hr/leave-balances', { params: withCenter(params) }),
  adjust: data => axios.post('/hr/leave-balances/adjust', withCenter(data)),
  transactions: id => axios.get(`/hr/leave-balances/${id}/transactions`, { params: withCenter() }),
}

export const hrMyLeaveApi = {
  overview: params => axios.get('/hr/my-leave', { params: withCenter(params) }),
  requests: params => axios.get('/hr/my-leave/requests', { params: withCenter(params) }),
  show: id => axios.get(`/hr/my-leave/requests/${id}`, { params: withCenter() }),
  preview: data => axios.post('/hr/my-leave/preview', withCenter(data), PREVIEW_CONFIG),
  create: (fields, files) => axios.post('/hr/my-leave/requests', leaveRequestForm(fields, files), { timeout: 120000 }),
  cancel: (id, reason) => axios.post(`/hr/my-leave/requests/${id}/cancel`, withCenter({ reason })),
}

export const hrAttendanceDaysApi = {
  list: params => axios.get('/hr/attendance-days', { params: withCenter(params) }),
  summary: params => axios.get('/hr/attendance-days/summary', { params: withCenter(params) }),
  employees: params => axios.get('/hr/attendance-days/employees', { params: withCenter(params) }),
  show: id => axios.get(`/hr/attendance-days/${id}`, { params: withCenter() }),
  process: data => axios.post('/hr/attendance-days/process', withCenter(data), { timeout: 120000 }),
  correct: (id, data) => axios.post(`/hr/attendance-days/${id}/correct`, withCenter(data)),
  reset: (id, reason) => axios.post(`/hr/attendance-days/${id}/reset`, withCenter({ reason })),
}

export const hrAttendancePeriodsApi = {
  ...resource('attendance-periods'),
  process: id => axios.post(`/hr/attendance-periods/${id}/process`, withCenter(), { timeout: 120000 }),
  approve: id => axios.patch(`/hr/attendance-periods/${id}/approve`, withCenter(), { timeout: 120000 }),
  reopen: (id, reason) => axios.patch(`/hr/attendance-periods/${id}/reopen`, withCenter({ reason })),
  summaries: (id, params) => axios.get(`/hr/attendance-periods/${id}/summaries`, { params: withCenter(params) }),
  payroll: id => axios.get(`/hr/attendance-periods/${id}/payroll`, { params: withCenter() }),
}

export const hrAdditionalDaysApi = {
  list: params => axios.get('/hr/additional-days', { params: withCenter(params) }),
  summary: params => axios.get('/hr/additional-days/summary', { params: withCenter(params) }),
  employees: params => axios.get('/hr/additional-days/employees', { params: withCenter(params) }),
  periods: () => axios.get('/hr/additional-days/periods', { params: withCenter() }),
  show: id => axios.get(`/hr/additional-days/${id}`, { params: withCenter() }),
  history: id => axios.get(`/hr/additional-days/${id}/history`, { params: withCenter() }),
  create: data => axios.post('/hr/additional-days', withCenter(data)),
  update: (id, data) => axios.put(`/hr/additional-days/${id}`, withCenter(data)),
  submit: id => axios.post(`/hr/additional-days/${id}/submit`, withCenter()),
  submitMany: ids => axios.post('/hr/additional-days/submit', withCenter({ ids })),
  cancel: (id, reason) => axios.post(`/hr/additional-days/${id}/cancel`, withCenter({ reason })),
}

export const hrAuditLogsApi = {
  list: params => axios.get('/hr/audit-logs', { params: withCenter(params) }),
}

export const fetchHrLookups = () => axios.get('/hr/lookups', { params: withCenter() })
