import { fetchHrLookups } from '@/plugins/apis/hrRequest'
import { registerAccountScopedReset } from '@core/utils/accountScope'
import { ref } from 'vue'

const EMPTY = {
  branch_scope: { restricted: false, branch_ids: [] },
  branches: [],
  departments: [],
  work_locations: [],
  shifts: [],
  attendance_policies: [],
  attendance_rule_options: {},
  attendance_defaults: {},
  job_titles: [],
  employment_statuses: [],
  attendance_modes: [],
  gps_policies: [],
  termination_reasons: [],
  insurance_categories: [],
  document_categories: [],
  document_types: [],
  change_types: [],
  staff_roles: [],
  request_types: [],
  approval_statuses: [],
  approver_types: [],
  auditable_types: [],
}

const lookups = ref({ ...EMPTY })
let pending = null

registerAccountScopedReset(() => {
  lookups.value = { ...EMPTY }
  pending = null
})

/**
 * Option lists shared by HR pages (branches, departments, enums), loaded once per account/center.
 * Call refresh() after creating or editing organization records.
 */
export function useHrLookups() {
  const load = (force = false) => {
    if (pending && !force)
      return pending

    pending = fetchHrLookups()
      .then(response => {
        lookups.value = { ...EMPTY, ...(response.data?.data ?? {}) }

        return lookups.value
      })
      .catch(error => {
        pending = null
        throw error
      })

    return pending
  }

  load().catch(() => {})

  const labelOf = (listKey, value) => lookups.value[listKey]?.find(option => option.value === value)?.title ?? value

  return {
    lookups,
    refresh: () => load(true),
    labelOf,
  }
}
