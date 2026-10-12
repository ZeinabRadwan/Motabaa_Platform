import ability from '@/plugins/casl/ability'
import { isParentUser } from '@core/utils/staffSessionVisibility'

const MEETING_PERMISSIONS = [
  'access_meetings',
  'show_meetings',
  'edit_meetings',
  'admin_meetings',
]

function moduleCan(action) {
  const moduleKey = action.includes('_') ? action.slice(action.indexOf('_') + 1) : action
  if (ability.can(`admin_${moduleKey}`, `admin_${moduleKey}`)) {
    return true
  }

  return ability.can(action, action) || ability.can(action, moduleKey)
}

/**
 * Staff need meeting permissions; parents reach meeting routes for assigned/private rooms
 * (backend enforces per-room access).
 */
export function canAccessMeetingRooms() {
  if (isParentUser()) {
    return true
  }

  return MEETING_PERMISSIONS.some(moduleCan)
}

export function canParticipateInMeetingRoomChat() {
  if (isParentUser()) {
    return true
  }

  return moduleCan('show_meetings') || moduleCan('edit_meetings') || moduleCan('admin_meetings')
}
