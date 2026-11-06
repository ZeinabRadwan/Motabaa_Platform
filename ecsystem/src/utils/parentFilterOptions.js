/** Keep the first `{ value, title }` option per value; drops options without a value. */
export function uniqueOptionsByValue(items) {
  if (!Array.isArray(items))
    return []

  const seen = new Set()

  return items.filter(item => {
    const value = item?.value
    if (value === null || value === undefined || value === '')
      return false

    const key = String(value)
    if (seen.has(key))
      return false
    seen.add(key)

    return true
  })
}

/**
 * Parent service type options, deduped by canonical category value and labelled
 * from `parent_treatment_plans.service_types.<value_with_underscores>` so they follow the UI locale.
 */
export function parentServiceTypeOptions(items, t, te) {
  return uniqueOptionsByValue(items).map(item => {
    const key = `parent_treatment_plans.service_types.${String(item.value).trim().replace(/\s+/g, '_')}`

    return { ...item, title: te(key) ? t(key) : (item.title ?? String(item.value)) }
  })
}
