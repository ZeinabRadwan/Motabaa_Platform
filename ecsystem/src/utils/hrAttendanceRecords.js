// Central attendance records (electronic and manual). Keys mirror AttendanceRecord on the API.

export const ATTENDANCE_STATUSES = [
  'present',
  'late',
  'early_departure',
  'late_early_departure',
  'missing_check_in',
  'missing_check_out',
  'off_schedule',
  'in_progress',
]

export const ATTENDANCE_STATUS_COLORS = {
  present: 'success',
  late: 'warning',
  early_departure: 'warning',
  late_early_departure: 'error',
  missing_check_in: 'error',
  missing_check_out: 'error',
  off_schedule: 'secondary',
  in_progress: 'info',
}

export const ATTENDANCE_METHODS = ['electronic', 'manual']

export const ENTRY_REASONS = [
  'manual_attendance_method',
  'forgot_check_in',
  'forgot_check_out',
  'system_or_device_issue',
  'field_work',
  'official_assignment',
  'no_website_access',
  'other',
]

// Fields of the change history, in display order (HrManualAttendance::TRACKED).
export const HISTORY_FIELDS = [
  'shift',
  'check_in',
  'check_out',
  'status',
  'attendance_status',
  'late_minutes',
  'early_departure_minutes',
  'worked_minutes',
  'entry_reason',
  'entry_reason_notes',
  'notes',
]

/**
 * Whether the signed-in user may change the record (`limits` from the summary endpoint). The server enforces the
 * same rules; this only hides actions that would be refused.
 */
export const canChangeRecord = (record, limits) => {
  if (!limits?.can_edit || record.status === 'checked_in')
    return false
  if (limits.locked_until && record.attendance_date <= limits.locked_until)
    return false
  if (!limits.can_enter_historical && record.attendance_date < limits.oldest_date)
    return false

  return !(limits.own_employee_id && record.employee?.id === limits.own_employee_id && !limits.can_edit_own)
}
