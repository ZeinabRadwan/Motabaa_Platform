<script setup>
import { hrMyAttendanceApi } from '@/plugins/apis/hrRequest'
import { getBrowserPosition } from '@/utils/hrGeolocation'
import { LOCATION_STATUS_COLORS } from '@/utils/hrLocationMonitoring'
import { useI18n } from 'vue-i18n'

/*
 * Periodic location checks while the employee is checked in. The server owns the schedule (next_check_at); this
 * page only answers checks that are due while it is open and visible. A browser cannot read the location of a
 * closed or suspended page, so checks missed meanwhile stay "pending" on the server; they are never treated as
 * verified, nor as absence.
 *
 * A check keeps its client id until the server answers, so a request retried after a network failure is stored once.
 */
const props = defineProps({
  monitoring: { type: Object, default: null },
  clockOffset: { type: Number, default: 0 },
  timezone: { type: String, default: 'Asia/Riyadh' },
  online: { type: Boolean, default: true },
  paused: { type: Boolean, default: false },
})

const emit = defineEmits(['update', 'refresh'])

const { t, locale } = useI18n()

const EARLY_TOLERANCE_MS = 60000
const NETWORK_RETRY_MS = 30000
const SERVER_RETRY_MS = 60000
const FIX_MAX_AGE_MS = 120000
const HISTORY_PREVIEW = 6

const now = ref(Date.now())
const visible = ref(typeof document === 'undefined' ? true : document.visibilityState === 'visible')
const phase = ref(null)
const pending = ref(null)
const feedback = ref(null)
const blockedUntil = ref(0)
const showAllChecks = ref(false)

const active = computed(() => !!props.monitoring?.active)
const serverNow = computed(() => now.value + props.clockOffset)
const nextCheckAt = computed(() => (props.monitoring?.next_check_at ? new Date(props.monitoring.next_check_at).getTime() : null))

const due = computed(() => {
  if (!active.value)
    return false
  if (props.monitoring.due)
    return true

  return nextCheckAt.value !== null && serverNow.value >= nextCheckAt.value - EARLY_TOLERANCE_MS
})

const pad = value => String(value).padStart(2, '0')

const countdown = computed(() => {
  if (!active.value || nextCheckAt.value === null || due.value)
    return null
  const seconds = Math.max(0, Math.round((nextCheckAt.value - serverNow.value) / 1000))

  return `${pad(Math.floor(seconds / 60))}:${pad(seconds % 60)}`
})

const formatClock = iso => new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA-u-nu-latn' : 'en-GB', {
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
  timeZone: props.timezone || 'Asia/Riyadh',
}).format(new Date(iso))

const statusKey = computed(() => props.monitoring?.status ?? 'pending')

const checkDetail = check => {
  const parts = []
  if (check.distance_meters !== null && check.distance_meters !== undefined)
    parts.push(t('hr.location_monitoring.distance', { distance: Math.round(check.distance_meters), radius: check.radius_meters ?? '—' }))
  if (check.accuracy_meters !== null && check.accuracy_meters !== undefined)
    parts.push(t('hr.my_attendance.accuracy_short', { accuracy: Math.round(check.accuracy_meters) }))
  if (check.reason)
    parts.push(t(`hr.location_monitoring.reasons.${check.reason}`))

  return parts.join(' · ')
}

const checkStatusLabel = check => (check.missed ? t('hr.location_monitoring.missed') : t(`hr.location_monitoring.statuses.${check.status}`))
const checkStatusColor = check => (check.missed ? 'warning' : LOCATION_STATUS_COLORS[check.status] ?? 'secondary')

const lastCheck = computed(() => props.monitoring?.last_check ?? null)
const checks = computed(() => props.monitoring?.checks ?? [])
const visibleChecks = computed(() => (showAllChecks.value ? checks.value : checks.value.slice(0, HISTORY_PREVIEW)))

const clientId = () => {
  if (typeof crypto !== 'undefined' && crypto.randomUUID)
    return crypto.randomUUID()

  return `${Date.now().toString(16)}-${Math.random().toString(16).slice(2, 14)}`
}

const readPosition = async () => {
  try {
    return await getBrowserPosition()
  }
  catch (error) {
    return { location_error: error.code }
  }
}

let retryTimer = null

