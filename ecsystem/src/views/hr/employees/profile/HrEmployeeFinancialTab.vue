<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import HrInfoGrid from '@/components/hr/HrInfoGrid.vue'
import { useHrEmployeeRecords } from '@/composables/useHrEmployeeRecords'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'
import { formatHrMoney } from '@/utils/hrFormat'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  employee: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const { t, locale } = useI18n()
const { lookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-compensation', 'edit_hr-compensation'))
const money = value => formatHrMoney(value, locale.value)

const salaries = useHrEmployeeRecords(hrEmployeesApi.salaries, () => props.employee.id, {
  emptyForm: () => {
    const current = props.employee.financial?.current_salary

    return {
      effective_from: null,
      basic_salary: current?.basic_salary ?? null,
      housing_allowance: current?.housing_allowance ?? 0,
      transportation_allowance: current?.transportation_allowance ?? 0,
      other_allowances: current?.other_allowances ?? 0,
      other_allowances_note: current?.other_allowances_note ?? '',
      reason: '',
    }
  },
  onChanged: () => emit('changed'),
})

const insurances = useHrEmployeeRecords(hrEmployeesApi.insurances, () => props.employee.id, {
  emptyForm: () => {
    const current = props.employee.financial?.current_insurance

    return {
      effective_from: null,
      category: current?.category ?? (props.employee.is_saudi ? 'saudi' : 'non_saudi'),
      employee_rate: current?.employee_rate ?? null,
      employer_rate: current?.employer_rate ?? null,
      contribution_start_date: current?.contribution_start_date ?? props.employee.employment_start_date,
      subscription_number: current?.subscription_number ?? '',
      reason: '',
    }
  },
  onChanged: () => emit('changed'),
})

onMounted(() => {
  salaries.fetchRows()
  insurances.fetchRows()
})

const salaryForm = salaries.form

const previewGross = computed(() => ['basic_salary', 'housing_allowance', 'transportation_allowance', 'other_allowances']
  .reduce((sum, key) => sum + Math.round(Number(salaryForm.value[key] || 0) * 100), 0) / 100)

const currentSalaryItems = computed(() => {
  const salary = props.employee.financial?.current_salary

  return [
    { label: t('hr.fields.basic_salary'), value: salary ? money(salary.basic_salary) : null },
    { label: t('hr.fields.housing_allowance'), value: salary ? money(salary.housing_allowance) : null },
    { label: t('hr.fields.transportation_allowance'), value: salary ? money(salary.transportation_allowance) : null },
    { label: t('hr.fields.other_allowances'), value: salary ? money(salary.other_allowances) : null },
    { label: t('hr.fields.gross_salary'), value: salary ? `${money(salary.gross_salary)} ${salary.currency}` : null },
    { label: t('hr.fields.effective_from'), value: salary?.effective_from, ltr: true },
  ]
})

const currentInsuranceItems = computed(() => {
  const insurance = props.employee.financial?.current_insurance

  return [
    { label: t('hr.fields.insurance_category'), value: insurance?.category_label },
    { label: t('hr.fields.employee_rate'), value: insurance ? `${insurance.employee_rate}%` : null, ltr: true },
    { label: t('hr.fields.employer_rate'), value: insurance ? `${insurance.employer_rate}%` : null, ltr: true },
    { label: t('hr.fields.insurance_rate'), value: insurance ? `${insurance.total_rate}%` : null, ltr: true },
    { label: t('hr.fields.contribution_start_date'), value: insurance?.contribution_start_date, ltr: true },
    { label: t('hr.fields.subscription_number'), value: insurance?.subscription_number, ltr: true },
  ]
})

const rowState = row => {
  if (row.is_scheduled)
    return { color: 'info', label: t('hr.records.scheduled') }
  if (row.is_current)
    return { color: 'success', label: t('hr.records.current') }

  return { color: 'secondary', label: t('hr.records.past') }
}

const lockedHint = computed(() => (props.employee.changes_locked_until
  ? t('hr.records.locked_until', { date: props.employee.changes_locked_until })
  : ''))
</script>

<template>
  <div>
    <VAlert
      v-if="lockedHint"
      type="warning"
      variant="tonal"
      density="compact"
      class="mb-4"
    >
      {{ lockedHint }}
    </VAlert>

    <VCard class="mb-4">
      <VCardItem>
        <VCardTitle>{{ $t('hr.employees.salary') }}</VCardTitle>
        <template #append>
          <VBtn
            v-if="canEdit"
            size="small"
            prepend-icon="tabler-plus"
            @click="salaries.openDialog"
          >
            {{ $t('hr.records.add_salary_change') }}
          </VBtn>
        </template>
      </VCardItem>
      <VCardText>
        <HrInfoGrid :items="currentSalaryItems" />
      </VCardText>
      <VDivider />
      <VTable
        density="compact"
        class="text-no-wrap"
      >
        <thead>
          <tr>
            <th>{{ $t('hr.fields.effective_from') }}</th>
            <th>{{ $t('hr.fields.effective_to') }}</th>
            <th>{{ $t('hr.fields.basic_salary') }}</th>
            <th>{{ $t('hr.fields.housing_allowance') }}</th>
            <th>{{ $t('hr.fields.transportation_allowance') }}</th>
            <th>{{ $t('hr.fields.other_allowances') }}</th>
            <th>{{ $t('hr.fields.gross_salary') }}</th>
            <th>{{ $t('hr.fields.change_reason') }}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in salaries.rows.value"
            :key="row.id"
          >
            <td dir="ltr">
              {{ row.effective_from }}
            </td>
            <td dir="ltr">
              {{ row.effective_to || '—' }}
            </td>
            <td>{{ money(row.basic_salary) }}</td>
            <td>{{ money(row.housing_allowance) }}</td>
            <td>{{ money(row.transportation_allowance) }}</td>
            <td>{{ money(row.other_allowances) }}</td>
            <td class="font-weight-medium">
              {{ money(row.gross_salary) }}
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
                  v-if="canEdit && row.is_scheduled && row === salaries.rows.value[0]"
                  icon
                  size="x-small"
                  variant="text"
                  color="error"
                  @click="salaries.askCancel(row)"
                >
                  <VIcon icon="tabler-calendar-x" />
                  <VTooltip activator="parent">
                    {{ $t('hr.records.cancel_scheduled') }}
                  </VTooltip>
                </VBtn>
              </div>
            </td>
          </tr>
          <tr v-if="!salaries.rows.value.length && !salaries.loading.value">
            <td
              colspan="9"
              class="text-center hr-page__muted"
            >
              {{ $t('hr.records.no_salary') }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <VCard>
      <VCardItem>
        <VCardTitle>{{ $t('hr.employees.insurance') }}</VCardTitle>
        <template #append>
          <VBtn
            v-if="canEdit"
            size="small"
            prepend-icon="tabler-plus"
            @click="insurances.openDialog"
          >
            {{ $t('hr.records.add_insurance_change') }}
          </VBtn>
        </template>
      </VCardItem>
      <VCardText>
        <HrInfoGrid :items="currentInsuranceItems" />
      </VCardText>
      <VDivider />
      <VTable
        density="compact"
        class="text-no-wrap"
      >
        <thead>
          <tr>
            <th>{{ $t('hr.fields.effective_from') }}</th>
            <th>{{ $t('hr.fields.effective_to') }}</th>
            <th>{{ $t('hr.fields.insurance_category') }}</th>
            <th>{{ $t('hr.fields.employee_rate') }}</th>
            <th>{{ $t('hr.fields.employer_rate') }}</th>
            <th>{{ $t('hr.fields.contribution_start_date') }}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in insurances.rows.value"
            :key="row.id"
          >
            <td dir="ltr">
              {{ row.effective_from }}
            </td>
            <td dir="ltr">
              {{ row.effective_to || '—' }}
            </td>
            <td>{{ row.category_label }}</td>
            <td dir="ltr">
              {{ row.employee_rate }}%
            </td>
            <td dir="ltr">
              {{ row.employer_rate }}%
            </td>
            <td dir="ltr">
              {{ row.contribution_start_date || '—' }}
            </td>
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
                  v-if="canEdit && row.is_scheduled && row === insurances.rows.value[0]"
                  icon
                  size="x-small"
                  variant="text"
                  color="error"
                  @click="insurances.askCancel(row)"
                >
                  <VIcon icon="tabler-calendar-x" />
                  <VTooltip activator="parent">
                    {{ $t('hr.records.cancel_scheduled') }}
                  </VTooltip>
                </VBtn>
              </div>
            </td>
          </tr>
          <tr v-if="!insurances.rows.value.length && !insurances.loading.value">
            <td
              colspan="7"
              class="text-center hr-page__muted"
            >
              {{ $t('hr.records.no_insurance') }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <VDialog
      v-model="salaries.isDialogVisible.value"
      max-width="680"
    >
      <VCard :title="$t('hr.records.add_salary_change')">
        <VCardText>
          <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ $t('hr.records.effective_dated_hint') }}
          </VAlert>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppDateTimePicker
                v-model="salaryForm.effective_from"
                :label="`${$t('hr.fields.effective_from')} *`"
                :error-messages="salaries.errorsOf('effective_from')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="salaryForm.basic_salary"
                type="number"
                min="0"
                step="0.01"
                :label="`${$t('hr.fields.basic_salary')} *`"
                dir="ltr"
                :error-messages="salaries.errorsOf('basic_salary')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="salaryForm.housing_allowance"
                type="number"
                min="0"
                step="0.01"
                :label="$t('hr.fields.housing_allowance')"
                dir="ltr"
                :error-messages="salaries.errorsOf('housing_allowance')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="salaryForm.transportation_allowance"
                type="number"
                min="0"
                step="0.01"
                :label="$t('hr.fields.transportation_allowance')"
                dir="ltr"
                :error-messages="salaries.errorsOf('transportation_allowance')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="salaryForm.other_allowances"
                type="number"
                min="0"
                step="0.01"
                :label="$t('hr.fields.other_allowances')"
                dir="ltr"
                :error-messages="salaries.errorsOf('other_allowances')"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="salaryForm.other_allowances_note"
                :label="$t('hr.fields.other_allowances_note')"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="salaryForm.reason"
                :label="`${$t('hr.fields.change_reason')} *`"
                :error-messages="salaries.errorsOf('reason')"
              />
            </VCol>
            <VCol cols="12">
              <div class="text-body-1">
                {{ $t('hr.fields.gross_salary') }}:
                <strong>{{ money(previewGross) }}</strong>
              </div>
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="salaries.isDialogVisible.value = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            :loading="salaries.saving.value"
            @click="salaries.save"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="insurances.isDialogVisible.value"
      max-width="680"
    >
      <VCard :title="$t('hr.records.add_insurance_change')">
        <VCardText>
          <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            {{ $t('hr.records.insurance_rates_hint') }}
          </VAlert>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppDateTimePicker
                v-model="insurances.form.value.effective_from"
                :label="`${$t('hr.fields.effective_from')} *`"
                :error-messages="insurances.errorsOf('effective_from')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="insurances.form.value.category"
                :items="lookups.insurance_categories"
                :label="`${$t('hr.fields.insurance_category')} *`"
                :error-messages="insurances.errorsOf('category')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="insurances.form.value.employee_rate"
                type="number"
                min="0"
                max="100"
                step="0.01"
                suffix="%"
                :label="`${$t('hr.fields.employee_rate')} *`"
                dir="ltr"
                :error-messages="insurances.errorsOf('employee_rate')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="insurances.form.value.employer_rate"
                type="number"
                min="0"
                max="100"
                step="0.01"
                suffix="%"
                :label="`${$t('hr.fields.employer_rate')} *`"
                dir="ltr"
                :error-messages="insurances.errorsOf('employer_rate')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppDateTimePicker
                v-model="insurances.form.value.contribution_start_date"
                :label="$t('hr.fields.contribution_start_date')"
                :error-messages="insurances.errorsOf('contribution_start_date')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="insurances.form.value.subscription_number"
                :label="$t('hr.fields.subscription_number')"
                dir="ltr"
                :error-messages="insurances.errorsOf('subscription_number')"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="insurances.form.value.reason"
                :label="$t('hr.fields.change_reason')"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="insurances.isDialogVisible.value = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            :loading="insurances.saving.value"
            @click="insurances.save"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <HrConfirmDialog
      :model-value="!!salaries.cancelTarget.value"
      :message="$t('hr.records.confirm_cancel')"
      :confirm-label="$t('hr.records.cancel_scheduled')"
      @update:model-value="salaries.cancelTarget.value = null"
      @confirm="salaries.confirmCancel"
    />
    <HrConfirmDialog
      :model-value="!!insurances.cancelTarget.value"
      :message="$t('hr.records.confirm_cancel')"
      :confirm-label="$t('hr.records.cancel_scheduled')"
      @update:model-value="insurances.cancelTarget.value = null"
      @confirm="insurances.confirmCancel"
    />
  </div>
</template>
