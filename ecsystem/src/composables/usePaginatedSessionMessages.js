/**
 * Paginated session/meeting message list with "load older" support (API max 50 per page).
 */
export function usePaginatedSessionMessages({
  fetchAll,
  getParams,
  searchQuery,
  perPage = 50,
}) {
  const messages = ref([])
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const loading = ref(false)
  const loadingMore = ref(false)

  const hasOlderMessages = computed(() => currentPage.value < lastPage.value)

  const applyResponse = (response, reset) => {
    const chunk = response.data?.data ?? []
    lastPage.value = response.data?.lastPage ?? 1
    total.value = response.data?.total ?? chunk.length

    if (reset) {
      messages.value = chunk
      currentPage.value = response.data?.currentPage ?? 1

      return
    }

    const seen = new Set(messages.value.map(m => m.id))
    messages.value.push(...chunk.filter(m => !seen.has(m.id)))
    currentPage.value = response.data?.currentPage ?? currentPage.value
  }

  const fetchMessages = (reset = true) => {
    if (reset)
      loading.value = true
    else
      loadingMore.value = true

    const page = reset ? 1 : currentPage.value + 1

    return fetchAll({
      ...getParams(),
      q: searchQuery?.value ?? '',
      page,
      options: { itemsPerPage: perPage },
    }).then(response => {
      applyResponse(response, reset)
    }).finally(() => {
      loading.value = false
      loadingMore.value = false
    })
  }

  const loadOlderMessages = () => {
    if (loadingMore.value || !hasOlderMessages.value)
      return Promise.resolve()

    return fetchMessages(false)
  }

  return {
    messages,
    fetchMessages,
    loadOlderMessages,
    hasOlderMessages,
    loading,
    loadingMore,
    total,
  }
}
