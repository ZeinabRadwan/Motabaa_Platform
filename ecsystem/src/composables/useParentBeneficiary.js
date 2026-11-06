import { casesApi } from '@/plugins/apis/casesReqest'
import {
  accountScopeKey,
  captureAccountScope,
  registerAccountScopedReset,
} from '@core/utils/accountScope'
import { isParentUser } from '@core/utils/staffSessionVisibility'
import { readonly, ref } from 'vue'

const STORAGE_KEY = 'athar_parent_selected_case_id'
const STORAGE_SNAPSHOT_KEY = 'athar_parent_selected_case_snapshot'

const selectedBeneficiaryId = ref(null)
const selectedBeneficiary = ref(null)
const linkedBeneficiaries = ref([])
const linkedBeneficiariesLoading = ref(false)
const linkedBeneficiariesError = ref(false)
const linkedBeneficiariesLoaded = ref(false)

// Module state outlives SPA navigation, so it is bound to one `userId:centerId`.
let stateScope = null
let generation = 0
let storageHydrated = false
let fetchPromise = null

const scopedStorageKey = key => `${key}:${stateScope}`

export function resetParentBeneficiaryState() {
  generation += 1
  stateScope = null
  storageHydrated = false
  fetchPromise = null
  selectedBeneficiaryId.value = null
  selectedBeneficiary.value = null
  linkedBeneficiaries.value = []
  linkedBeneficiariesLoading.value = false
  linkedBeneficiariesError.value = false
  linkedBeneficiariesLoaded.value = false
}

registerAccountScopedReset(resetParentBeneficiaryState)

function ensureAccountScope() {
  const scope = accountScopeKey()
  if (scope === stateScope)
    return

  resetParentBeneficiaryState()
  stateScope = scope
}

function hydrateFromSessionStorage() {
  if (storageHydrated || !isParentUser())
    return

  storageHydrated = true

  try {
    // Unscoped keys from older builds may belong to another account.
    sessionStorage.removeItem(STORAGE_KEY)
    sessionStorage.removeItem(STORAGE_SNAPSHOT_KEY)

    const raw = sessionStorage.getItem(scopedStorageKey(STORAGE_KEY))
    if (raw != null && raw !== '') {
      const id = Number(raw)
      if (!Number.isNaN(id) && id > 0)
        selectedBeneficiaryId.value = id
    }

    const snapshotRaw = sessionStorage.getItem(scopedStorageKey(STORAGE_SNAPSHOT_KEY))
    if (snapshotRaw) {
      const snapshot = JSON.parse(snapshotRaw)
      if (snapshot?.id && Number(snapshot.id) === Number(selectedBeneficiaryId.value))
        selectedBeneficiary.value = snapshot
    }
  }
  catch {
    // sessionStorage may be unavailable
  }
}

function persistBeneficiaryId(id) {
  if (!isParentUser())
    return

  selectedBeneficiaryId.value = id ?? null

  try {
    if (id == null || id === '')
      sessionStorage.removeItem(scopedStorageKey(STORAGE_KEY))
    else
      sessionStorage.setItem(scopedStorageKey(STORAGE_KEY), String(id))
  }
  catch {
    // ignore
  }
}

function persistBeneficiarySnapshot(item) {
  if (!isParentUser())
    return

  try {
    if (!item?.id) {
      sessionStorage.removeItem(scopedStorageKey(STORAGE_SNAPSHOT_KEY))

      return
    }

    sessionStorage.setItem(scopedStorageKey(STORAGE_SNAPSHOT_KEY), JSON.stringify({
      id: item.id,
      name: item.name,
      picture: item.picture ?? null,
      disability_type_ids: item.disability_type_ids ?? [],
    }))
  }
  catch {
    // ignore
  }
}

/**
 * Shared beneficiary context for parent accounts (scoped to the signed-in parent + center).
 */