const scheduleRetry = delay => {
  clearTimeout(retryTimer)
  retryTimer = setTimeout(() => {
    retryTimer = null
    maybeCheck()
  }, delay)
}

const reset = () => {
  clearTimeout(retryTimer)
  retryTimer = null
  pending.value = null
}

const RESCHEDULE_REASONS = ['location_check_not_due', 'location_check_no_session', 'location_monitoring_off', 'location_monitoring_ended']

const submit = async () => {
  const attempt = pending.value
  if (!attempt.readAt || Date.now() - attempt.readAt > FIX_MAX_AGE_MS) {
    phase.value = 'locating'
    attempt.payload = await readPosition()
    attempt.readAt = Date.now()
  }

  phase.value = 'sending'
  try {
    const { data } = await hrMyAttendanceApi.locationCheck({ ...attempt.payload, client_id: attempt.clientId })
    const check = data.data.check

    reset()
    feedback.value = {
      type: check.status === 'within_radius' ? 'success' : 'warning',
      text: data.message,
      detail: checkDetail(check),
    }
    emit('update', data.data)
  }
  catch (error) {
    const response = error.response
    if (!response) {
      feedback.value = { type: 'warning', icon: 'tabler-wifi-off', text: t('hr.location_monitoring.network_retry') }
      scheduleRetry(NETWORK_RETRY_MS)
    }
    else if (response.status === 429 || response.status >= 500) {
      feedback.value = { type: 'warning', text: t('hr.location_monitoring.server_retry') }
      scheduleRetry(SERVER_RETRY_MS)
    }
    else {
      reset()
      if (!RESCHEDULE_REASONS.includes(response.data?.reason)) {
        feedback.value = { type: 'error', text: Object.values(response.data?.errors ?? {}).flat()[0] ?? t('hr.my_attendance.unexpected_error') }
        blockedUntil.value = Date.now() + 5 * 60000
      }
      emit('refresh')
    }
  }
  finally {
    phase.value = null
  }
}

// Runs whenever a check may be needed: due time reached, page visible again, back online, punch finished.
const maybeCheck = () => {
  if (!active.value || phase.value || props.paused || !visible.value || !props.online || retryTimer)
    return
  if (pending.value) {
    submit()

    return
  }
  if (!due.value || Date.now() < blockedUntil.value)
    return

  pending.value = { clientId: clientId(), payload: null, readAt: null }
  submit()
}

watch([due, visible, () => props.online, () => props.paused], () => maybeCheck())

watch(() => props.online, isOnline => {
  if (isOnline && pending.value) {
    clearTimeout(retryTimer)
    retryTimer = null
    maybeCheck()
  }
})

// Check-out (or the end of the shift) ends monitoring: nothing is read or sent afterwards.
watch(active, isActive => {
  if (!isActive) {
    reset()
    blockedUntil.value = 0
  }
})

const onVisibility = () => {
  visible.value = document.visibilityState === 'visible'
}

let ticker = null

onMounted(() => {
  ticker = setInterval(() => {
    now.value = Date.now()
  }, 1000)
  document.addEventListener('visibilitychange', onVisibility)
  maybeCheck()
})

onBeforeUnmount(() => {
  clearInterval(ticker)
  clearTimeout(retryTimer)
  document.removeEventListener('visibilitychange', onVisibility)
})
</script>

