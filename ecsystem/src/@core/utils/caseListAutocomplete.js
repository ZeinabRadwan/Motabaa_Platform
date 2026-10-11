import { LIST_MAX_PER_PAGE } from '@core/utils/tableFetch'

/**
 * Build GET /cases params aligned with /cases/list (not table pagination defaults).
 */
export function caseListFetchParams({
  q = '',
  page = 1,
  itemsPerPage = LIST_MAX_PER_PAGE,
  isParent = false,
  specialistTeacherId = '',
} = {}) {
  const params = {
    q,
    page,
    options: {
      page,
      itemsPerPage,
    },
  }

  if (!isParent) {
    params.status = 'active'
    params.specialist_teacher_id = specialistTeacherId
  }

  return params
}

const mapCaseRows = rows =>
  (Array.isArray(rows) ? rows : []).map(row => ({
    id: row.id,
    name: row.name,
  }))

/**
 * Case dropdown for pages that must use GET /cases like /cases/list (not /cases/select-items).
 */
export async function searchCasesListDropdown(casesRequest, params = {}, { isParent = false } = {}) {
  const q = (params.q ?? '').toString()
  const preload = params.preload === 1 || params.preload === true
  const perPage = Math.min(
    Math.max(Number(params.limit) || LIST_MAX_PER_PAGE, 1),
    LIST_MAX_PER_PAGE,
  )

  const first = await casesRequest.fetchAll(caseListFetchParams({
    q,
    page: 1,
    itemsPerPage: perPage,
    isParent,
  }))

  let items = mapCaseRows(first.data?.data)
  const total = Number(first.data?.total) || items.length
  const lastPage = Number(first.data?.lastPage) || 1

  if (preload && lastPage > 1) {
    for (let page = 2; page <= lastPage; page++) {
      const next = await casesRequest.fetchAll(caseListFetchParams({
        q,
        page,
        itemsPerPage: perPage,
        isParent,
      }))
      items = items.concat(mapCaseRows(next.data?.data))
    }
  }

  return { data: { data: items, total } }
}

/** @deprecated Use searchCasesListDropdown for /cases/list parity via GET /cases */
export const searchCaseListItems = (casesRequest, params = {}) =>
  casesRequest.selectItems(params)
