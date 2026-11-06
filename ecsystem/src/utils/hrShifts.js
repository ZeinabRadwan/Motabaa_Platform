// Shift and schedule helpers. Dates are 'YYYY-MM-DD' strings handled in UTC so they never shift with the
// browser timezone; weekdays use the API numbering (0 = Sunday ... 6 = Saturday, the Saudi week start).

export const WEEKDAYS = [0, 1, 2, 3, 4, 5, 6]

export const SHIFT_TYPE_COLORS = {
  morning: '#F9A825',
  evening: '#6A1B9A',
  night: '#283593',
  rotating: '#00838F',
  custom: '#546E7A',
}

export const SHIFT_COLOR_PRESETS = ['#2E7D32', '#1565C0', '#6A1B9A', '#283593', '#F9A825', '#EF6C00', '#C62828', '#00838F', '#546E7A']

export const shiftColor = (color, type) => color || SHIFT_TYPE_COLORS[type] || SHIFT_TYPE_COLORS.custom

const parse = iso => {
  const [year, month, day] = iso.split('-').map(Number)

  return new Date(Date.UTC(year, month - 1, day))
}

export const toIsoDate = date => date.toISOString().slice(0, 10)

export const todayIso = () => {
  const now = new Date()

  return toIsoDate(new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate())))
}

export const addDays = (iso, days) => {
  const date = parse(iso)

  date.setUTCDate(date.getUTCDate() + days)

  return toIsoDate(date)
}

export const weekdayOf = iso => parse(iso).getUTCDay()

export const startOfWeek = iso => addDays(iso, -weekdayOf(iso))

export const startOfMonth = iso => `${iso.slice(0, 7)}-01`

export const endOfMonth = iso => {
  const date = parse(startOfMonth(iso))

  date.setUTCMonth(date.getUTCMonth() + 1)
  date.setUTCDate(0)

  return toIsoDate(date)
}

export const addMonths = (iso, months) => {
  const date = parse(startOfMonth(iso))

  date.setUTCMonth(date.getUTCMonth() + months)

  return toIsoDate(date)
}

export const dayOfMonth = iso => Number(iso.slice(8, 10))

export const formatDate = (iso, locale, options) => new Intl.DateTimeFormat(
  locale === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB',
  { timeZone: 'UTC', ...options },
).format(parse(iso))

/** 480 -> "8h", 510 -> "8h 30m" (localized units). */
export const formatMinutes = (minutes, t) => {
  if (minutes === null || minutes === undefined)
    return '—'
  const hours = Math.floor(minutes / 60)
  const rest = minutes % 60
  if (!rest)
    return t('hr.shifts.hours_short', { hours })
  if (!hours)
    return t('hr.shifts.minutes_short', { minutes: rest })

  return t('hr.shifts.hours_minutes_short', { hours, minutes: rest })
}

/** Minutes between two "HH:mm" times, crossing midnight when the end is not after the start. */
export const spanMinutes = (start, end) => {
  if (!start || !end)
    return null

  const toMinutes = time => {
    const [hours, minutes] = time.split(':').map(Number)

    return hours * 60 + minutes
  }

  const span = (toMinutes(end) - toMinutes(start) + 1440) % 1440

  return span === 0 ? 1440 : span
}

export const isOvernight = (start, end) => !!start && !!end && end <= start

/** Colour, icon and label key of a resolved schedule day (see ScheduledShiftDay on the API). */
export const dayAppearance = day => {
  if (day.status === 'working')
    return { color: shiftColor(day.color, day.shift_type), icon: day.source === 'exception' ? 'tabler-calendar-event' : null, labelKey: null }
  if (day.status === 'rest')
    return { color: null, icon: 'tabler-moon', labelKey: 'hr.schedule.status.rest' }
  if (day.status === 'off')
    return { color: null, icon: day.source === 'exception' ? 'tabler-beach' : 'tabler-minus', labelKey: day.source === 'exception' ? 'hr.schedule.status.day_off' : 'hr.schedule.status.off' }
  if (day.status === 'not_employed')
    return { color: null, icon: null, labelKey: 'hr.schedule.status.not_employed' }

  return { color: null, icon: 'tabler-help-circle', labelKey: 'hr.schedule.status.unscheduled' }
}
