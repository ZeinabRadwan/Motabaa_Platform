<script setup>
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

// Every check-in / check-out attempt of one employee (accepted, rejected, duplicate), for HR review.
const props = defineProps({
  employeeId: { type: Number, required: true },
})

const { t, te } = useI18n()

const RESULT_COLORS = { accepted: 'success', rejected: 'error', duplicate: 'secondary' }

const result = ref(null)
const dateFrom = ref(null)
const dateTo = ref(null)
const items = ref([])
const total = ref(0)
const loading = ref(false)
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })

const resultItems = computed(() => Object.keys(RESULT_COLORS).map(value => ({ value, title: t(`hr.attendance_log.results.${value}`) })))

const fetchRows = () => {
  loading.value = true

  return hrEmployeesApi.attendancePunches(props.employeeId, {
    result: result.value || undefined,
    from: dateFrom.value || undefined,
    to: dateTo.value || undefined,
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

watchServerTableFetch(fetchRows, {
  filters: () => [result.value, dateFrom.value, dateTo.value],
  options,
})

defineExpose({ refresh: fetchRows })

const headers = computed(() => [
  { title: t('hr.attendance_log.attempted_at'), key: 'attempted_at', sortable: false },
  { title: t('hr.attendance_log.action'), key: 'type', sortable: false },
  { title: t('hr.attendance_log.result'), key: 'result', sortable: false },
  { title: t('hr.attendance_log.location'), key: 'location', sortable: false },
  { title: t('hr.attendance_log.device'), key: 'device', sortable: false },
])

const reasonLabel = reason => (te(`hr.attendance_log.reasons.${reason}`) ? t(`hr.attendance_log.reasons.${reason}`) : reason)
</script>

<template>
  <div>
    <VCardText class="pb-2">
      <VRow>
        <VCol
          cols="12"
          sm="4"
          md="3"
        >
          <AppSelect
            v-model="result"
            :items="resultItems"
            :placeholder="$t('hr.attendance_log.result')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
          md="3"
        >
          <AppDateTimePicker
            v-model="dateFrom"
            :placeholder="$t('from')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
          md="3"
        >
          <AppDateTimePicker
            v-model="dateTo"
            :placeholder="$t('to')"
            clearable
            clear-icon="tabler-x"
          />
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
      :no-data-text="$t('hr.attendance_log.no_attempts')"
      item-value="id"
      class="text-no-wrap"
      @update:options="applyServerTableOptions(options, $event)"
    >
      <template #item.attempted_at="{ item }">
        <span dir="ltr">{{ item.raw.attempted_date }} {{ item.raw.attempted_time }}</span>
      </template>
      <template #item.type="{ item }">
        {{ $t(`hr.my_attendance.${item.raw.type}`) }}
      </template>
      <template #item.result="{ item }">
        <VChip
          size="small"
          label
          :color="RESULT_COLORS[item.raw.result] || 'secondary'"
        >
          {{ $t(`hr.attendance_log.results.${item.raw.result}`) }}
        </VChip>
        <div
          v-if="item.raw.reason"
          class="text-caption hr-page__muted mt-1"
        >
          {{ reasonLabel(item.raw.reason) }}
        </div>
      </template>
      <template #item.location="{ item }">
        <div v-if="item.raw.latitude !== null">
          <span dir="ltr">{{ item.raw.latitude.toFixed(6) }}, {{ item.raw.longitude.toFixed(6) }}</span>
          <div class="text-caption hr-page__muted">
            <span v-if="item.raw.verification">{{ $t(`hr.my_attendance.verification.${item.raw.verification}`) }}</span>
            <span v-if="item.raw.distance_meters !== null"> · {{ $t('hr.my_attendance.distance_only', { distance: Math.round(item.raw.distance_meters) }) }}</span>
            <span v-if="item.raw.accuracy_meters !== null"> · {{ $t('hr.my_attendance.accuracy_short', { accuracy: Math.round(item.raw.accuracy_meters) }) }}</span>
          </div>
        </div>
        <div v-else-if="item.raw.location_error">
          {{ $t(`hr.attendance_log.location_errors.${item.raw.location_error}`) }}
        </div>
        <span v-else>{{ item.raw.verification ? $t(`hr.my_attendance.verification.${item.raw.verification}`) : '—' }}</span>
      </template>
      <template #item.device="{ item }">
        <span dir="ltr">{{ item.raw.ip_address || '—' }}</span>
        <VTooltip
          v-if="item.raw.user_agent"
          activator="parent"
          max-width="360"
        >
          {{ item.raw.user_agent }}
        </VTooltip>
      </template>
    </VDataTableServer>
  </div>
</template>
