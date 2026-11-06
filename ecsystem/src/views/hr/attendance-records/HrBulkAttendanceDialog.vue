<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrAttendanceRecordsApi } from '@/plugins/apis/hrRequest'
import { ATTENDANCE_STATUS_COLORS, ENTRY_REASONS } from '@/utils/hrAttendanceRecords'
import { useI18n } from 'vue-i18n'

/**
 * Bulk manual entry: one date, the employees of a branch, times per employee (or the same times for everyone).
 * Each row is saved or refused on its own by the server; the result is shown next to the row.
 */
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  limits: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const MAX_ENTRIES = 200

const { t } = useI18n()
const { lookups } = useHrLookups()

const date = ref(null)
const branchId = ref(null)
const manualOnly = ref(true)
const common = ref({ check_in: '', check_out: '', entry_reason: 'manual_attendance_method', entry_reason_notes: '' })
const rows = ref([])
const loading = ref(false)
const saving = ref(false)
const errors = ref({})
const summary = ref(null)

watch(() => props.modelValue, visible => {
  if (!visible)
    return
  date.value = props.limits.today ?? null
  branchId.value = null
  manualOnly.value = true
  common.value = { check_in: '', check_out: '', entry_reason: 'manual_attendance_method', entry_reason_notes: '' }
  rows.value = []
  errors.value = {}
  summary.value = null
  loadEmployees()
})

const loadEmployees = () => {
  if (!date.value)
    return
  loading.value = true
  summary.value = null
  hrAttendanceRecordsApi.employees({ date: date.value, branch_id: branchId.value || undefined, limit: MAX_ENTRIES }).then(({ data }) => {
    rows.value = data.data
      .filter(employee => employee.attendance_mode !== 'exempt')
      .map(employee => ({
        ...employee,
        include: !employee.recorded_method && employee.attendance_mode === 'manual',
        check_in: '',
        check_out: '',
        notes: '',
        result: null,
      }))
  }).catch(() => {
    rows.value = []
  }).finally(() => {
    loading.value = false
  })
}

watch([date, branchId], () => {
  if (props.modelValue)
    loadEmployees()
})

const visibleRows = computed(() => rows.value.filter(row => !manualOnly.value || row.attendance_mode === 'manual'))
const selectable = row => !row.recorded_method && row.result?.status !== 'created'
const selectedRows = computed(() => visibleRows.value.filter(row => row.include && selectable(row)))
const readyRows = computed(() => selectedRows.value.filter(row => row.check_in || row.check_out))

const allSelected = computed({
  get: () => visibleRows.value.some(selectable) && visibleRows.value.filter(selectable).every(row => row.include),
  set: value => visibleRows.value.filter(selectable).forEach(row => {
    row.include = value
  }),
})

const applyToSelected = () => selectedRows.value.forEach(row => {
  row.check_in = common.value.check_in
  row.check_out = common.value.check_out
})

const reasonItems = computed(() => ENTRY_REASONS.map(value => ({ value, title: t(`hr.attendance_records.entry_reasons.${value}`) })))

const dateConfig = computed(() => ({
  maxDate: props.limits.today,
  minDate: props.limits.can_enter_historical ? null : props.limits.oldest_date,
}))

const entryOf = row => ({
  employee_id: row.id,
  attendance_date: date.value,
  check_in: row.check_in || null,
  check_out: row.check_out || null,
  notes: row.notes || null,
})

// Results are keyed by the entry index sent.
const applyResults = (entries, results) => results.forEach(result => {
  const row = entries[result.key]
  if (!row)
    return
  row.result = result
  if (result.status === 'created')
    row.include = false
})

// Validation errors of one entry ("entries.3.check_in") are shown next to that row.
const applyEntryErrors = entries => Object.entries(errors.value).forEach(([key, messages]) => {
  const row = entries[Number(key.match(/^entries\.(\d+)\./)?.[1] ?? -1)]
  if (row)
    row.result = { status: 'failed', message: messages[0] }
})

const save = () => {
  const entries = readyRows.value
  if (!entries.length)
    return

  saving.value = true
  errors.value = {}
  hrAttendanceRecordsApi.bulk({
    entry_reason: common.value.entry_reason,
    entry_reason_notes: common.value.entry_reason === 'other' ? common.value.entry_reason_notes || null : null,
    entries: entries.map(entryOf),
  }).then(({ data }) => {
    applyResults(entries, data.data.results)
    summary.value = { created: data.data.created, failed: data.data.failed, message: data.message }
    if (data.data.created)
      emit('saved')
  }).catch(error => {
    errors.value = error?.response?.data?.errors ?? {}
    applyEntryErrors(entries)
  }).finally(() => {
    saving.value = false
  })
}

