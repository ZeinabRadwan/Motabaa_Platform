const HR_TIMEZONE = 'Asia/Riyadh'

/**
 * API timestamps are UTC ISO strings; HR screens show them in Riyadh time, Gregorian calendar,
 * Latin digits (so numbers line up in both RTL and LTR tables).
 */
export const formatHrDateTime = (value, locale = 'ar') => {
  if (!value)
    return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime()))
    return String(value)

  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: HR_TIMEZONE,
  }).format(date)
}

/** SAR amounts with two decimals and Latin digits; '—' when empty. */
export const formatHrMoney = (value, locale = 'ar') => {
  if (value === null || value === undefined || value === '')
    return '—'

  return new Intl.NumberFormat(locale === 'ar' ? 'ar-SA-u-nu-latn' : 'en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(value))
}

export const EMPLOYMENT_STATUS_COLORS = {
  active: 'success',
  probation: 'info',
  suspended: 'warning',
  terminated: 'error',
}

export const APPROVAL_STATUS_COLORS = {
  pending: 'warning',
  approved: 'success',
  rejected: 'error',
  cancelled: 'secondary',
  skipped: 'secondary',
}
