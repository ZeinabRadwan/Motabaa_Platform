<script setup>
import HrInfoGrid from '@/components/hr/HrInfoGrid.vue'
import { hrMyAttendanceApi } from '@/plugins/apis/hrRequest'
import { getBrowserPosition, watchGeolocationPermission } from '@/utils/hrGeolocation'
import { dayAppearance, formatMinutes, shiftColor } from '@/utils/hrShifts'
import { useI18n } from 'vue-i18n'
import HrLocationMonitor from './HrLocationMonitor.vue'

/*
 * The signed-in employee's attendance for the current shift. The location is read once per button press while
 * this page is open, and while checked in at the periodic location checks (HrLocationMonitor); the server
 * validates it, applies the attendance policy and uses its own clock.
 */
const emit = defineEmits(['punched'])

const { t, locale } = useI18n()

const STATUS_APPEARANCE = {
  not_checked_in: { color: 'secondary', icon: 'tabler-clock' },
  checked_in: { color: 'success', icon: 'tabler-clock-play' },
  checked_out: { color: 'info', icon: 'tabler-clock-stop' },
  missing_check_out: { color: 'warning', icon: 'tabler-alert-triangle' },
}

const VERIFICATION_COLORS = { verified: 'success', not_required: 'secondary', auto_closed: 'secondary', manual: 'info' }
const PERMISSION_COLORS = { granted: 'success', denied: 'error', prompt: 'info' }

const status = ref(null)
const loading = ref(true)
const loadFailed = ref(false)
const phase = ref(null)
const pendingAction = ref(null)
const feedback = ref(null)
const permission = ref(null)
const lastFix = ref(null)
const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine)
const clockOffset = ref(0)
const now = ref(Date.now())

const applyStatus = data => {
  status.value = data
  if (data?.server_time)
    clockOffset.value = new Date(data.server_time).getTime() - Date.now()
}

const fetchStatus = async () => {
  try {
    const { data } = await hrMyAttendanceApi.status()

    applyStatus(data.data)
    loadFailed.value = false
  }
  catch {
    loadFailed.value = !status.value
  }
  finally {
    loading.value = false
  }
}

const record = computed(() => status.value?.record ?? null)
const day = computed(() => status.value?.day ?? null)
const gpsRequirement = computed(() => status.value?.gps_requirement ?? 'none')
const statusKey = computed(() => record.value?.status ?? 'not_checked_in')
const statusAppearance = computed(() => STATUS_APPEARANCE[statusKey.value] ?? STATUS_APPEARANCE.not_checked_in)
const busy = computed(() => phase.value !== null)

const serverNow = computed(() => new Date(now.value + clockOffset.value))

const formatClock = (date, withSeconds = false) => new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', {
  hour: '2-digit',
  minute: '2-digit',
  second: withSeconds ? '2-digit' : undefined,
  hour12: false,
  timeZone: status.value?.timezone || 'Asia/Riyadh',
}).format(date)

const formatToday = date => new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: status.value?.timezone || 'Asia/Riyadh',
}).format(date)

const pad = value => String(value).padStart(2, '0')

// Live while checked in (server clock), otherwise the stored duration.
const durationText = computed(() => {
  const current = record.value
  if (!current)
    return '—'
  if (current.status === 'checked_in') {
    const seconds = Math.max(0, Math.floor((serverNow.value.getTime() - new Date(current.check_in.at).getTime()) / 1000))

    return `${pad(Math.floor(seconds / 3600))}:${pad(Math.floor((seconds % 3600) / 60))}:${pad(seconds % 60)}`
  }

  return formatMinutes(current.duration_minutes, t)
})

const shiftSummary = computed(() => {
  const current = day.value
  if (!current)
    return null
  if (current.status !== 'working')
    return t(dayAppearance(current).labelKey)
  const span = `${current.start_time} - ${current.end_time}${current.is_overnight ? ` (${t('hr.shifts.next_day')})` : ''}`

  return `${current.shift_name || t('hr.exceptions.types.custom_hours')} · ${span}`
})

