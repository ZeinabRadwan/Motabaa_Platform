import { hrLeaveRequestsApi } from '@/plugins/apis/hrRequest'

export const LEAVE_STATUS_COLORS = {
  pending: 'warning',
  approved: 'success',
  rejected: 'error',
  cancelled: 'secondary',
}

export const ATTENDANCE_DAY_STATUS_COLORS = {
  present: 'success',
  late: 'warning',
  early_departure: 'warning',
  late_early_departure: 'warning',
  absent: 'error',
  incomplete: 'error',
  pending: 'secondary',
  on_leave: 'info',
  holiday: 'primary',
  rest_day: 'secondary',
  day_off: 'secondary',
  exempt: 'secondary',
  excused: 'info',
  unscheduled: 'secondary',
  not_employed: 'secondary',
}

/** Statuses whose late / early values HR may change (the others can only be confirmed as reviewed). */
export const CORRECTABLE_DAY_STATUSES = ['present', 'late', 'early_departure', 'late_early_departure', 'absent', 'incomplete', 'excused']

export const ADDITIONAL_DAY_STATUS_COLORS = {
  draft: 'secondary',
  pending: 'warning',
  approved: 'info',
  rejected: 'error',
  cancelled: 'secondary',
  paid: 'success',
}

/** 1234.5 -> "1,234.50" (salary amounts, two decimals). */
export const formatAmount = value => {
  if (value === null || value === undefined || value === '')
    return '—'

  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export const PERIOD_STATUS_COLORS = {
  draft: 'secondary',
  processed: 'info',
  approved: 'success',
}

/** 2.5 -> "2.5", 3 -> "3", null -> "—". */
export const formatDays = value => {
  if (value === null || value === undefined || value === '')
    return '—'

  return String(Math.round(Number(value) * 100) / 100)
}

/** Saves a blob response (attachments, exports) under its file name. */
export const saveBlob = (response, name, type) => {
  const url = URL.createObjectURL(new Blob([response.data], { type: type || response.data?.type }))
  const link = document.createElement('a')

  link.href = url
  link.download = name
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

/** Employees, HR, requesters and approvers all download through the same authorized endpoint. */
export const downloadLeaveAttachment = (leaveId, file) => hrLeaveRequestsApi.attachment(leaveId, file.id)
  .then(response => saveBlob(response, file.name, file.mime_type))
  .catch(() => {})