const errorsOf = field => errors.value[field] ?? []
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="1080"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      :title="$t('hr.attendance_records.bulk_title')"
      :subtitle="$t('hr.attendance_records.bulk_subtitle')"
    >
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="4"
            md="3"
          >
            <AppDateTimePicker
              v-model="date"
              :config="dateConfig"
              :label="`${$t('hr.attendance_records.attendance_date')} *`"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
            md="3"
          >
            <AppSelect
              v-model="branchId"
              :items="lookups.branches"
              :label="$t('hr.fields.branch')"
              :placeholder="$t('hr.attendance_records.all_branches')"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
            md="6"
            class="d-flex align-center"
          >
            <VSwitch
              v-model="manualOnly"
              :label="$t('hr.attendance_records.manual_employees_only')"
              hide-details
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppSelect
              v-model="common.entry_reason"
              :items="reasonItems"
              :label="`${$t('hr.attendance_records.entry_reason')} *`"
              :error-messages="errorsOf('entry_reason')"
            />
          </VCol>
          <VCol
            v-if="common.entry_reason === 'other'"
            cols="12"
            sm="6"
            md="3"
          >
            <AppTextField
              v-model="common.entry_reason_notes"
              :label="`${$t('hr.attendance_records.entry_reason_notes')} *`"
              :error-messages="errorsOf('entry_reason_notes')"
            />
          </VCol>
          <VCol
            cols="6"
            sm="3"
            md="2"
          >
            <AppTextField
              v-model="common.check_in"
              type="time"
              :label="$t('hr.my_attendance.check_in_time')"
              dir="ltr"
            />
          </VCol>
          <VCol
            cols="6"
            sm="3"
            md="2"
          >
            <AppTextField
              v-model="common.check_out"
              type="time"
              :label="$t('hr.my_attendance.check_out_time')"
              dir="ltr"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="2"
            class="d-flex align-end"
          >
            <VBtn
              variant="tonal"
              block
              :disabled="!selectedRows.length || (!common.check_in && !common.check_out)"
              @click="applyToSelected"
            >
              {{ $t('hr.attendance_records.apply_to_selected') }}
            </VBtn>
          </VCol>
        </VRow>

        <VAlert
          v-if="summary"
          :type="summary.failed ? 'warning' : 'success'"
          variant="tonal"
          density="compact"
          class="mt-4"
        >
          {{ summary.message }}
        </VAlert>
        <VAlert
          v-if="errorsOf('entries').length"
          type="error"
          variant="tonal"
          density="compact"
          class="mt-4"
        >
          {{ errorsOf('entries')[0] }}
        </VAlert>

        <VTable
          density="compact"
          class="mt-4 text-no-wrap"
        >
          <thead>
            <tr>
              <th style="inline-size: 48px;">
                <VCheckbox
                  v-model="allSelected"
                  density="compact"
                  hide-details
                />
              </th>
              <th>{{ $t('hr.fields.employee') }}</th>
              <th>{{ $t('hr.my_attendance.check_in_time') }}</th>
              <th>{{ $t('hr.my_attendance.check_out_time') }}</th>
              <th>{{ $t('hr.attendance_records.notes') }}</th>
              <th>{{ $t('hr.attendance_records.result') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td
                colspan="6"
                class="text-center py-4"
              >
                <VProgressCircular
                  indeterminate
                  size="24"
                />
              </td>
            </tr>
            <tr v-else-if="!visibleRows.length">
              <td
                colspan="6"
                class="text-center hr-page__muted py-4"
              >
                {{ $t('hr.attendance_records.bulk_no_employees') }}
              </td>
            </tr>
            <tr
              v-for="row in loading ? [] : visibleRows"
              :key="row.id"
            >
              <td>
                <VCheckbox
                  v-model="row.include"
                  :disabled="!selectable(row)"
                  density="compact"
                  hide-details
                />
              </td>
              <td>
                <div>{{ row.name }}</div>
                <div class="text-caption hr-page__muted">
                  <span dir="ltr">{{ row.employee_number }}</span>
                  <span v-if="row.branch"> · {{ row.branch }}</span>
                  <span v-if="row.attendance_mode !== 'manual'"> · {{ $t(`hr.attendance_records.modes.${row.attendance_mode}`) }}</span>
                </div>
              </td>
              <td style="min-inline-size: 130px;">
                <AppTextField
                  v-model="row.check_in"
                  type="time"
                  density="compact"
                  :disabled="!selectable(row)"
                  dir="ltr"
                />
              </td>
              <td style="min-inline-size: 130px;">
                <AppTextField
                  v-model="row.check_out"
                  type="time"
                  density="compact"
                  :disabled="!selectable(row)"
                  dir="ltr"
                />
              </td>
              <td style="min-inline-size: 180px;">
                <AppTextField
                  v-model="row.notes"
                  density="compact"
                  :disabled="!selectable(row)"
                />
              </td>
              <td class="text-wrap">
                <VChip
                  v-if="row.recorded_method"
                  size="small"
                  label
                  color="secondary"
                >
                  {{ $t('hr.attendance_records.already_recorded', { method: $t(`hr.attendance_records.methods.${row.recorded_method}`) }) }}
                </VChip>
                <VChip
                  v-else-if="row.result?.status === 'created'"
                  size="small"
                  label
                  :color="ATTENDANCE_STATUS_COLORS[row.result.attendance_status] || 'success'"
                  prepend-icon="tabler-check"
                >
                  {{ $t(`hr.attendance_records.statuses.${row.result.attendance_status}`) }}
                </VChip>
                <span
                  v-else-if="row.result?.status === 'failed'"
                  class="text-error text-caption"
                >{{ row.result.message }}</span>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
      <VCardActions>
        <span class="text-caption hr-page__muted ms-2">
          {{ $t('hr.attendance_records.bulk_ready', { ready: readyRows.length, selected: selectedRows.length }) }}
        </span>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Close') }}
        </VBtn>
        <VBtn
          :loading="saving"
          :disabled="!readyRows.length"
          @click="save"
        >
          {{ $t('hr.attendance_records.save_records', { count: readyRows.length }) }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