const windowText = window => (window ? `${window.from_time} - ${window.to_time}` : null)

const detailItems = computed(() => {
  const data = status.value
  if (!data)
    return []
  const location = data.work_location

  return [
    { label: t('hr.my_attendance.assigned_shift'), value: shiftSummary.value },
    { label: t('hr.my_attendance.shift_date'), value: day.value?.date, ltr: true },
    { label: t('hr.shifts.expected_hours'), value: day.value?.status === 'working' ? formatMinutes(day.value.expected_minutes, t) : null },
    { label: t('hr.my_attendance.check_in_window'), value: windowText(data.check_in_window), ltr: true },
    { label: t('hr.my_attendance.check_out_window'), value: windowText(data.check_out_window), ltr: true },
    { label: t('hr.my_attendance.work_location'), value: location?.name },
    { label: t('hr.my_attendance.allowed_radius'), value: location ? t('hr.my_attendance.meters', { value: location.radius_meters }) : null },
    { label: t('hr.my_attendance.location_check'), value: t(`hr.my_attendance.gps.${gpsRequirement.value}`) },
  ]
})

const locationWarning = computed(() => {
  if (!status.value?.eligible || gpsRequirement.value === 'none')
    return null
  const location = status.value.work_location
  if (!location || !location.has_coordinates)
    return t('hr.my_attendance.location_not_configured')

  return null
})

const sideInfo = side => {
  if (!side)
    return null
  const radius = record.value?.location_radius_meters
  const parts = []
  if (side.distance_meters !== null && side.distance_meters !== undefined)
    parts.push(radius ? t('hr.my_attendance.distance_short', { distance: Math.round(side.distance_meters), radius }) : t('hr.my_attendance.distance_only', { distance: Math.round(side.distance_meters) }))
  if (side.accuracy_meters !== null && side.accuracy_meters !== undefined)
    parts.push(t('hr.my_attendance.accuracy_short', { accuracy: Math.round(side.accuracy_meters) }))

  return {
    color: VERIFICATION_COLORS[side.verification] ?? 'warning',
    label: t(`hr.my_attendance.verification.${side.verification}`),
    detail: parts.join(' · '),
  }
}

const checkInInfo = computed(() => sideInfo(record.value?.check_in))
const checkOutInfo = computed(() => sideInfo(record.value?.check_out))

const hintText = computed(() => {
  const hint = status.value?.hint
  if (!hint)
    return null

  return t(`hr.my_attendance.hints.${hint.key}`, { time: hint.time ?? '' })
})

const checkOutLabel = computed(() => (record.value?.status === 'checked_out' ? t('hr.my_attendance.check_out_again') : t('hr.my_attendance.check_out')))

const firstError = body => Object.values(body?.errors ?? {}).flat()[0] ?? body?.message ?? t('hr.my_attendance.unexpected_error')

const LOCATION_REASONS = ['attendance_outside_radius', 'attendance_low_accuracy', 'attendance_location_denied', 'attendance_location_unavailable']

const locationTip = code => (code ? t(`hr.my_attendance.location_errors.${code}`) : null)

// The GPS payload for the API: the fix, or the reason the browser could not provide one.
const readLocation = async () => {
  if (gpsRequirement.value === 'none')
    return { payload: {}, issue: null }

  phase.value = 'locating'
  try {
    const fix = await getBrowserPosition()

    lastFix.value = { ...fix, at: new Date() }

    return { payload: fix, issue: null }
  }
  catch (error) {
    return { payload: { location_error: error.code }, issue: error.code }
  }
}

const successFeedback = (data, issue) => {
  const result = data.data.result ?? {}
  const flagged = !!result.verification && !['verified', 'not_required'].includes(result.verification)
  let type = 'success'
  if (result.duplicate)
    type = 'info'
  else if (flagged)
    type = 'warning'

  return {
    type,
    text: data.message,
    detail: flagged ? `${t(`hr.my_attendance.verification.${result.verification}`)} · ${t('hr.my_attendance.sent_for_review')}` : null,
    tip: flagged ? locationTip(issue) : null,
  }
}

