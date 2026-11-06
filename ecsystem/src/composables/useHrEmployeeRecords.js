import i18n from '@/plugins/i18n'
import { ref } from 'vue'

const notify = message => window.Snackbar?.value?.exposevisibleSnackbar?.(message || i18n.global.t('saved_successfully'), 'success')

/**
 * Effective-dated employee records (salaries, insurances, shift assignments): list, add a new row through a
 * dialog, cancel a scheduled row. Rows are immutable on the server; a correction is a new row.
 *
 * @param {object} api  { list(employeeId), create(employeeId, data), cancel(employeeId, id) }
 * @param {() => number} employeeId
 * @param {object} config
 * @param {() => object} config.emptyForm
 * @param {() => void} [config.onChanged]  e.g. reload the profile summary
 * @param {(form: object) => object} [config.toPayload]  map dialog-only fields before sending
 */
export function useHrEmployeeRecords(api, employeeId, { emptyForm, onChanged = () => {}, toPayload = form => ({ ...form }) }) {
  const rows = ref([])
  const loading = ref(false)
  const isDialogVisible = ref(false)
  const form = ref(emptyForm())
  const saving = ref(false)
  const fieldErrors = ref({})
  const cancelTarget = ref(null)

  const fetchRows = () => {
    loading.value = true

    return api.list(employeeId()).then(response => {
      rows.value = response.data.data
    }).catch(() => {
      rows.value = []
    }).finally(() => {
      loading.value = false
    })
  }

  const openDialog = () => {
    form.value = emptyForm()
    fieldErrors.value = {}
    isDialogVisible.value = true
  }

  const save = () => {
    saving.value = true
    fieldErrors.value = {}

    return api.create(employeeId(), toPayload(form.value)).then(response => {
      isDialogVisible.value = false
      notify(response.data?.message)
      fetchRows()
      onChanged()
    }).catch(error => {
      fieldErrors.value = error?.response?.data?.errors ?? {}
    }).finally(() => {
      saving.value = false
    })
  }

  const askCancel = row => {
    cancelTarget.value = row
  }

  const confirmCancel = () => {
    const row = cancelTarget.value

    cancelTarget.value = null
    if (!row)
      return

    api.cancel(employeeId(), row.id).then(response => {
      notify(response.data?.message)
      fetchRows()
      onChanged()
    }).catch(() => {})
  }

  const errorsOf = field => fieldErrors.value[field] ?? []

  return {
    rows,
    loading,
    fetchRows,
    isDialogVisible,
    form,
    saving,
    openDialog,
    save,
    errorsOf,
    cancelTarget,
    askCancel,
    confirmCancel,
  }
}
