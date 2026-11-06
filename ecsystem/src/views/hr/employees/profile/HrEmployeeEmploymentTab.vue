<script setup>
import HrInfoGrid from '@/components/hr/HrInfoGrid.vue'
import HrEmployeeWorkAssignments from '@/views/hr/employees/profile/HrEmployeeWorkAssignments.vue'
import { contractTypeItems } from '@core/utils/generalItems'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  employee: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const { t } = useI18n()

const contractLabel = computed(() => contractTypeItems().find(item => item.value === props.employee.contract_type)?.title ?? null)

const items = computed(() => {
  const employee = props.employee

  return [
    { label: t('hr.fields.employee_number'), value: employee.employee_number, ltr: true },
    { label: t('hr.fields.job_title'), value: employee.job_title },
    { label: t('hr.fields.employment_status'), value: employee.employment_status_label },
    { label: t('hr.fields.department'), value: employee.department_name },
    { label: t('hr.fields.branch'), value: employee.branch_name },
    { label: t('hr.fields.manager'), value: employee.manager?.name },
    { label: t('hr.fields.hire_date'), value: employee.hire_date, ltr: true },
    { label: t('hr.fields.employment_start_date'), value: employee.employment_start_date, ltr: true },
    { label: t('hr.fields.work_location'), value: employee.work_location_name },
  ]
})

const contractItems = computed(() => [
  { label: t('hr.fields.contract_type'), value: contractLabel.value },
  { label: t('hr.fields.contract_start_date'), value: props.employee.contract_start_date, ltr: true },
  { label: t('hr.fields.contract_end_date'), value: props.employee.contract_end_date, ltr: true },
])
</script>

<template>
  <div>
    <VCard
      class="mb-4"
      :title="$t('hr.employees.tabs.employment')"
    >
      <VCardText>
        <HrInfoGrid :items="items" />
      </VCardText>
    </VCard>

    <HrEmployeeWorkAssignments
      :employee="employee"
      @changed="emit('changed')"
    />

    <VCard
      class="mb-4"
      :title="$t('hr.employees.contract_information')"
    >
      <VCardText>
        <HrInfoGrid :items="contractItems" />
      </VCardText>
    </VCard>

    <VCard :title="$t('hr.employees.employment_periods')">
      <VTable density="compact">
        <thead>
          <tr>
            <th>{{ $t('hr.fields.start_date') }}</th>
            <th>{{ $t('hr.fields.end_date') }}</th>
            <th>{{ $t('hr.fields.termination_reason') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="period in employee.employment_periods"
            :key="period.id"
          >
            <td dir="ltr">
              {{ period.start_date }}
            </td>
            <td>
              <span
                v-if="period.end_date"
                dir="ltr"
              >{{ period.end_date }}</span>
              <VChip
                v-else
                size="x-small"
                label
                color="success"
              >
                {{ $t('hr.employees.ongoing') }}
              </VChip>
            </td>
            <td>{{ period.end_reason_label || '—' }}</td>
          </tr>
        </tbody>
      </VTable>
      <VCardText class="hr-page__muted">
        {{ $t('hr.employees.periods_hint') }}
      </VCardText>
    </VCard>
  </div>
</template>
