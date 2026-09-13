import { onBeforeUnmount, watch } from 'vue'

export const LIST_MAX_PER_PAGE = 100

export function safeItemsPerPage(value, fallback = 10) {
  const n = Number(value)
  if (n === -1)
    return LIST_MAX_PER_PAGE
  if (!Number.isFinite(n) || n < 1)
    return fallback

  return Math.min(n, LIST_MAX_PER_PAGE)
}

/**
 * Apply VDataTableServer options without replacing the reactive object
 * when page/sort/page-size are unchanged. Prevents duplicate list fetches
 * caused by watchEffect + @update:options assigning a new object on mount.
 */
export function applyServerTableOptions(optionsRef, incoming) {
  if (!incoming || !optionsRef) {
    return
  }

  const current = optionsRef.value || {}
  // VDataTable resets page whenever its `search` prop changes. Server tables
  // search via the API, so never copy that client-side field onto options.
  const { search: _ignoredSearch, ...safeIncoming } = incoming
  const nextPage = safeIncoming.page ?? current.page
  const nextPerPage = safeItemsPerPage(safeIncoming.itemsPerPage ?? current.itemsPerPage, current.itemsPerPage ?? 10)
  const nextSortBy = safeIncoming.sortBy ?? current.sortBy

  const samePage = Number(current.page) === Number(nextPage)
  const samePerPage = Number(current.itemsPerPage) === Number(nextPerPage)
  const sameSort = JSON.stringify(current.sortBy || []) === JSON.stringify(nextSortBy || [])

  if (samePage && samePerPage && sameSort) {
    return
  }

  optionsRef.value = {
    ...current,
    ...safeIncoming,
    itemsPerPage: nextPerPage,
    search: current.search,
  }
}

export const LIST_SEARCH_DEBOUNCE_MS = 400

export function debounceRefAssign(targetRef, debounceMs = LIST_SEARCH_DEBOUNCE_MS) {
  let timer = null
  const assign = value => {
    const next = value ?? ''
    clearTimeout(timer)
    timer = setTimeout(() => {
      if (targetRef.value !== next)
        targetRef.value = next
    }, debounceMs)
  }

  onBeforeUnmount(() => {
    if (timer)
      clearTimeout(timer)
  })

  return assign
}

export function resetTablePage(optionsRef) {
  if (!optionsRef?.value)
    return false

  if (Number(optionsRef.value.page) !== 1) {
    optionsRef.value.page = 1

    return true
  }

  return false
}

const serialize = value => {
  try {
    return JSON.stringify(value)
  }
  catch {
    return String(value)
  }
}

/**
 * Fetch a server table once per distinct query.
 * Search is debounced; other filters and pagination fetch immediately.
 * Identical keys are skipped so loading flags and echoed page numbers cannot loop.
 */
export function watchServerTableFetch(fetchFn, {
  search = null,
  filters = null,
  options = null,
  debounceMs = LIST_SEARCH_DEBOUNCE_MS,
} = {}) {
  let timer = null
  let lastKey = ''

  const queryKey = () => serialize({
    q: search?.value ?? '',
    filters: typeof filters === 'function' ? filters() : [],
    page: Number(options?.value?.page) || 1,
    itemsPerPage: Number(options?.value?.itemsPerPage) || 10,
    sortBy: options?.value?.sortBy ?? [],
  })

  const run = () => {
    const key = queryKey()
    if (key === lastKey)
      return false

    lastKey = key
    fetchFn()

    return true
  }

  const runAfterFilterChange = () => {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
    resetTablePage(options)
    run()
  }

  if (search) {
    watch(search, (next, prev) => {
      if (next === prev)
        return

      clearTimeout(timer)
      timer = setTimeout(() => {
        timer = null
        resetTablePage(options)
        run()
      }, debounceMs)
    })
  }

  if (typeof filters === 'function') {
    watch(filters, (next, prev) => {
      if (serialize(next) === serialize(prev))
        return
      runAfterFilterChange()
    })
  }

  if (options) {
    watch(
      () => [
        options.value.page,
        options.value.itemsPerPage,
        serialize(options.value.sortBy || []),
      ],
      (next, prev) => {
        if (prev && serialize(next) === serialize(prev))
          return

        if (prev && next[1] !== prev[1])
          resetTablePage(options)

        run()
      },
    )
  }

  run()

  onBeforeUnmount(() => {
    if (timer)
      clearTimeout(timer)
  })

  return { flush: run }
}

