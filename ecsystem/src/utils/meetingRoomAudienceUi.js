import i18n from '@/plugins/i18n/index.js'

export const MEETING_AUDIENCE_OPTIONS = [
  {
    value: 'general',
    icon: 'tabler-users',
    hintKey: 'rooms.audience_hint_public',
  },
  {
    value: 'parents',
    icon: 'tabler-user-check',
    hintKey: 'rooms.audience_hint_parents',
  },
  {
    value: 'link_invite',
    icon: 'tabler-link',
    hintKey: 'rooms.audience_hint_link_invite',
  },
]

export function meetingAudienceLabel(value) {
  const map = {
    general: 'rooms.audience_general',
    parents: 'rooms.audience_parents',
    link_invite: 'rooms.audience_link_invite',
  }

  return i18n.global.t(map[value] || map.general)
}

export function meetingAudienceBadgeLabel(value) {
  const map = {
    general: 'rooms.audience_badge_general',
    parents: 'rooms.audience_badge_parents',
    link_invite: 'rooms.audience_badge_link_invite',
  }

  return i18n.global.t(map[value] || map.general)
}

export function meetingAudienceBadgeClass(value) {
  if (value === 'parents')
    return 'meeting-room-card__badge--audience-parents'
  if (value === 'link_invite')
    return 'meeting-room-card__badge--audience-link'

  return 'meeting-room-card__badge--audience-general'
}

export function meetingAudienceOption(value) {
  return MEETING_AUDIENCE_OPTIONS.find(o => o.value === value) || MEETING_AUDIENCE_OPTIONS[0]
}

export function meetingAudienceFilterItems() {
  return [
    {
      title: i18n.global.t('rooms.filter_all_access_types'),
      value: 'all',
    },
    ...MEETING_AUDIENCE_OPTIONS.map(opt => ({
      title: meetingAudienceLabel(opt.value),
      value: opt.value,
    })),
  ]
}

export function meetingRoomStatusFilterItems() {
  return [
    {
      title: i18n.global.t('rooms.filter_all_statuses'),
      value: 'all',
    },
    {
      title: i18n.global.t('active_status'),
      value: 'active',
    },
    {
      title: i18n.global.t('Inactive'),
      value: 'inactive',
    },
  ]
}
