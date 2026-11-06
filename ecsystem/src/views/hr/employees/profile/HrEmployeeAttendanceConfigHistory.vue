<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'
import { useI18n } from 'vue-i18n'

/** Effective-dated attendance configuration rows of an employee, latest first. */
const props = defineProps({
  employee: { type: Object, required: true },
  canConfigure: { type: Boolean, default: false },
})

const emit = defineEmits(['changed'])

const { t } = useI18n()

const rows = ref([])
const loading = ref(false)
const cancelTarget = ref(null)

const load = () => {
  loading.value = true

  return hrEmployeesApi.attendanceConfigs(props.employee.id).then(response => {
    rows.value = response.data.data ?? []
  }).catch(() => {
    rows.value = []
  }).finally(() => {
    loading.value = false
  })
}

onMounted(load)

const state = row => {
  if (row.is_scheduled)
    return { color: 'info', label: t('hr.records.scheduled') }
  if (row.is_current)
    return { color: 'success', label: t('hr.records.current') }

  return { color: 'secondary', label: t('hr.records.past') }
}

const confirmCancel = () => {
  const row = cancelTarget.value

  cancelTarget.value = null
  if (!row)
    return
  hrEmployeesApi.cancelAttendanceConfig(props.employee.id, row.id).then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    load()
    emit('changed')
  }).catch(() => {})
}

defineExpose({ refresh: load })
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>{{ $t('hr.attendance_config.history_title') }}</VCardTitle>
      <VCardSubtitle>{{ $t('hr.attendance_config.history_subtitle') }}</VCardSubtitle>
    </VCardItem>
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
          <th>{{ $t('hr.fields.attendance_mode') }}</th>
          <th>{{ $t('hr.attendance_config.gps_verification') }}</th>
          <th>{{ $t('hr.fields.attendance_policy') }}</th>
          <th>{{ $t('hr.fields.grace_minutes') }}</th>
          <th>{{ $t('hr.fields.change_reason') }}</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, index) in rows"
          :key="row.id"
        >
          <td dir="ltr">
            {{ row.effective_from }}
          </td>
          <td dir="ltr">
            {{ row.effective_to || '—' }}
          </td>
          <td>{{ row.attendance_mode_label }}</td>
          <td>{{ row.attendance_mode === 'electronic' ? row.gps_policy_label : '—' }}</td>
          <td>
            <span v-if="row.attendance_policy">
              {{ row.attendance_policy.name }}
              <VChip
                v-if="row.attendance_policy.deleted_at"
                size="x-small"
                label
                color="error"
                class="ms-1"
              >
                {{ $t('hr.attendance_config.deleted_policy') }}
              </VChip>
            </span>
            <span v-else>{{ $t('hr.attendance_config.center_defaults') }}</span>
          </td>
          <td>{{ row.grace_minutes ?? $t('hr.shifts.inherited') }}</td>
          <td>
            <div>{{ row.reason || '—' }}</div>
            <div
              v-if="row.created_by"
              class="hr-page__muted text-caption"
            >
              {{ row.created_by }}
            </div>
          </td>
          <td>
            <div class="d-flex align-center gap-1">
              <VChip
                size="x-small"
                label
                :color="state(row).color"
              >
                {{ state(row).label }}
              </VChip>
              <VBtn
                v-if="canConfigure && row.is_scheduled && index === 0"
                icon
                size="x-small"
                variant="text"
                color="error"
                @click="cancelTarget = row"
              >
                <VIcon icon="tabler-calendar-x" />
                <VTooltip activator="parent">
                  {{ $t('hr.records.cancel_scheduled') }}
                </VTooltip>
              </VBtn>
            </div>
          </td>
        </tr>
        <tr v-if="!rows.length && !loading">
          <td
            colspan="8"
            class="text-center hr-page__muted"
          >
            {{ $t('hr.attendance_config.no_history') }}
          </td>
        </tr>
      </tbody>
    </VTable>

    <HrConfirmDialog
      :model-value="!!cancelTarget"
      :message="$t('hr.records.confirm_cancel')"
      :confirm-label="$t('hr.records.cancel_scheduled')"
      @update:model-value="cancelTarget = null"
      @confirm="confirmCancel"
    />
  </VCard>
</template>
