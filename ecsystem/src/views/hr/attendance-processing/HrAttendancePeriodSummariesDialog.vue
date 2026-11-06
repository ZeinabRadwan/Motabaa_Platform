<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrAttendancePeriodsApi } from '@/plugins/apis/hrRequest'
import { formatDays, PERIOD_STATUS_COLORS, saveBlob } from '@/utils/hrLeave'
import { formatMinutes } from '@/utils/hrShifts'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

// Per-employee figures of a processed payroll period: absence days, late minutes, attendance exceptions and
// approved leave. Once the period is approved the same figures are served to payroll (GET .../payroll), which
// can also be downloaded here as JSON.

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  period: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()
const { lookups } = useHrLookups()

const exceptionLabel = key => (lookups.value.attendance_exceptions ?? []).find(option => option.value === key)?.title ?? key

const items = ref([])
const total = ref(0)
const loading = ref(false)
const search = ref('')
const exceptionsOnly = ref(false)
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })
const expanded = ref(null)

const fetchItems = () => {
  if (!props.modelValue || !props.period)
    return
  loading.value = true

  return hrAttendancePeriodsApi.summaries(props.period.id, {
    q: search.value || undefined,
    exceptions_only: exceptionsOnly.value ? 1 : undefined,
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
  filters: () => [exceptionsOnly.value, props.modelValue, props.period?.id],
  options,
})

const updateOptions = incoming => applyServerTableOptions(options, incoming)

const downloading = ref(false)

const downloadPayroll = () => {
  downloading.value = true
  hrAttendancePeriodsApi.payroll(props.period.id).then(response => {
    saveBlob({ data: JSON.stringify(response.data.data, null, 2) }, `attendance-payroll-${props.period.period_start}_${props.period.period_end}.json`, 'application/json')
  }).catch(() => {}).finally(() => {
    downloading.value = false
  })
}

const minutes = value => (value ? formatMinutes(value, t) : '—')

const headers = computed(() => [
  { title: t('hr.fields.employee'), key: 'employee', sortable: false },
  { title: t('hr.attendance_processing.scheduled_days'), key: 'scheduled_days', sortable: false },
  { title: t('hr.attendance_processing.present_days'), key: 'present_days', sortable: false },
  { title: t('hr.attendance_processing.absence_days'), key: 'absence_days' },
  { title: t('hr.attendance_processing.late_minutes'), key: 'late_minutes' },
  { title: t('hr.attendance_processing.early_minutes'), key: 'early_departure_minutes', sortable: false },
  { title: t('hr.attendance_processing.leave_days'), key: 'leave_days', sortable: false },
  { title: t('hr.attendance_processing.exceptions'), key: 'unresolved_exceptions' },
  { title: '', key: 'details', sortable: false },
])
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="1200"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard v-if="period">
      <VCardItem>
        <VCardTitle>{{ period.name }}</VCardTitle>
        <VCardSubtitle>
          <span dir="ltr">{{ period.period_start }} → {{ period.period_end }}</span>
          · {{ period.branch_name || $t('hr.attendance_processing.all_branches') }}
        </VCardSubtitle>
        <template #append>
          <VChip
            label
            :color="PERIOD_STATUS_COLORS[period.status]"
          >
            {{ period.status_label }}
          </VChip>
        </template>
      </VCardItem>
      <VCardText>
        <VAlert
          v-if="period.status !== 'approved'"
          type="warning"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ $t('hr.attendance_processing.not_approved_hint') }}
        </VAlert>
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              v-model="search"
              :placeholder="$t('Search')"
              prepend-inner-icon="tabler-search"
              clearable
              clear-icon="tabler-x"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <VSwitch
              v-model="exceptionsOnly"
              :label="$t('hr.attendance_processing.exceptions_only')"
              hide-details
            />
          </VCol>
          <VCol
            v-if="period.status === 'approved'"
            cols="12"
            md="4"
            class="d-flex align-center justify-end"
          >
            <VBtn
              prepend-icon="tabler-download"
              variant="tonal"
              :loading="downloading"
              @click="downloadPayroll"
            >
              {{ $t('hr.attendance_processing.download_payroll') }}
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>

      <VDataTableServer
        v-model:items-per-page="options.itemsPerPage"
        v-model:page="options.page"
        :items="items"
        :items-length="total"
        :headers="headers"
        :loading="loading"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <template #item.employee="{ item }">
          <div>{{ item.raw.employee?.name }}</div>
          <div
            class="text-caption hr-page__muted"
            dir="ltr"
          >
            {{ item.raw.employee?.employee_number }}
          </div>
        </template>
        <template #item.absence_days="{ item }">
          <span :class="{ 'text-error font-weight-medium': Number(item.raw.absence_days) > 0 }">{{ formatDays(item.raw.absence_days) }}</span>
        </template>
        <template #item.late_minutes="{ item }">
          {{ minutes(item.raw.late_minutes) }}
          <div
            v-if="item.raw.late_days"
            class="text-caption hr-page__muted"
          >
            {{ $t('hr.attendance_processing.days_count', { days: item.raw.late_days }) }}
          </div>
        </template>
        <template #item.early_departure_minutes="{ item }">
          {{ minutes(item.raw.early_departure_minutes) }}
        </template>
        <template #item.leave_days="{ item }">
          {{ item.raw.leave_days }}
          <div
            v-if="item.raw.unpaid_leave_days"
            class="text-caption text-warning"
          >
            {{ $t('hr.attendance_processing.unpaid_days', { days: item.raw.unpaid_leave_days }) }}
          </div>
        </template>
        <template #item.unresolved_exceptions="{ item }">
          {{ item.raw.exceptions_count }}
          <VChip
            v-if="item.raw.unresolved_exceptions"
            size="x-small"
            label
            color="error"
            class="ms-1"
          >
            {{ $t('hr.attendance_processing.unresolved_short', { count: item.raw.unresolved_exceptions }) }}
          </VChip>
        </template>
        <template #item.details="{ item }">
          <VBtn
            icon
            variant="text"
            size="small"
            @click="expanded = expanded === item.raw.id ? null : item.raw.id"
          >
            <VIcon :icon="expanded === item.raw.id ? 'tabler-chevron-up' : 'tabler-chevron-down'" />
          </VBtn>
        </template>
      </VDataTableServer>

      <VCardText v-if="expanded">
        <template
          v-for="row in items.filter(summary => summary.id === expanded)"
          :key="row.id"
        >
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <div class="text-subtitle-2 mb-2">
                {{ $t('hr.attendance_processing.approved_leave', { name: row.employee?.name }) }}
              </div>
              <div
                v-if="!row.leave_requests.length"
                class="hr-page__muted"
              >
                —
              </div>
              <div
                v-for="leave in row.leave_requests"
                :key="leave.id"
                class="mb-1"
              >
                {{ leave.leave_type }}
                <span dir="ltr">({{ leave.start_date }} → {{ leave.end_date }})</span>
                · {{ formatDays(leave.days) }}
                · {{ $t(leave.is_paid ? 'hr.leave.paid_yes' : 'hr.leave.paid_no') }}
              </div>
              <div
                v-if="row.leave_breakdown.length"
                class="d-flex flex-wrap gap-1 mt-2"
              >
                <VChip
                  v-for="entry in row.leave_breakdown"
                  :key="entry.leave_type_id ?? 'none'"
                  size="small"
                  label
                >
                  {{ entry.leave_type || '—' }}: {{ entry.days }}
                </VChip>
              </div>
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <div class="text-subtitle-2 mb-2">
                {{ $t('hr.attendance_processing.exceptions') }}
              </div>
              <div
                v-if="!row.exception_details.length"
                class="hr-page__muted"
              >
                —
              </div>
              <div
                v-for="exception in row.exception_details"
                :key="exception.date"
                class="mb-1"
              >
                <span dir="ltr">{{ exception.date }}</span>:
                {{ exception.reasons.map(exceptionLabel).join(', ') }}
                <VChip
                  size="x-small"
                  label
                  :color="exception.review_status === 'pending' ? 'warning' : 'success'"
                >
                  {{ $t(`hr.attendance_processing.review.${exception.review_status || 'resolved'}`) }}
                </VChip>
                <div
                  v-if="exception.resolution"
                  class="text-caption hr-page__muted"
                >
                  {{ exception.resolution }}
                </div>
              </div>
            </VCol>
          </VRow>
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
      </VCardActions>
    </VCard>
  </VDialog>
</template>