const errorFeedback = (error, issue) => {
  if (!error.response)
    return { type: 'error', icon: 'tabler-wifi-off', text: t('hr.my_attendance.network_error'), retry: true }

  const body = error.response.data ?? {}
  const tip = locationTip(issue) ?? (body.reason === 'attendance_low_accuracy' ? t('hr.my_attendance.accuracy_tip') : null)

  return {
    type: error.response.status === 403 ? 'warning' : 'error',
    text: firstError(body),
    tip,
    retry: LOCATION_REASONS.includes(body.reason) || error.response.status === 429,
  }
}

const punch = async action => {
  if (busy.value || !status.value)
    return

  feedback.value = null
  pendingAction.value = action

  const { payload, issue } = await readLocation()

  phase.value = 'submitting'
  try {
    const request = action === 'check_in' ? hrMyAttendanceApi.checkIn : hrMyAttendanceApi.checkOut
    const { data } = await request(payload)

    applyStatus(data.data)
    feedback.value = { ...successFeedback(data, issue), action }
    window.Snackbar?.value?.exposevisibleSnackbar?.(data.message, 'success')
    emit('punched')
  }
  catch (error) {
    feedback.value = { ...errorFeedback(error, issue), action }
    fetchStatus()
  }
  finally {
    phase.value = null
    pendingAction.value = null
  }
}

const onVisibility = () => {
  if (document.visibilityState === 'visible')
    fetchStatus()
}

const onOnline = () => {
  online.value = true
  fetchStatus()
}

const onOffline = () => {
  online.value = false
}

let ticker = null
let poller = null
let stopPermissionWatch = () => {}

onMounted(async () => {
  fetchStatus()
  ticker = setInterval(() => {
    now.value = Date.now()
  }, 1000)

  // Windows open and close with time: refresh while the page is visible.
  poller = setInterval(() => {
    if (document.visibilityState === 'visible' && !busy.value)
      fetchStatus()
  }, 60000)
  document.addEventListener('visibilitychange', onVisibility)
  window.addEventListener('online', onOnline)
  window.addEventListener('offline', onOffline)
  stopPermissionWatch = await watchGeolocationPermission(state => {
    permission.value = state
  })
})

onBeforeUnmount(() => {
  clearInterval(ticker)
  clearInterval(poller)
  document.removeEventListener('visibilitychange', onVisibility)
  window.removeEventListener('online', onOnline)
  window.removeEventListener('offline', onOffline)
  stopPermissionWatch()
})

defineExpose({ refresh: fetchStatus })
</script>

