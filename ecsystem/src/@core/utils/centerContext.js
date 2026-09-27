const parsePositiveId = value => {
  const id = Number(value)

  return Number.isFinite(id) && id > 0 ? id : null
}

export const currentCenterId = () => {
  const fromStorage = parsePositiveId(localStorage.getItem('center'))
  if (fromStorage)
    return fromStorage

  try {
    const user = JSON.parse(localStorage.getItem('userData') || 'null')
    const centers = Array.isArray(user?.centers) ? user.centers : []
    const preferred = centers.find(center => Number(center?.status) === 1) || centers[0]

    return parsePositiveId(preferred?.id)
  }
  catch (e) {
    return null
  }
}

export const withResolvedCenterId = value => {
  const requested = parsePositiveId(value)

  return requested || currentCenterId()
}
