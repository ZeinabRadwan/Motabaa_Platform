<script setup>
import { hrAttendanceRecordsApi } from '@/plugins/apis/hrRequest'
import { HISTORY_FIELDS } from '@/utils/hrAttendanceRecords'
import { formatMinutes } from '@/utils/hrShifts'
import { useI18n } from 'vue-i18n'

// Every manual creation and change of an attendance record: who, when, why, and the previous / new values.
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  recordId: { type: Number, default: null },
})

const emit = defineEmits(['update:modelValue'])

const { t, locale } = useI18n()

const ACTION_APPEARANCE = {
  created: { color: 'success', icon: 'tabler-plus' },
  updated: { color: 'info', icon: 'tabler-edit' },
  check_in_corrected: { color: 'warning', icon: 'tabler-login' },
  check_out_corrected: { color: 'warning', icon: 'tabler-logout' },
}

const record = ref(null)
const changes = ref([])
const loading = ref(false)

watch(() => [props.modelValue, props.recordId], ([visible, id]) => {
  if (!visible || !id)
    return
  loading.value = true
  record.value = null
  changes.value = []
  hrAttendanceRecordsApi.history(id).then(({ data }) => {
    record.value = data.data.record
    changes.value = data.data.changes
  }).finally(() => {
    loading.value = false
  })
}, { immediate: true })

const formatValue = (field, value) => {
  if (value === null || value === undefined || value === '')
    return '—'
  if (field === 'attendance_status')
    return t(`hr.attendance_records.statuses.${value}`)
  if (field === 'status')
    return t(`hr.attendance_records.session_statuses.${value}`)
  if (field === 'entry_reason')
    return t(`hr.attendance_records.entry_reasons.${value}`)
  if (['late_minutes', 'early_departure_minutes', 'worked_minutes'].includes(field))
    return formatMinutes(value, t)

  return String(value)
}

const rows = change => HISTORY_FIELDS
  .filter(field => field in (change.new_values ?? {}) || field in (change.old_values ?? {}))
  .map(field => ({
    field,
    old: change.old_values ? formatValue(field, change.old_values[field]) : null,
    new: formatValue(field, change.new_values?.[field]),
  }))

const formatTime = iso => new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-SA-u-ca-gregory-nu-latn' : 'en-GB', {
  dateStyle: 'medium',
  timeStyle: 'short',
}).format(new Date(iso))
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="760"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      :title="$t('hr.attendance_records.history_title')"
      :subtitle="record ? `${record.employee?.name ?? ''} · ${record.attendance_date} · ${$t(`hr.attendance_records.methods.${record.attendance_method}`)}` : ''"
    >
      <VCardText>
        <div
          v-if="loading"
          class="text-center py-6"
        >
          <VProgressCircular indeterminate />
        </div>
        <VAlert
          v-else-if="!changes.length"
          type="info"
          variant="tonal"
          density="compact"
        >
          {{ $t('hr.attendance_records.history_empty') }}
        </VAlert>
        <VTimeline
          v-else
          side="end"
          align="start"
          density="compact"
          truncate-line="both"
        >
          <VTimelineItem
            v-for="change in changes"
            :key="change.id"
            :dot-color="ACTION_APPEARANCE[change.action]?.color ?? 'secondary'"
            :icon="ACTION_APPEARANCE[change.action]?.icon"
            size="small"
          >
            <div class="d-flex flex-wrap align-center gap-2 mb-1">
              <span class="font-weight-medium">{{ $t(`hr.attendance_records.actions.${change.action}`) }}</span>
              <VChip
                v-if="change.source !== 'form'"
                size="x-small"
                label
              >
                {{ $t(`hr.attendance_records.sources.${change.source}`) }}
              </VChip>
              <VChip
                v-if="change.via_impersonation"
                size="x-small"
                label
                color="warning"
              >
                {{ $t('hr.attendance_records.via_impersonation') }}
              </VChip>
            </div>
            <div class="text-caption hr-page__muted mb-2">
              {{ change.changed_by?.name ?? $t('hr.attendance_records.system') }} · <span dir="ltr">{{ formatTime(change.created_at) }}</span>
            </div>
            <div
              v-if="change.reason"
              class="text-body-2 mb-2"
            >
              <span class="hr-page__muted">{{ $t('hr.attendance_records.modification_reason') }}:</span> {{ change.reason }}
            </div>
            <VTable
              density="compact"
              class="hr-history-table"
            >
              <thead>
                <tr>
                  <th>{{ $t('hr.attendance_records.field') }}</th>
                  <th v-if="change.old_values">
                    {{ $t('hr.attendance_records.previous_value') }}
                  </th>
                  <th>{{ $t('hr.attendance_records.new_value') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in rows(change)"
                  :key="row.field"
                >
                  <td>{{ $t(`hr.attendance_records.fields.${row.field}`) }}</td>
                  <td
                    v-if="change.old_values"
                    class="text-decoration-line-through hr-page__muted"
                    dir="auto"
                  >
                    {{ row.old }}
                  </td>
                  <td dir="auto">
                    {{ row.new }}
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VTimelineItem>
        </VTimeline>
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

<style lang="scss" scoped>
.hr-history-table {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
}
</style>
