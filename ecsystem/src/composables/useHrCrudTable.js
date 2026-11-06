import i18n from '@/plugins/i18n'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { ref } from 'vue'

const notify = (message, type = 'success') => window.Snackbar?.value?.exposevisibleSnackbar?.(message, type)

/**
 * Server table + create/edit dialog + delete/restore for HR resources exposed through hrRequest's `resource()`.
 * Validation and domain errors (422/403) are already surfaced by the axios interceptor.
 *
 * @param {object} api       { list, create, update, remove, restore }
 * @param {object} config
 * @param {() => object} config.filters  extra list params (reactive getter)
 * @param {() => object} config.emptyForm
 * @param {(item: object) => object} config.toForm
 * @param {(form: object) => object} [config.toPayload]
 * @param {() => void} [config.onSaved]  e.g. refresh shared lookups
 */
export function useHrCrudTable(api, { filters = () => ({}), emptyForm, toForm, toPayload = form => form, onSaved = () => {} }) {
  const items = ref([])
  const total = ref(0)
  const loading = ref(false)
  const search = ref('')
  const showTrashed = ref(false)
  const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })

  const isDialogVisible = ref(false)
  const editingId = ref(null)
  const form = ref(emptyForm())
  const saving = ref(false)

  const confirm = ref({ visible: false, action: null, item: null })

  const fetchItems = () => {
    loading.value = true

    return api.list({
      ...filters(),
      q: search.value || undefined,
      trashed: showTrashed.value ? 1 : undefined,
      options: options.value,
    }).then(response => {
      items.value = response.data.data
      total.value = response.data.total
    }).catch(() => {
      items.value = []
      total.value = 0
    }).finally(() => {
      loading.value = false
    })
  }

  watchServerTableFetch(fetchItems, {
    search,
    filters: () => [showTrashed.value, filters()],
    options,
  })

  const updateOptions = incoming => applyServerTableOptions(options, incoming)

  const openDialog = (item = null) => {
    editingId.value = item?.id ?? null
    form.value = item ? toForm(item) : emptyForm()
    isDialogVisible.value = true
  }

  const openCreateDialog = () => openDialog()

  const save = () => {
    saving.value = true

    const payload = toPayload({ ...form.value })
    const request = editingId.value ? api.update(editingId.value, payload) : api.create(payload)

    return request.then(() => {
      isDialogVisible.value = false
      notify(i18n.global.t('saved_successfully'))
      onSaved()
      fetchItems()
    }).catch(() => {}).finally(() => {
      saving.value = false
    })
  }

  const askConfirm = (action, item) => {
    confirm.value = { visible: true, action, item }
  }

  const runConfirmed = () => {
    const { action, item } = confirm.value

    confirm.value = { visible: false, action: null, item: null }
    if (!item)
      return

    const request = action === 'restore' ? api.restore(item.id) : api.remove(item.id)

    request.then(response => {
      notify(response.data?.message || i18n.global.t(action === 'restore' ? 'hr.common.restored' : 'Record deleted successfully'))
      onSaved()
      fetchItems()
    }).catch(() => {})
  }

  return {
    items,
    total,
    loading,
    search,
    showTrashed,
    options,
    updateOptions,
    fetchItems,
    isDialogVisible,
    editingId,
    form,
    saving,
    openDialog,
    openCreateDialog,
    save,
    confirm,
    askConfirm,
    runConfirmed,
  }
}