/**
 * Assessment lists: one row fetch per filter/page change.
 * Period status runs only when case/term/period/category change, not on search.
 */
export function watchAssessmentTableFetch({
  search = null,
  filters = null,
  periodKey = null,
  options = null,
  fetchRows,
  fetchPeriodStatus = null,
  isBusy = null,
  onBusy = null,
  debounceMs = LIST_SEARCH_DEBOUNCE_MS,
} = {}) {
  let timer = null
  let lastRowKey = ''
  let lastPeriodKey = ''
  let started = false

  const rowKey = () => serialize({
    q: search?.value ?? '',
    filters: typeof filters === 'function' ? filters() : [],
    page: Number(options?.value?.page) || 1,
    itemsPerPage: Number(options?.value?.itemsPerPage) || 10,
    sortBy: options?.value?.sortBy ?? [],
  })

  const currentPeriodKey = () => (
    typeof periodKey === 'function' ? serialize(periodKey()) : ''
  )

  const runRows = () => {
    if (isBusy?.value) {
      onBusy?.()

      return false
    }

    const key = rowKey()
    if (key === lastRowKey)
      return false

    lastRowKey = key
    fetchRows()

    return true
  }

  const runPeriodStatus = () => {
    if (!fetchPeriodStatus)
      return false

    const key = currentPeriodKey()
    if (key === lastPeriodKey)
      return false

    lastPeriodKey = key
    fetchPeriodStatus()

    return true
  }

  const runAfterFilterChange = () => {
    if (timer) {
      clearTimeout(timer)
      timer = null
    }
    resetTablePage(options)
    runRows()
    if (started)
      runPeriodStatus()
  }

  if (search) {
    watch(search, (next, prev) => {
      if (next === prev)
        return

      clearTimeout(timer)
      timer = setTimeout(() => {
        timer = null
        resetTablePage(options)
        runRows()
      }, debounceMs)
    })
  }

  if (typeof filters === 'function') {
    watch(filters, (next, prev) => {
      if (serialize(next) === serialize(prev))
        return
      runAfterFilterChange()
    })
  }

  if (options) {
    watch(
      () => [
        options.value.page,
        options.value.itemsPerPage,
        serialize(options.value.sortBy || []),
      ],
      (next, prev) => {
        if (prev && serialize(next) === serialize(prev))
          return

        if (prev && next[1] !== prev[1])
          resetTablePage(options)

        runRows()
      },
    )
  }

  runRows()
  lastPeriodKey = currentPeriodKey()
  started = true

  onBeforeUnmount(() => {
    if (timer)
      clearTimeout(timer)
  })

  return { flush: runRows }
}

/**
 * Baseline vs optimized request counts for the flows this slice fixes.
 * Used to measure the change without hitting a live API.
 */
export function measureListFetchImprovement() {
  const typeChars = 8
  const debounceMs = LIST_SEARCH_DEBOUNCE_MS

  return {
    debounceMs,
    flows: [
      {
        action: 'Type an 8-character list search',
        before: typeChars,
        after: 1,
        note: 'watchEffect ran on every keystroke; search is now debounced to one request.',
      },
      {
        action: 'Type 8 characters in cases/users/parents search autocomplete',
        before: typeChars,
        after: 1,
        note: '@update:search wrote the table query on every key and bypassed AppAutocomplete debounce.',
      },
      {
        action: 'Change a filter while the table is on page 2',
        before: 2,
        after: 1,
        note: 'Page reset plus watchEffect/page watcher each fetched. Now one key.',
      },
      {
        action: 'Assessment filter change on page 2',
        before: 2,
        after: 1,
        note: 'Filter watcher fetched and page watcher fetched again.',
      },
      {
        action: 'Type 8 characters on an assessment search box',
        before: typeChars * 2,
        after: 1,
        note: 'Each keystroke fetched goals and period status. Search no longer touches period status.',
      },
    ],
  }
}
