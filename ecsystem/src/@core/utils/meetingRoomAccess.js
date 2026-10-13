import ability from '@/plugins/casl/ability'
import { isParentUser } from '@core/utils/staffSessionVisibility'
import { useSessionStore } from '@/stores/useSessionStore'

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
 * Parent accounts may open meeting routes; per-room access is enforced by the API.
 */
export function isParentMeetingParticipant() {
  if (isParentUser()) {
    return true
  }

  if (ability.can('parent', 'parent')) {
    return true
  }

  try {
    const user = useSessionStore().userData
    if (user?.role === 'parent' || user?.is_parent_user === true) {
      return true
    }
  }
  catch (e) {
    // Pinia unavailable outside setup
  }

  return false
}

/**
 * Staff need meeting permissions; parents reach meeting routes for assigned/private rooms
 * (backend enforces per-room access).
 */
export function canAccessMeetingRooms() {
  if (isParentMeetingParticipant()) {
    return true
  }

  return MEETING_PERMISSIONS.some(moduleCan)
}

export function canParticipateInMeetingRoomChat() {
  if (isParentMeetingParticipant()) {
    return true
  }

  return moduleCan('show_meetings') || moduleCan('edit_meetings') || moduleCan('admin_meetings')
}
