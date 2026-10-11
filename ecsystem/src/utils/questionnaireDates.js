/**
 * Normalize a flatpickr / API date value to Y-m-d for questionnaire task boundaries.
 */
export function formatQuestionnaireBoundaryDate(value) {
  if (value === null || value === undefined || value === '')
    return ''

  if (value instanceof Date && ! Number.isNaN(value.getTime()))
    return value.toISOString().slice(0, 10)

  const raw = String(value).trim()
  const isoPrefix = raw.match(/^(\d{4}-\d{2}-\d{2})/)
  if (isoPrefix)
    return isoPrefix[1]

  const parsed = new Date(raw)
  if (! Number.isNaN(parsed.getTime()))
    return parsed.toISOString().slice(0, 10)

  return raw
}