<template>
  <VCard v-if="monitoring?.monitored">
    <VCardItem>
      <VCardTitle>{{ $t('hr.location_monitoring.my_title') }}</VCardTitle>
      <VCardSubtitle>
        {{ $t('hr.location_monitoring.every', { minutes: monitoring.interval_minutes }) }}
      </VCardSubtitle>
      <template #append>
        <VChip
          :color="LOCATION_STATUS_COLORS[statusKey] ?? 'secondary'"
          label
          prepend-icon="tabler-map-pin"
        >
          {{ $t(`hr.location_monitoring.statuses.${statusKey}`) }}
        </VChip>
      </template>
    </VCardItem>

    <VCardText>
      <VRow class="mb-1">
        <VCol
          cols="12"
          sm="4"
        >
          <div class="hr-page__muted text-body-2">
            {{ $t('hr.location_monitoring.next_check') }}
          </div>
          <div
            v-if="phase"
            class="d-flex align-center gap-2 text-body-1"
          >
            <VProgressCircular
              indeterminate
              size="16"
              width="2"
            />
            {{ $t(phase === 'locating' ? 'hr.my_attendance.locating' : 'hr.my_attendance.submitting') }}
          </div>
          <div
            v-else-if="active && monitoring.next_check_time"
            class="text-h6"
            dir="ltr"
          >
            {{ monitoring.next_check_time }}
            <span
              v-if="countdown"
              class="text-body-2 hr-page__muted"
            >({{ countdown }})</span>
          </div>
          <div
            v-else-if="active && due"
            class="text-body-1"
          >
            {{ $t('hr.location_monitoring.due_now') }}
          </div>
          <div
            v-else
            class="text-body-1"
          >
            {{ $t('hr.location_monitoring.stopped') }}
          </div>
        </VCol>
        <VCol
          cols="12"
          sm="4"
        >
          <div class="hr-page__muted text-body-2">
            {{ $t('hr.location_monitoring.last_check') }}
          </div>
          <div
            class="text-h6"
            dir="ltr"
          >
            {{ lastCheck?.time ?? '—' }}
          </div>
          <div
            v-if="lastCheck"
            class="text-caption hr-page__muted"
          >
            {{ checkDetail(lastCheck) }}
          </div>
        </VCol>
        <VCol
          cols="12"
          sm="4"
        >
          <div class="hr-page__muted text-body-2">
            {{ $t('hr.location_monitoring.missed_checks') }}
          </div>
          <div
            class="text-h6"
            :class="{ 'text-warning': monitoring.missed_count }"
          >
            {{ monitoring.missed_count }}
          </div>
        </VCol>
      </VRow>

      <VAlert
        v-if="feedback"
        :type="feedback.type"
        :icon="feedback.icon"
        variant="tonal"
        density="compact"
        class="mb-2"
        closable
        @click:close="feedback = null"
      >
        <div>{{ feedback.text }}</div>
        <div
          v-if="feedback.detail"
          class="text-body-2"
        >
          {{ feedback.detail }}
        </div>
      </VAlert>

      <VAlert
        v-if="monitoring.open_exception"
        type="error"
        variant="tonal"
        density="compact"
        icon="tabler-map-pin-off"
        class="mb-2"
      >
        {{ $t('hr.location_monitoring.outside_alert') }}
      </VAlert>

      <VAlert
        v-if="monitoring.missed_count && active"
        type="warning"
        variant="tonal"
        density="compact"
        class="mb-2"
      >
        {{ $t('hr.location_monitoring.missed_note') }}
      </VAlert>

      <div
        v-if="checks.length"
        class="mt-2"
      >
        <div class="text-body-1 font-weight-medium mb-2">
          {{ $t('hr.location_monitoring.session_checks') }}
        </div>
        <VList
          density="compact"
          class="py-0"
        >
          <VListItem
            v-for="check in visibleChecks"
            :key="check.id"
            class="px-0"
          >
            <template #prepend>
              <span
                class="hr-check-time me-3"
                dir="ltr"
              >{{ check.time ?? '—' }}</span>
            </template>
            <VListItemTitle class="d-flex flex-wrap align-center gap-2">
              <VChip
                :color="checkStatusColor(check)"
                size="x-small"
                label
              >
                {{ checkStatusLabel(check) }}
              </VChip>
              <span class="text-body-2">{{ $t(`hr.location_monitoring.kinds.${check.kind}`) }}</span>
            </VListItemTitle>
            <VListItemSubtitle v-if="checkDetail(check)">
              {{ checkDetail(check) }}
            </VListItemSubtitle>
          </VListItem>
        </VList>
        <VBtn
          v-if="checks.length > HISTORY_PREVIEW"
          variant="text"
          size="small"
          @click="showAllChecks = !showAllChecks"
        >
          {{ showAllChecks ? $t('hr.location_monitoring.show_less') : $t('hr.location_monitoring.show_all', { count: checks.length }) }}
        </VBtn>
      </div>

      <div class="text-caption hr-page__muted mt-3">
        {{ $t('hr.location_monitoring.privacy_note') }}
      </div>
    </VCardText>
  </VCard>
</template>

<style lang="scss" scoped>
.hr-check-time {
  font-variant-numeric: tabular-nums;
  min-inline-size: 3rem;
}
</style>