<template>
  <div>
    <VCard
      v-if="loading"
      class="pa-8 d-flex justify-center"
    >
      <VProgressCircular
        indeterminate
        color="primary"
      />
    </VCard>

    <VAlert
      v-else-if="loadFailed"
      type="error"
      variant="tonal"
    >
      {{ $t('hr.my_attendance.load_failed') }}
      <template #append>
        <VBtn
          size="small"
          variant="tonal"
          prepend-icon="tabler-refresh"
          @click="fetchStatus"
        >
          {{ $t('hr.my_attendance.retry') }}
        </VBtn>
      </template>
    </VAlert>

    <VAlert
      v-else-if="status && !status.eligible"
      type="info"
      variant="tonal"
      icon="tabler-info-circle"
    >
      {{ $t(`hr.my_attendance.ineligible.${status.ineligible_reason}`) }}
    </VAlert>

    <VRow v-else-if="status">
      <VCol
        cols="12"
        md="7"
      >
        <VCard class="h-100">
          <VCardText>
            <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
              <div>
                <div class="hr-page__muted text-body-2">
                  {{ formatToday(serverNow) }}
                </div>
                <div
                  class="hr-attendance-clock"
                  dir="ltr"
                >
                  {{ formatClock(serverNow, true) }}
                </div>
              </div>
              <VChip
                :color="statusAppearance.color"
                :prepend-icon="statusAppearance.icon"
                label
                size="large"
              >
                {{ $t(`hr.my_attendance.status.${statusKey}`) }}
              </VChip>
            </div>

            <VAlert
              v-if="!online"
              type="warning"
              variant="tonal"
              density="compact"
              icon="tabler-wifi-off"
              class="mb-4"
            >
              {{ $t('hr.my_attendance.offline') }}
            </VAlert>

            <VRow class="mb-2">
              <VCol
                cols="12"
                sm="4"
              >
                <div class="hr-page__muted text-body-2">
                  {{ $t('hr.my_attendance.check_in_time') }}
                </div>
                <div
                  class="text-h5"
                  dir="ltr"
                >
                  {{ record?.check_in?.time ?? '—' }}
                </div>
                <VChip
                  v-if="checkInInfo"
                  :color="checkInInfo.color"
                  size="x-small"
                  label
                  class="mt-1"
                >
                  {{ checkInInfo.label }}
                </VChip>
                <div
                  v-if="checkInInfo?.detail"
                  class="text-caption hr-page__muted"
                >
                  {{ checkInInfo.detail }}
                </div>
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <div class="hr-page__muted text-body-2">
                  {{ $t('hr.my_attendance.check_out_time') }}
                </div>
                <div
                  class="text-h5"
                  dir="ltr"
                >
                  {{ record?.check_out?.time ?? '—' }}
                </div>
                <VChip
                  v-if="checkOutInfo"
                  :color="checkOutInfo.color"
                  size="x-small"
                  label
                  class="mt-1"
                >
                  {{ checkOutInfo.label }}
                </VChip>
                <div
                  v-if="checkOutInfo?.detail"
                  class="text-caption hr-page__muted"
                >
                  {{ checkOutInfo.detail }}
                </div>
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <div class="hr-page__muted text-body-2">
                  {{ $t('hr.my_attendance.working_duration') }}
                </div>
                <div
                  class="text-h5"
                  dir="ltr"
                >
                  {{ durationText }}
                </div>
                <div
                  v-if="record?.late_minutes"
                  class="text-caption text-warning"
                >
                  {{ $t('hr.my_attendance.late', { minutes: formatMinutes(record.late_minutes, t) }) }}
                </div>
                <div
                  v-if="record?.early_departure_minutes"
                  class="text-caption text-warning"
                >
                  {{ $t('hr.my_attendance.early', { minutes: formatMinutes(record.early_departure_minutes, t) }) }}
                </div>
              </VCol>
            </VRow>

            <div class="d-flex flex-wrap gap-3 mb-2">
              <VBtn
                size="large"
                color="success"
                prepend-icon="tabler-login"
                :disabled="!status.can_check_in || (busy && pendingAction !== 'check_in')"
                :loading="pendingAction === 'check_in'"
                class="flex-grow-1"
                @click="punch('check_in')"
              >
                {{ $t('hr.my_attendance.check_in') }}
              </VBtn>
              <VBtn
                size="large"
                color="error"
                prepend-icon="tabler-logout"
                :disabled="!status.can_check_out || (busy && pendingAction !== 'check_out')"
                :loading="pendingAction === 'check_out'"
                class="flex-grow-1"
                @click="punch('check_out')"
              >
                {{ checkOutLabel }}
              </VBtn>
            </div>

            <div
              v-if="phase"
              class="d-flex align-center gap-2 text-body-2 hr-page__muted mb-2"
            >
              <VProgressCircular
                indeterminate
                size="16"
                width="2"
              />
              {{ $t(phase === 'locating' ? 'hr.my_attendance.locating' : 'hr.my_attendance.submitting') }}
            </div>

            <VAlert
              v-if="feedback"
              :type="feedback.type"
              :icon="feedback.icon"
              variant="tonal"
              class="mb-2"
              closable
              @click:close="feedback = null"
            >
              <div class="font-weight-medium">
                {{ feedback.text }}
              </div>
              <div
                v-if="feedback.detail"
                class="text-body-2"
              >
                {{ feedback.detail }}
              </div>
              <div
                v-if="feedback.tip"
                class="text-body-2 mt-1"
              >
                {{ feedback.tip }}
              </div>
              <VBtn
                v-if="feedback.retry && feedback.action"
                size="small"
                variant="tonal"
                prepend-icon="tabler-refresh"
                class="mt-2"
                :disabled="busy"
                @click="punch(feedback.action)"
              >
                {{ $t('hr.my_attendance.retry') }}
              </VBtn>
            </VAlert>

            <VAlert
              v-if="hintText"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-2"
            >
              {{ hintText }}
            </VAlert>

            <VAlert
              v-if="record?.needs_review"
              type="warning"
              variant="tonal"
              density="compact"
              icon="tabler-alert-triangle"
            >
              {{ $t('hr.my_attendance.under_review') }}:
              {{ record.review_reasons.map(reason => $t(`hr.my_attendance.review_reasons.${reason}`)).join('، ') }}
            </VAlert>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="5"
      >
        <VCard class="h-100">
          <VCardItem>
            <VCardTitle>{{ $t('hr.my_attendance.shift_and_location') }}</VCardTitle>
          </VCardItem>
          <VCardText>
            <div
              v-if="day?.status === 'working'"
              class="hr-today-shift mb-4"
              :style="{ borderInlineStartColor: shiftColor(day.color, day.shift_type) }"
            >
              <VIcon
                icon="tabler-clock-play"
                size="20"
              />
              <div class="font-weight-medium">
                {{ shiftSummary }}
              </div>
            </div>
            <HrInfoGrid
              :items="detailItems"
              class="hr-attendance-details"
            />
            <VAlert
              v-if="locationWarning"
              type="warning"
              variant="tonal"
              density="compact"
              class="mt-4"
            >
              {{ locationWarning }}
            </VAlert>
            <div
              v-if="gpsRequirement !== 'none'"
              class="d-flex flex-wrap align-center gap-2 mt-4"
            >
              <VChip
                v-if="permission"
                :color="PERMISSION_COLORS[permission] || 'secondary'"
                size="small"
                label
                prepend-icon="tabler-current-location"
              >
                {{ $t(`hr.my_attendance.permission.${permission}`) }}
              </VChip>
              <span
                v-if="lastFix"
                class="text-caption hr-page__muted"
              >
                {{ $t('hr.my_attendance.last_fix', { accuracy: Math.round(lastFix.accuracy), time: formatClock(lastFix.at) }) }}
              </span>
            </div>
            <VAlert
              v-if="permission === 'denied' && gpsRequirement !== 'none'"
              type="error"
              variant="tonal"
              density="compact"
              class="mt-3"
            >
              {{ $t('hr.my_attendance.location_errors.permission_denied') }}
            </VAlert>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        v-if="status.location_monitoring?.monitored"
        cols="12"
      >
        <HrLocationMonitor
          :monitoring="status.location_monitoring"
          :clock-offset="clockOffset"
          :timezone="status.timezone"
          :online="online"
          :paused="busy"
          @update="applyStatus"
          @refresh="fetchStatus"
        />
      </VCol>

      <VCol cols="12">
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          icon="tabler-info-circle"
        >
          {{ $t(status.location_monitoring?.monitored ? 'hr.my_attendance.foreground_only_monitored' : 'hr.my_attendance.foreground_only') }}
          {{ $t('hr.my_attendance.server_time_note', { timezone: status.timezone }) }}
        </VAlert>
      </VCol>
    </VRow>
  </div>
</template>

<style lang="scss" scoped>
.hr-attendance-clock {
  font-size: 2.5rem;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  line-height: 1.2;
}

.hr-attendance-details :deep(.v-col) {
  flex: 0 0 50%;
  max-inline-size: 50%;
}
</style>
