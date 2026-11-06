/** Location verification statuses (hr_location_checks.status) as returned by the API. */
export const LOCATION_STATUSES = [
  'within_radius',
  'outside_radius',
  'permission_denied',
  'gps_unavailable',
  'verification_failed',
  'pending',
]

export const LOCATION_STATUS_COLORS = {
  within_radius: 'success',
  outside_radius: 'error',
  permission_denied: 'error',
  gps_unavailable: 'warning',
  verification_failed: 'warning',
  pending: 'secondary',
  not_monitored: 'secondary',
}

export const LOCATION_SOURCES = ['web', 'mobile_foreground', 'mobile_background', 'system']
