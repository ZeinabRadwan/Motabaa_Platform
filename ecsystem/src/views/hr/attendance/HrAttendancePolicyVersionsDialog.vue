<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { hrAttendancePoliciesApi } from '@/plugins/apis/hrRequest'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  policy: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'changed'])

const { t } = useI18n()

const canEdit = computed(() => can('edit_hr-attendance-setup', 'edit_hr-attendance-setup'))

const versions = ref([])
const loading = ref(false)
const cancelTarget = ref(null)

const load = () => {
  loading.value = true
  hrAttendancePoliciesApi.versions(props.policy.id).then(response => {
    versions.value = response.data.data ?? []
  }).catch(() => {
    versions.value = []
  }).finally(() => {
    loading.value = false
  })
}

watch(() => props.modelValue, visible => {
  if (visible && props.policy)
    load()
})

const minutes = value => (value === null || value === undefined ? '—' : t('hr.attendance_setup.minutes', { minutes: value }))

const checkIn = version => `−${version.check_in_before_minutes} / ${version.check_in_after_minutes === null ? t('hr.policies.until_shift_end') : `+${version.check_in_after_minutes}`}`

const checkOut = version => `${version.check_out_before_minutes === null ? t('hr.policies.any_time') : `−${version.check_out_before_minutes}`} / +${version.check_out_after_minutes}`

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
  hrAttendancePoliciesApi.cancelVersion(props.policy.id, version.id).then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    load()
    emit('changed')
  }).catch(() => {})
}
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="1200"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      v-if="policy"
      :title="$t('hr.policies.history_title', { policy: policy.name })"
      :subtitle="$t('hr.policies.history_hint')"
    >
      <VProgressLinear
        v-if="loading"
        indeterminate
        color="primary"
      />
      <VCardText class="pa-0">
        <VTable
          density="compact"
          class="text-no-wrap"
        >
          <thead>
            <tr>
              <th>{{ $t('hr.fields.effective_from') }}</th>
              <th>{{ $t('hr.fields.effective_to') }}</th>
              <th>{{ $t('hr.policies.check_in_window') }}</th>
              <th>{{ $t('hr.policies.check_out_window') }}</th>
              <th>{{ $t('hr.policies.grace_short') }}</th>
              <th>{{ $t('hr.policies.sections.late') }}</th>
              <th>{{ $t('hr.policies.sections.early') }}</th>
              <th>{{ $t('hr.policies.missing_checkout') }}</th>
              <th>{{ $t('hr.policies.duplicate_punch') }}</th>
              <th>{{ $t('hr.policies.gps_requirement') }}</th>
              <th>{{ $t('hr.policies.manual_short') }}</th>
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
              <td>{{ checkIn(version) }}</td>
              <td>{{ checkOut(version) }}</td>
              <td>{{ version.late_grace_minutes }} / {{ version.early_leave_grace_minutes }}</td>
              <td>
                <div>{{ version.late_counting_label }} · {{ version.late_action_label }}</div>
                <div
                  v-if="version.absence_after_minutes"
                  class="hr-page__muted text-caption"
                >
                  {{ $t('hr.policies.absent_after_short', { value: minutes(version.absence_after_minutes) }) }}
                </div>
              </td>
              <td>{{ version.early_leave_counting_label }} · {{ version.early_leave_action_label }}</td>
              <td>{{ version.missing_checkout_action_label }}</td>
              <td>{{ version.duplicate_punch_action_label }} ({{ minutes(version.duplicate_window_minutes) }})</td>
              <td>{{ version.gps_requirement_label }}</td>
              <td>
                <VIcon
                  :icon="version.allow_manual_attendance ? 'tabler-check' : 'tabler-x'"
                  :color="version.allow_manual_attendance ? 'success' : 'secondary'"
                  size="18"
                />
              </td>
              <td>
                <div>{{ version.reason || '—' }}</div>
                <div
                  v-if="version.created_by"
                  class="hr-page__muted text-caption"
                >
                  {{ version.created_by }}
                </div>
              </td>
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
                colspan="13"
                class="text-center hr-page__muted"
              >
                {{ $t('hr.policies.no_history') }}
              </td>
            </tr>
          </tbody>
        </VTable>
        <div class="hr-page__muted text-caption pa-4">
          {{ $t('hr.policies.window_legend') }}
        </div>
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

    <HrConfirmDialog
      :model-value="!!cancelTarget"
      :message="$t('hr.records.confirm_cancel')"
      :confirm-label="$t('hr.records.cancel_scheduled')"
      @update:model-value="cancelTarget = null"
      @confirm="confirmCancel"
    />
  </VDialog>
</template>
