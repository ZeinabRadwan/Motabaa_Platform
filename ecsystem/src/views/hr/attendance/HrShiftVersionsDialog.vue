<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { hrShiftsApi } from '@/plugins/apis/hrRequest'
import { formatMinutes } from '@/utils/hrShifts'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  shift: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'changed'])

const { t } = useI18n()

const canEdit = computed(() => can('edit_hr-attendance-setup', 'edit_hr-attendance-setup'))

const versions = ref([])
const loading = ref(false)
const cancelTarget = ref(null)

const load = () => {
  loading.value = true
  hrShiftsApi.versions(props.shift.id).then(response => {
    versions.value = response.data.data ?? []
  }).catch(() => {
    versions.value = []
  }).finally(() => {
    loading.value = false
  })
}

watch(() => props.modelValue, visible => {
  if (visible && props.shift)
    load()
})

const timing = version => {
  if (props.shift?.is_rotating)
    return t('hr.shifts.cycle_summary', { days: version.rotation_pattern.length, rest: version.rotation_pattern.filter(slot => slot === null).length })

  return `${version.start_time} - ${version.end_time}${version.is_overnight ? ` (${t('hr.shifts.next_day')})` : ''}`
}

const days = list => list.map(day => t(`hr.weekdays.${day}`)).join('، ') || '—'

const state = version => {
  if (version.is_scheduled)
    return { color: 'info', label: t('hr.records.scheduled') }
  if (version.is_current)
    return { color: 'success', label: t('hr.records.current') }

  return { color: 'secondary', label: t('hr.records.past') }
}

const confirmCancel = () => {
  const version = cancelTarget.value

  cancelTarget.value = null
  if (!version)
    return
  hrShiftsApi.cancelVersion(props.shift.id, version.id).then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    load()
    emit('changed')
  }).catch(() => {})
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="980"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      v-if="shift"
      :title="$t('hr.shifts.history_title', { shift: shift.name })"
      :subtitle="$t('hr.shifts.history_hint')"
    >
      <VProgressLinear
        v-if="loading"
        indeterminate
        color="primary"
      />
      <VTable
        density="compact"
        class="text-no-wrap"
      >
        <thead>
          <tr>
            <th>{{ $t('hr.fields.effective_from') }}</th>
            <th>{{ $t('hr.fields.effective_to') }}</th>
            <th>{{ $t('hr.attendance_setup.time_range') }}</th>
            <th v-if="!shift.is_rotating">
              {{ $t('hr.attendance_setup.break_minutes') }}
            </th>
            <th v-if="!shift.is_rotating">
              {{ $t('hr.shifts.expected_hours') }}
            </th>
            <th v-if="!shift.is_rotating">
              {{ $t('hr.fields.working_days') }}
            </th>
            <th>{{ $t('hr.attendance_setup.late_grace') }}</th>
            <th>{{ $t('hr.fields.change_reason') }}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(version, index) in versions"
            :key="version.id"
          >
            <td dir="ltr">
              {{ version.effective_from }}
            </td>
            <td dir="ltr">
              {{ version.effective_to || '—' }}
            </td>
            <td dir="ltr">
              {{ timing(version) }}
            </td>
            <td v-if="!shift.is_rotating">
              {{ version.break_start_time ? `${version.break_start_time} · ` : '' }}{{ formatMinutes(version.break_minutes, t) }}
            </td>
            <td v-if="!shift.is_rotating">
              {{ formatMinutes(version.effective_expected_minutes, t) }}
            </td>
            <td v-if="!shift.is_rotating">
              {{ days(version.working_days) }}
            </td>
            <td>{{ version.grace_minutes ?? $t('hr.shifts.inherited') }}</td>
            <td>{{ version.reason || '—' }}</td>
            <td>
              <div class="d-flex align-center gap-1">
                <VChip
                  size="x-small"
                  label
                  :color="state(version).color"
                >
                  {{ state(version).label }}
                </VChip>
                <VBtn
                  v-if="canEdit && version.is_scheduled && index === 0"
                  icon
                  size="x-small"
                  variant="text"
                  color="error"
                  @click="cancelTarget = version"
                >
                  <VIcon icon="tabler-calendar-x" />
                  <VTooltip activator="parent">
                    {{ $t('hr.records.cancel_scheduled') }}
                  </VTooltip>
                </VBtn>
              </div>
            </td>
          </tr>
          <tr v-if="!versions.length && !loading">
            <td
              colspan="9"
              class="text-center hr-page__muted"
            >
              {{ $t('hr.shifts.no_history') }}
            </td>
          </tr>
        </tbody>
      </VTable>
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

    <HrConfirmDialog
      :model-value="!!cancelTarget"
      :message="$t('hr.records.confirm_cancel')"
      :confirm-label="$t('hr.records.cancel_scheduled')"
      @update:model-value="cancelTarget = null"
      @confirm="confirmCancel"
    />
  </VDialog>
</template>