export function useParentBeneficiary() {
  ensureAccountScope()
  hydrateFromSessionStorage()

  const selectBeneficiary = item => {
    if (!item?.id)
      return

    ensureAccountScope()
    selectedBeneficiary.value = item
    persistBeneficiaryId(item.id)
    persistBeneficiarySnapshot(item)
  }

  const clearBeneficiary = () => {
    ensureAccountScope()
    selectedBeneficiary.value = null
    persistBeneficiaryId(null)
    persistBeneficiarySnapshot(null)
  }

  const syncBeneficiaryId = id => {
    if (id == null || id === '') {
      clearBeneficiary()

      return
    }

    ensureAccountScope()

    const numericId = Number(id)

    persistBeneficiaryId(numericId)

    const fromList = linkedBeneficiaries.value.find(row => Number(row.id) === numericId)
    if (fromList)
      selectedBeneficiary.value = fromList
  }

  return {
    selectedBeneficiaryId: readonly(selectedBeneficiaryId),
    selectedBeneficiary: readonly(selectedBeneficiary),
    linkedBeneficiaries: readonly(linkedBeneficiaries),
    linkedBeneficiariesLoading: readonly(linkedBeneficiariesLoading),
    linkedBeneficiariesError: readonly(linkedBeneficiariesError),
    linkedBeneficiariesLoaded: readonly(linkedBeneficiariesLoaded),
    selectBeneficiary,
    clearBeneficiary,
    syncBeneficiaryId,
    fetchLinkedBeneficiaries,
    resolveBeneficiarySelection,
    invalidateLinkedBeneficiariesCache,
  }
}

export function invalidateLinkedBeneficiariesCache() {
  generation += 1
  fetchPromise = null
  linkedBeneficiaries.value = []
  linkedBeneficiariesLoading.value = false
  linkedBeneficiariesLoaded.value = false
}

/**
 * Single in-flight request for linked cases (backend filters to parent-accessible cases).
 * Responses issued under a previous account / center are discarded.
 */
export async function fetchLinkedBeneficiaries(force = false) {
  if (!isParentUser())
    return []

  ensureAccountScope()

  if (!force && linkedBeneficiariesLoaded.value && linkedBeneficiaries.value.length > 0)
    return linkedBeneficiaries.value

  if (!force && fetchPromise)
    return fetchPromise

  const requestGeneration = generation
  const identity = captureAccountScope()
  const isCurrent = () => requestGeneration === generation && identity.isCurrent()

  linkedBeneficiariesLoading.value = true
  linkedBeneficiariesError.value = false

  const request = casesApi().fetchAll({
    page: 1,
    options: { page: 1, itemsPerPage: 50, sortBy: [], groupBy: [] },
  }).then(response => {
    if (!isCurrent())
      return []

    linkedBeneficiaries.value = response.data?.data ?? []
    linkedBeneficiariesLoaded.value = true

    return linkedBeneficiaries.value
  }).catch(() => {
    if (!isCurrent())
      return []

    linkedBeneficiaries.value = []
    linkedBeneficiariesError.value = true

    return []
  }).finally(() => {
    if (fetchPromise === request) {
      linkedBeneficiariesLoading.value = false
      fetchPromise = null
    }
  })

  fetchPromise = request

  return request
}

/**
 * Validates stored / route case id against the current parent's list; auto-selects when only one child.
 *
 * @param {{ routeCaseId?: number|string|null }} options
 */
export async function resolveBeneficiarySelection(options = {}) {
  const { selectBeneficiary, clearBeneficiary, selectedBeneficiaryId } = useParentBeneficiary()
  const requestGeneration = generation

  const list = await fetchLinkedBeneficiaries()

  // The account / center changed while loading: never apply that result.
  if (requestGeneration !== generation)
    return { list: linkedBeneficiaries.value, selected: selectedBeneficiary.value, clearedInvalid: false }

  if (list.length === 0) {
    clearBeneficiary()

    return { list, selected: null, clearedInvalid: false }
  }

  const preferredRaw = options.routeCaseId ?? selectedBeneficiaryId.value
  let clearedInvalid = false

  if (preferredRaw != null && preferredRaw !== '') {
    const preferredId = Number(preferredRaw)
    const match = list.find(row => Number(row.id) === preferredId)

    if (match) {
      selectBeneficiary(match)

      return { list, selected: match, clearedInvalid: false }
    }

    if (selectedBeneficiaryId.value != null) {
      clearBeneficiary()
      clearedInvalid = true
    }
  }

  if (list.length === 1) {
    selectBeneficiary(list[0])

    return { list, selected: list[0], clearedInvalid }
  }

  return { list, selected: null, clearedInvalid }
}
