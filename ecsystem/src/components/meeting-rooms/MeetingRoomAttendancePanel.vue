<script setup>
import { computed, ref, watch } from 'vue'
import { roomsApi } from '@/plugins/apis/roomsReqest'
import ability from '@/plugins/casl/ability'

const props = defineProps({
  roomId: { type: Number, required: true },
})

const roomsReqest = roomsApi()
const loading = ref(false)
const report = ref(null)

const canView = computed(() => (
  ability.can('edit_meetings', 'edit_meetings')
  || ability.can('admin_meetings', 'admin_meetings')
))

const loadReport = () => {
  if (! canView.value)
    return

  loading.value = true
  roomsReqest.fetchAttendance(props.roomId)
    .then(res => {
      report.value = res.data?.data ?? null
    })
    .finally(() => {
      loading.value = false
    })
}

watch(() => props.roomId, loadReport, { immediate: true })

defineExpose({ refresh: loadReport })

const formatDateTime = value => {
  if (! value)
    return '—'
  try {
    return new Date(value).toLocaleString()
  }
  catch {
    return value
  }
}

const statusLabel = row => {
  if (row.in_meeting)
    return 'rooms.attendance_in_meeting'
  if (row.attended)
    return 'rooms.attendance_present'
  return 'rooms.attendance_absent'
}

const statusColor = row => {
  if (row.in_meeting)
    return 'primary'
  if (row.attended)
    return 'success'
  return 'secondary'
}
</script>

<template>
  <VCard
    v-if="canView"
    class="meeting-attendance-panel mt-4"
    variant="outlined"
  >
    <VCardItem>
      <VCardTitle class="text-body-1 font-weight-bold">
        {{ $t('rooms.attendance_title') }}
      </VCardTitle>
      <template #append>
        <VBtn
          size="small"
          variant="text"
          icon="tabler-refresh"
          :loading="loading"
          @click="loadReport"
        />
      </template>
    </VCardItem>
    <VDivider />
    <VCardText>
      <div
        v-if="loading && !report"
        class="d-flex justify-center py-4"
      >
        <VProgressCircular
          indeterminate
          size="32"
        />
      </div>
      <template v-else-if="report">
        <VAlert
          v-if="report.expected_limited"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-3"
        >
          {{ $t('rooms.attendance_public_hint') }}
        </VAlert>
        <div
          v-if="report.summary?.expected_count != null"
          class="meeting-attendance-panel__summary mb-3"
        >
          <VChip
            size="small"
            variant="tonal"
            class="me-1 mb-1"
          >
            {{ $t('rooms.attendance_expected', { count: report.summary.expected_count }) }}
          </VChip>
          <VChip
            size="small"
            color="success"
            variant="tonal"
            class="me-1 mb-1"
          >
            {{ $t('rooms.attendance_attended', { count: report.summary.attended_count }) }}
          </VChip>
          <VChip
            size="small"
            color="secondary"
            variant="tonal"
            class="mb-1"
          >
            {{ $t('rooms.attendance_absent_count', { count: report.summary.absent_count }) }}
          </VChip>
        </div>
        <div
          v-else-if="report.summary?.attended_count != null"
          class="mb-3"
        >
          <VChip
            size="small"
            color="success"
            variant="tonal"
          >
            {{ $t('rooms.attendance_joined_count', { count: report.summary.attended_count }) }}
          </VChip>
        </div>

        <div class="meeting-attendance-panel__table-wrap">
          <table class="meeting-attendance-panel__table">
            <thead>
              <tr>
                <th>{{ $t('Name') }}</th>
                <th>{{ $t('rooms.attendance_status') }}</th>
                <th>{{ $t('rooms.attendance_joined_at') }}</th>
                <th>{{ $t('rooms.attendance_left_at') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in report.attendees"
                :key="row.user_id"
              >
                <td>{{ row.name }}</td>
                <td>
                  <VChip
                    size="x-small"
                    :color="statusColor(row)"
                    variant="tonal"
                  >
                    {{ $t(statusLabel(row)) }}
                  </VChip>
                </td>
                <td>{{ formatDateTime(row.last_joined_at) }}</td>
                <td>{{ formatDateTime(row.last_left_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="report.other_participants?.length"
          class="mt-4"
        >
          <p class="meeting-attendance-panel__subtitle">
            {{ $t('rooms.attendance_other_participants') }}
          </p>
          <div class="meeting-attendance-panel__table-wrap">
            <table class="meeting-attendance-panel__table">
              <thead>
                <tr>
                  <th>{{ $t('Name') }}</th>
                  <th>{{ $t('rooms.attendance_status') }}</th>
                  <th>{{ $t('rooms.attendance_joined_at') }}</th>
                  <th>{{ $t('rooms.attendance_left_at') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in report.other_participants"
                  :key="`other-${row.user_id}`"
                >
                  <td>{{ row.name }}</td>
                  <td>
                    <VChip
                      size="x-small"
                      :color="statusColor(row)"
                      variant="tonal"
                    >
                      {{ $t(statusLabel(row)) }}
                    </VChip>
                  </td>
                  <td>{{ formatDateTime(row.last_joined_at) }}</td>
                  <td>{{ formatDateTime(row.last_left_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <p
          v-if="!report.attendees?.length && !report.other_participants?.length"
          class="text-medium-emphasis text-body-2 mb-0"
        >
          {{ $t('rooms.attendance_empty') }}
        </p>
      </template>
    </VCardText>
  </VCard>
</template>

<style scoped lang="scss">
.meeting-attendance-panel__subtitle {
  margin: 0 0 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
}

.meeting-attendance-panel__table-wrap {
  overflow-x: auto;
}

.meeting-attendance-panel__table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;

  th,
  td {
    padding: 0.45rem 0.5rem;
    text-align: start;
    border-bottom: 1px solid rgba(18, 59, 102, 0.08);
  }

  th {
    font-weight: 600;
    color: rgba(18, 59, 102, 0.75);
  }
}
</style>
