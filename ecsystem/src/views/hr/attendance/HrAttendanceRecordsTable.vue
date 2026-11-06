<script setup>
import { ATTENDANCE_STATUS_COLORS } from '@/utils/hrAttendanceRecords'
import { formatMinutes, shiftColor } from '@/utils/hrShifts'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

// Attendance records, electronic and manual (AttendanceRecordResource). `fetch(params)` returns the paginated API
// response. With `params` the parent owns the filters (central records page); otherwise the table shows its own.
const props = defineProps({
  fetch: { type: Function, required: true },
  showLocationDetails: { type: Boolean, default: false },
  reviewFilter: { type: Boolean, default: false },
  showEmployee: { type: Boolean, default: false },
  params: { type: Object, default: null },
})

const slots = useSlots()
const { t } = useI18n()

const VERIFICATION_APPEARANCE = {
  verified: { color: 'success', icon: 'tabler-map-pin-check' },
  not_required: { color: 'secondary', icon: 'tabler-map-pin' },
  auto_closed: { color: 'secondary', icon: 'tabler-clock-stop' },
  manual: { color: 'info', icon: 'tabler-pencil' },
}

const dateFrom = ref(null)
const dateTo = ref(null)
const needsReview = ref(false)
const items = ref([])
const total = ref(0)
const loading = ref(false)
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })

