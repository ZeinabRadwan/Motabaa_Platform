<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { useHrEmployeeRecords } from '@/composables/useHrEmployeeRecords'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  employee: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const { t } = useI18n()
const { lookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-employees', 'edit_hr-employees'))

const records = useHrEmployeeRecords(hrEmployeesApi.workAssignments, () => props.employee.id, {
  emptyForm: () => ({
    branch_id: props.employee.branch_id,
    work_location_id: null,
    effective_from: null,
    reason: '',
  }),
  onChanged: () => emit('changed'),
})

const { rows, loading, form, isDialogVisible, saving, cancelTarget } = records

const next = computed(() => props.employee.work_assignment?.next ?? null)

const locationOptions = computed(() => lookups.value.work_locations.filter(option => option.branch_id === form.value.branch_id))

watch(() => form.value.branch_id, () => {
  if (!locationOptions.value.some(option => option.value === form.value.work_location_id))
    form.value.work_location_id = null
})

onMounted(() => {
  records.fetchRows()
})

watch(() => props.employee.id, () => records.fetchRows())

const rowState = row => {
  if (row.is_scheduled)
    return { color: 'info', label: t('hr.records.scheduled') }
  if (row.is_current)
    return { color: 'success', label: t('hr.records.current') }

  return { color: 'secondary', label: t('hr.records.past') }
}
</script>

<template>
  <VCard class="mb-4">
    <VCardItem>
      <VCardTitle>{{ $t('hr.assignments.title') }}</VCardTitle>
      <template #append>
        <VBtn
          v-if="canEdit && employee.is_employed"
          size="small"
          prepend-icon="tabler-arrows-exchange"
          @click="records.openDialog"
        >
          {{ $t('hr.assignments.transfer') }}
        </VBtn>
      </template>
    </VCardItem>

    <VCardText
      v-if="next"
      class="pt-0"
    >
      <VAlert
        type="info"
        variant="tonal"
        density="compact"
      >
        {{ $t('hr.assignments.next_transfer', {
          branch: next.branch_name,
          location: next.work_location_name || $t('hr.assignments.no_location'),
          date: next.effective_from,
        }) }}
      </VAlert>
    </VCardText>

    <VTable
      density="compact"
      class="text-no-wrap"
    >
      <thead>
        <tr>
          <th>{{ $t('hr.fields.effective_from') }}</th>
          <th>{{ $t('hr.fields.effective_to') }}</th>
          <th>{{ $t('hr.fields.branch') }}</th>
          <th>{{ $t('hr.fields.work_location') }}</th>
          <th>{{ $t('hr.fields.change_reason') }}</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in rows"
          :key="row.id"
        >
          <td dir="ltr">
            {{ row.effective_from }}
          </td>
          <td dir="ltr">
            {{ row.effective_to || '—' }}
          </td>
          <td>{{ row.branch_name }}</td>
          <td>
            {{ row.work_location_name || '—' }}
            <VIcon
              v-if="row.work_location && !row.work_location.has_coordinates"
              icon="tabler-map-pin-off"
              size="16"
              color="warning"
              class="ms-1"
            >
              <VTooltip activator="parent">
                {{ $t('hr.organization.no_coordinates') }}
              </VTooltip>
            </VIcon>
          </td>
          <td>{{ row.reason || '—' }}</td>
          <td>
            <div class="d-flex align-center gap-1">
              <VChip
                size="x-small"
                label
                :color="rowState(row).color"
              >
                {{ rowState(row).label }}
              </VChip>
              <VBtn
                v-if="canEdit && row.is_scheduled && row === rows[0]"
                icon
                size="x-small"
                variant="text"
                color="error"
                @click="records.askCancel(row)"
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
            colspan="6"
            class="text-center hr-page__muted"
          >
            {{ $t('hr.assignments.empty') }}
          </td>
        </tr>
      </tbody>
    </VTable>
    <VCardText class="hr-page__muted">
      {{ $t('hr.assignments.hint') }}
    </VCardText>

    <VDialog
      v-model="isDialogVisible"
      max-width="560"
    >
      <VCard :title="$t('hr.assignments.transfer')">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppSelect
                v-model="form.branch_id"
                :items="lookups.branches"
                :label="`${$t('hr.fields.branch')} *`"
                :error-messages="records.errorsOf('branch_id')"
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="form.work_location_id"
                :items="locationOptions"
                :label="$t('hr.fields.work_location')"
                clearable
                clear-icon="tabler-x"
                :error-messages="records.errorsOf('work_location_id')"
              />
            </VCol>
            <VCol cols="12">
              <AppDateTimePicker
                v-model="form.effective_from"
                :label="`${$t('hr.fields.effective_from')} *`"
                :hint="$t('hr.assignments.effective_hint')"
                persistent-hint
                :error-messages="records.errorsOf('effective_from')"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="form.reason"
                :label="`${$t('hr.fields.change_reason')} *`"
                :error-messages="records.errorsOf('reason')"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isDialogVisible = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            :loading="saving"
            @click="records.save"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <HrConfirmDialog
      :model-value="!!cancelTarget"
      :message="$t('hr.records.confirm_cancel')"
      :confirm-label="$t('hr.records.cancel_scheduled')"
      @update:model-value="cancelTarget = null"
      @confirm="records.confirmCancel"
    />
  </VCard>
</template>
