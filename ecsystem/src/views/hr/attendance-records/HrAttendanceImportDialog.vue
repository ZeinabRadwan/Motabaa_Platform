<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrAttendanceRecordsApi } from '@/plugins/apis/hrRequest'
import { ATTENDANCE_STATUS_COLORS, ENTRY_REASONS } from '@/utils/hrAttendanceRecords'
import { downloadExportFile } from '@core/utils/exportDownload'
import { useI18n } from 'vue-i18n'

/**
 * Manual attendance from Excel / CSV: download the template (optionally prefilled with the manual-attendance
 * employees of a day), check the file (nothing is saved), then import the valid rows. Existing records are never
 * overwritten.
 */
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  limits: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const ROW_COLORS = { valid: 'success', created: 'success', invalid: 'error', failed: 'error', skipped: 'secondary' }

const { t } = useI18n()
const { lookups } = useHrLookups()

const templateDate = ref(null)
const templateBranch = ref(null)
const file = ref([])
const entryReason = ref('manual_attendance_method')
const entryReasonNotes = ref('')
const report = ref(null)
const committed = ref(false)
const working = ref(false)
const downloading = ref(false)
const errors = ref({})

watch(() => props.modelValue, visible => {
  if (!visible)
    return
  templateDate.value = props.limits.today ?? null
  templateBranch.value = null
  file.value = []
  entryReason.value = 'manual_attendance_method'
  entryReasonNotes.value = ''
  report.value = null
  committed.value = false
  errors.value = {}
})

watch(file, () => {
  report.value = null
  committed.value = false
})

const selectedFile = computed(() => (Array.isArray(file.value) ? file.value[0] : file.value) ?? null)

const reasonItems = computed(() => ENTRY_REASONS.map(value => ({ value, title: t(`hr.attendance_records.entry_reasons.${value}`) })))

const downloadTemplate = () => {
  downloading.value = true
  hrAttendanceRecordsApi.importTemplate({ date: templateDate.value || undefined, branch_id: templateBranch.value || undefined })
    .then(({ data }) => downloadExportFile(data.data.url))
    .finally(() => {
      downloading.value = false
    })
}

const run = dryRun => {
  if (!selectedFile.value)
    return
  working.value = true
  errors.value = {}
  hrAttendanceRecordsApi.import({
    file: selectedFile.value,
    dry_run: dryRun ? 1 : 0,
    entry_reason: entryReason.value,
    entry_reason_notes: entryReason.value === 'other' ? entryReasonNotes.value : null,
  }).then(({ data }) => {
    report.value = data.data
    committed.value = !dryRun
    if (!dryRun) {
      window.Snackbar?.value?.exposevisibleSnackbar?.(data.message, data.data.summary.failed ? 'warning' : 'success')
      if (data.data.summary.created)
        emit('saved')
    }
  }).catch(error => {
    errors.value = error?.response?.data?.errors ?? {}
  }).finally(() => {
    working.value = false
  })
}

