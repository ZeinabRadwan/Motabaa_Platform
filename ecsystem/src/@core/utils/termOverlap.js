export const toDateOnly = value => {
  if (!value)
    return ''

  const match = String(value).trim().match(/^(\d{4}-\d{2}-\d{2})/)

  return match ? match[1] : ''
}

export const isStartAfterEnd = (startsAt, endsAt) => {
  const start = toDateOnly(startsAt)
  const end = toDateOnly(endsAt)

  return Boolean(start && end && start > end)
}

/**
 * Same interval rule as Term::overlapping:
 * existing.starts_at < new.ends_at AND existing.ends_at > new.starts_at
 * Terms that only touch on the same day are allowed.
 */
export const datesOverlap = (startA, endA, startB, endB) => {
  const aStart = toDateOnly(startA)
  const aEnd = toDateOnly(endA)
  const bStart = toDateOnly(startB)
  const bEnd = toDateOnly(endB)

  if (!aStart || !aEnd || !bStart || !bEnd)
    return false

  return aStart < bEnd && aEnd > bStart
}

export const findOverlappingTerm = (terms, startsAt, endsAt, ignoreId = 0) => {
  const currentId = Number(ignoreId) || 0

  return (terms || []).find(term => {
    if (!term || term.deleted_at)
      return false

    if (currentId > 0 && Number(term.id) === currentId)
      return false

    return datesOverlap(term.starts_at, term.ends_at, startsAt, endsAt)
  }) || null
}

export const formatTermDisplayDate = (value, locale = 'ar') => {
  const iso = toDateOnly(value)
  if (!iso)
    return ''

  const date = new Date(`${iso}T00:00:00`)
  if (Number.isNaN(date.getTime()))
    return iso

  const lang = String(locale || 'ar').toLowerCase().startsWith('en') ? 'en-GB' : 'ar'

  return date.toLocaleDateString(lang, {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    numberingSystem: 'latn',
  })
}

const escapeHtml = value => String(value || '')
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')

export const buildTermOverlapHtml = (title, startsAt, endsAt, header, locale = 'ar') => {
  const from = formatTermDisplayDate(startsAt, locale)
  const to = formatTermDisplayDate(endsAt, locale)

  return `${header}<br />${escapeHtml(title)}<br />${from} - ${to}`
}

export const formatTermOverlapApiMessage = (translatedMessage, locale = 'ar') => {
  const text = String(translatedMessage || '').trim()
  const datePair = text.match(/^(.*)\s+\((\d{4}-\d{2}-\d{2})\s*-\s*(\d{4}-\d{2}-\d{2})\)$/)

  if (!datePair)
    return translatedMessage

  const beforeDates = datePair[1]
  const colon = beforeDates.lastIndexOf(':')
  if (colon === -1)
    return translatedMessage

  const header = beforeDates.slice(0, colon + 1).trim()
  const title = beforeDates.slice(colon + 1).trim()
  if (!header || !title)
    return translatedMessage

  return buildTermOverlapHtml(title, datePair[2], datePair[3], header, locale)
}

export const translateApiValidationMessage = (message, translate, locale = 'ar') => {
  let text = String(message || '')
  const wrapped = text.match(/##(.*?)&&/)

  if (wrapped)
    text = text.replace(`##${wrapped[1]}&&`, translate(wrapped[1]))

  return formatTermOverlapApiMessage(text, locale)
}