const fetchRows = () => {
  loading.value = true

  const filters = props.params ?? {
    from: dateFrom.value || undefined,
    to: dateTo.value || undefined,
    needs_review: needsReview.value ? 1 : undefined,
  }

  return props.fetch({ ...filters, options: options.value }).then(response => {
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
  filters: () => (props.params ? JSON.stringify(props.params) : [dateFrom.value, dateTo.value, needsReview.value]),
  options,
})

defineExpose({ refresh: fetchRows })

const headers = computed(() => [
  ...(props.showEmployee ? [{ title: t('hr.fields.employee'), key: 'employee_number', sortable: true }] : []),
  { title: t('hr.attendance_log.date'), key: 'attendance_date', sortable: props.showEmployee },
  { title: t('hr.fields.shift'), key: 'shift', sortable: false },
  { title: t('hr.my_attendance.check_in_time'), key: 'check_in', sortable: false },
  { title: t('hr.my_attendance.check_out_time'), key: 'check_out', sortable: false },
  { title: t('hr.my_attendance.working_duration'), key: 'duration', sortable: false },
  { title: t('hr.attendance_log.deviations'), key: 'deviations', sortable: false },
  { title: t('hr.attendance_log.status'), key: 'status', sortable: false },
  ...(slots.actions ? [{ title: '', key: 'actions', sortable: false, align: 'end' }] : []),
])

const verification = side => VERIFICATION_APPEARANCE[side.verification] ?? { color: 'warning', icon: 'tabler-map-pin-off' }

const locationDetail = (side, record) => {
  const parts = [t(`hr.my_attendance.verification.${side.verification}`)]
  if (side.distance_meters !== null && side.distance_meters !== undefined)
    parts.push(t('hr.my_attendance.distance_short', { distance: Math.round(side.distance_meters), radius: record.location_radius_meters ?? '—' }))
  if (side.accuracy_meters !== null && side.accuracy_meters !== undefined)
    parts.push(t('hr.my_attendance.accuracy_short', { accuracy: Math.round(side.accuracy_meters) }))
  if (props.showLocationDetails && side.latitude !== null && side.latitude !== undefined)
    parts.push(`${side.latitude.toFixed(6)}, ${side.longitude.toFixed(6)}`)
  if (props.showLocationDetails && side.ip)
    parts.push(`IP ${side.ip}`)

  return parts.join(' · ')
}

// Records saved before the attendance status existed fall back to the session status.
const statusOf = record => record.attendance_status
  ? { color: ATTENDANCE_STATUS_COLORS[record.attendance_status] || 'secondary', label: t(`hr.attendance_records.statuses.${record.attendance_status}`) }
  : { color: 'secondary', label: t(`hr.my_attendance.status.${record.status}`) }
</script>

<template>
  <div>
    <VCardText
      v-if="!params"
      class="pb-2"
    >
      <VRow>
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
        <VCol
          v-if="reviewFilter"
          cols="12"
          sm="4"
          md="3"
          class="d-flex align-center"
        >
          <VSwitch
            v-model="needsReview"
            :label="$t('hr.attendance_log.needs_review_only')"
            hide-details
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
      :no-data-text="$t('hr.attendance_log.no_records')"
      item-value="id"
      class="text-no-wrap"
      @update:options="applyServerTableOptions(options, $event)"
    >
      <template #item.employee_number="{ item }">
        <div>{{ item.raw.employee?.name ?? '—' }}</div>
        <div class="text-caption hr-page__muted">
          <span dir="ltr">{{ item.raw.employee?.employee_number }}</span>
          <span v-if="item.raw.employee?.branch"> · {{ item.raw.employee.branch.name }}</span>
        </div>
      </template>
      <template #item.attendance_date="{ item }">
        <span dir="ltr">{{ item.raw.attendance_date }}</span>
      </template>
      <template #item.shift="{ item }">
        <div
          v-if="item.raw.shift"
          class="d-flex align-center gap-2"
        >
          <span
            class="hr-shift-swatch hr-shift-swatch--inline"
            :style="{ backgroundColor: shiftColor(item.raw.shift.color) }"
          />
          <span>{{ item.raw.shift.name }}</span>
          <span
            class="hr-page__muted"
            dir="ltr"
          >{{ item.raw.scheduled_start_time }} - {{ item.raw.scheduled_end_time }}</span>
          <VIcon
            v-if="item.raw.shift_overridden"
            icon="tabler-switch-horizontal"
            size="16"
            color="info"
          >
            <VTooltip activator="parent">
              {{ $t('hr.attendance_records.shift_overridden') }}
            </VTooltip>
          </VIcon>
        </div>
        <span v-else>—</span>
      </template>
      <template #item.check_in="{ item }">
        <div
          v-if="item.raw.check_in"
          class="d-flex align-center gap-1"
        >
          <VIcon
            :icon="verification(item.raw.check_in).icon"
            :color="verification(item.raw.check_in).color"
            size="18"
          />
          <span dir="ltr">{{ item.raw.check_in.time }}</span>
          <span
            v-if="item.raw.check_in.date !== item.raw.attendance_date"
            class="hr-page__muted text-caption"
            dir="ltr"
          >({{ item.raw.check_in.date }})</span>
          <VTooltip activator="parent">
            {{ locationDetail(item.raw.check_in, item.raw) }}
          </VTooltip>
        </div>
        <span v-else>—</span>
      </template>
      <template #item.check_out="{ item }">
        <div
          v-if="item.raw.check_out"
          class="d-flex align-center gap-1"
        >
          <VIcon
            :icon="verification(item.raw.check_out).icon"
            :color="verification(item.raw.check_out).color"
            size="18"
          />
          <span dir="ltr">{{ item.raw.check_out.time }}</span>
          <span
            v-if="item.raw.check_out.date !== item.raw.attendance_date"
            class="hr-page__muted text-caption"
          >({{ $t('hr.shifts.next_day') }})</span>
          <VTooltip activator="parent">
            {{ locationDetail(item.raw.check_out, item.raw) }}
          </VTooltip>
        </div>
        <span v-else>—</span>
      </template>
      <template #item.duration="{ item }">
        {{ formatMinutes(item.raw.duration_minutes, t) }}
        <div
          v-if="item.raw.worked_minutes !== null"
          class="hr-page__muted text-caption"
        >
          {{ $t('hr.my_attendance.worked_in_shift') }}: {{ formatMinutes(item.raw.worked_minutes, t) }}
        </div>
      </template>
      <template #item.deviations="{ item }">
        <div class="d-flex flex-wrap gap-1">
          <VChip
            v-if="item.raw.late_minutes"
            size="x-small"
            label
            color="warning"
          >
            {{ $t('hr.my_attendance.late', { minutes: formatMinutes(item.raw.late_minutes, t) }) }}
          </VChip>
          <VChip
            v-if="item.raw.early_departure_minutes"
            size="x-small"
            label
            color="warning"
          >
            {{ $t('hr.my_attendance.early', { minutes: formatMinutes(item.raw.early_departure_minutes, t) }) }}
          </VChip>
          <span v-if="!item.raw.late_minutes && !item.raw.early_departure_minutes">—</span>
        </div>
      </template>
      <template #item.status="{ item }">
        <div class="d-flex align-center flex-wrap gap-1">
          <VChip
            size="small"
            label
            :color="statusOf(item.raw).color"
          >
            {{ statusOf(item.raw).label }}
          </VChip>
          <VChip
            v-if="item.raw.attendance_method === 'manual'"
            size="small"
            label
            variant="outlined"
            color="info"
            prepend-icon="tabler-pencil"
          >
            {{ $t('hr.attendance_records.methods.manual') }}
            <VTooltip
              v-if="item.raw.entry_reason"
              activator="parent"
            >
              {{ $t(`hr.attendance_records.entry_reasons.${item.raw.entry_reason}`) }}{{ item.raw.entry_reason_notes ? `: ${item.raw.entry_reason_notes}` : '' }}
            </VTooltip>
          </VChip>
          <VChip
            v-else-if="item.raw.is_corrected"
            size="small"
            label
            variant="outlined"
            color="secondary"
            prepend-icon="tabler-edit"
          >
            {{ $t('hr.attendance_records.corrected') }}
          </VChip>
          <VChip
            v-if="item.raw.needs_review"
            size="small"
            label
            color="error"
            variant="tonal"
            prepend-icon="tabler-alert-triangle"
          >
            {{ $t('hr.attendance_log.review') }}
            <VTooltip activator="parent">
              {{ item.raw.review_reasons.map(reason => $t(`hr.my_attendance.review_reasons.${reason}`)).join('، ') }}
            </VTooltip>
          </VChip>
        </div>
        <div
          v-if="item.raw.notes"
          class="text-caption hr-page__muted text-truncate"
          style="max-inline-size: 220px;"
        >
          {{ item.raw.notes }}
        </div>
      </template>
      <template #item.actions="{ item }">
        <slot
          name="actions"
          :record="item.raw"
        />
      </template>
    </VDataTableServer>
  </div>
</template>