const errorsOf = field => errors.value[field] ?? []
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="980"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      :title="$t('hr.attendance_records.import_title')"
      :subtitle="$t('hr.attendance_records.import_subtitle')"
    >
      <VCardText>
        <VCard
          variant="outlined"
          class="mb-4"
        >
          <VCardText>
            <div class="text-body-1 font-weight-medium mb-1">
              1. {{ $t('hr.attendance_records.import_step_template') }}
            </div>
            <div class="text-body-2 hr-page__muted mb-3">
              {{ $t('hr.attendance_records.import_template_hint') }}
            </div>
            <VRow>
              <VCol
                cols="12"
                sm="4"
              >
                <AppDateTimePicker
                  v-model="templateDate"
                  :config="{ maxDate: limits.today }"
                  :label="$t('hr.attendance_records.prefill_date')"
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="templateBranch"
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
                class="d-flex align-end"
              >
                <VBtn
                  variant="tonal"
                  prepend-icon="tabler-file-download"
                  block
                  :loading="downloading"
                  @click="downloadTemplate"
                >
                  {{ $t('hr.attendance_records.download_template') }}
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VCard variant="outlined">
          <VCardText>
            <div class="text-body-1 font-weight-medium mb-3">
              2. {{ $t('hr.attendance_records.import_step_upload') }}
            </div>
            <VRow>
              <VCol
                cols="12"
                md="5"
              >
                <VFileInput
                  v-model="file"
                  accept=".xlsx,.xls,.csv"
                  :label="`${$t('hr.attendance_records.import_file')} *`"
                  prepend-icon=""
                  prepend-inner-icon="tabler-file-spreadsheet"
                  show-size
                  :error-messages="errorsOf('file')"
                />
              </VCol>
              <VCol
                cols="12"
                :md="entryReason === 'other' ? 4 : 7"
              >
                <AppSelect
                  v-model="entryReason"
                  :items="reasonItems"
                  :label="`${$t('hr.attendance_records.entry_reason')} *`"
                  :error-messages="errorsOf('entry_reason')"
                />
              </VCol>
              <VCol
                v-if="entryReason === 'other'"
                cols="12"
                md="3"
              >
                <AppTextField
                  v-model="entryReasonNotes"
                  :label="`${$t('hr.attendance_records.entry_reason_notes')} *`"
                  :error-messages="errorsOf('entry_reason_notes')"
                />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <template v-if="report">
          <div class="d-flex flex-wrap gap-2 mt-4">
            <VChip
              v-for="status in committed ? ['created', 'failed', 'skipped'] : ['valid', 'invalid', 'skipped']"
              :key="status"
              :color="ROW_COLORS[status]"
              label
            >
              {{ $t(`hr.attendance_records.import_statuses.${status}`) }}: {{ report.summary[status] }}
            </VChip>
          </div>
          <VTable
            density="compact"
            class="mt-3 text-no-wrap"
            height="320"
            fixed-header
          >
            <thead>
              <tr>
                <th>#</th>
                <th>{{ $t('hr.fields.employee') }}</th>
                <th>{{ $t('hr.attendance_records.attendance_date') }}</th>
                <th>{{ $t('hr.my_attendance.check_in_time') }}</th>
                <th>{{ $t('hr.my_attendance.check_out_time') }}</th>
                <th>{{ $t('hr.attendance_records.result') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in report.rows"
                :key="row.row"
              >
                <td>{{ row.row }}</td>
                <td>
                  <div>{{ row.employee_name ?? '—' }}</div>
                  <div
                    class="text-caption hr-page__muted"
                    dir="ltr"
                  >
                    {{ row.employee_number ?? '' }}
                  </div>
                </td>
                <td dir="ltr">
                  {{ row.attendance_date ?? '—' }}
                </td>
                <td dir="ltr">
                  {{ row.check_in ?? '—' }}
                </td>
                <td dir="ltr">
                  {{ row.check_out ?? '—' }}
                </td>
                <td class="text-wrap">
                  <div class="d-flex align-center flex-wrap gap-1">
                    <VChip
                      size="x-small"
                      label
                      :color="ROW_COLORS[row.status]"
                    >
                      {{ $t(`hr.attendance_records.import_statuses.${row.status}`) }}
                    </VChip>
                    <VChip
                      v-if="row.attendance_status"
                      size="x-small"
                      label
                      variant="outlined"
                      :color="ATTENDANCE_STATUS_COLORS[row.attendance_status]"
                    >
                      {{ $t(`hr.attendance_records.statuses.${row.attendance_status}`) }}
                    </VChip>
                  </div>
                  <div
                    v-if="row.message"
                    class="text-caption"
                    :class="row.status === 'skipped' ? 'hr-page__muted' : 'text-error'"
                  >
                    {{ row.message }}
                  </div>
                  <div
                    v-for="warning in row.warnings ?? []"
                    :key="warning"
                    class="text-caption hr-page__muted"
                  >
                    {{ $t(`hr.attendance_records.warnings.${warning}`) }}
                  </div>
                </td>
              </tr>
            </tbody>
          </VTable>
        </template>
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Close') }}
        </VBtn>
        <VBtn
          v-if="!report || committed"
          :loading="working"
          :disabled="!selectedFile || committed"
          prepend-icon="tabler-file-search"
          @click="run(true)"
        >
          {{ $t('hr.attendance_records.check_file') }}
        </VBtn>
        <VBtn
          v-else
          :loading="working"
          :disabled="!report.summary.valid"
          prepend-icon="tabler-file-import"
          @click="run(false)"
        >
          {{ $t('hr.attendance_records.import_valid', { count: report.summary.valid }) }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
