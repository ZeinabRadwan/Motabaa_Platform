<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import i18n from '@/plugins/i18n'
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'
import { normalizeSaudiId, saudiIdError } from '@/utils/saudiId'
import cuntries from '@core/utils/cuntries'
import { contractTypeItems, genderTypes } from '@core/utils/generalItems'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const props = defineProps({
  // Null when adding an employee.
  employeeId: { type: [Number, String], default: null },
})

const { t } = useI18n()
const router = useRouter()
const { lookups } = useHrLookups()

const isEdit = computed(() => !!props.employeeId)

const PERSONAL_FIELDS = [
  'name',
  'email',
  'phone',
  'nationality',
  'gender',
  'birthdate',
  'id_or_residence_number',
  'id_expiry_date',
  'address_city',
  'address_area',
  'address_street',
  'address_building',
  'address_number',
  'address_unit',
  'address_zipcode',
  'job_title',
  'hire_date',
  'contract_type',
  'contract_end_date',
]

const emptyForm = () => ({
  mode: 'existing',
  user_id: null,
  role_id: null,
  ...Object.fromEntries(PERSONAL_FIELDS.map(field => [field, null])),
  employee_number: '',
  branch_id: null,
  department_id: null,
  manager_id: null,
  work_location_id: null,
  employment_start_date: null,
  contract_start_date: null,
  employment_status: 'active',
  notes: '',
  attendance_mode: 'manual',
  gps_policy: 'policy',
  grace_minutes: null,
  attendance_policy_id: null,
  shift_id: null,
  effective_date: null,
  change_reason: '',
})

const form = ref(emptyForm())
const loaded = ref(!isEdit.value)
const saving = ref(false)
const fieldErrors = ref({})
const employee = ref(null)

const errorsOf = field => fieldErrors.value[field] ?? []

const nationalityItems = computed(() => cuntries.map(country => ({
  value: country.country_code,
  title: i18n.global.locale.value === 'ar' ? country.country_arNationality : country.country_enNationality,
})))

const statusItems = computed(() => {
  const allowed = isEdit.value ? ['active', 'probation', 'suspended'] : ['active', 'probation']

  return lookups.value.employment_statuses.filter(option => allowed.includes(option.value))
})

const workLocationOptions = computed(() => lookups.value.work_locations.filter(option => !form.value.branch_id || option.branch_id === form.value.branch_id))
const shiftOptions = computed(() => lookups.value.shifts.filter(option => !option.branch_id || !form.value.branch_id || option.branch_id === form.value.branch_id))
const jobTitleItems = computed(() => lookups.value.job_titles.map(option => option.value))

watch(() => form.value.branch_id, () => {
  if (form.value.work_location_id && !workLocationOptions.value.some(option => option.value === form.value.work_location_id))
    form.value.work_location_id = null
})

const idNumberError = computed(() => {
  const key = saudiIdError(form.value.id_or_residence_number, form.value.nationality)

  return key ? [t(key)] : errorsOf('id_or_residence_number')
})

const idNumberHint = computed(() => {
  if (!form.value.nationality)
    return t('hr.employees.id_hint')

  return t(form.value.nationality === 'SA' ? 'hr.employees.id_hint_saudi' : 'hr.employees.id_hint_resident')
})

// Staff candidates (mode "existing"): remembered by id so choosing one prefills the personal fields.
const candidateCache = new Map()

const searchCandidates = params => hrEmployeesApi.candidates(params).then(response => {
  (response.data?.data ?? []).forEach(candidate => candidateCache.set(candidate.id, candidate))

  return response
})

watch(() => form.value.user_id, userId => {
  const candidate = candidateCache.get(userId)
  if (!candidate)
    return
  PERSONAL_FIELDS.forEach(field => {
    if (candidate[field] !== null && candidate[field] !== undefined && candidate[field] !== '')
      form.value[field] = field === 'gender' ? String(candidate[field]) : candidate[field]
  })
})

const searchManagers = params => hrEmployeesApi.list({
  q: params.q || undefined,
  ids: params.ids,
  employed_only: params.ids?.length ? undefined : 1,
  options: { page: 1, itemsPerPage: 20 },
}).then(response => ({
  data: { data: response.data.data.filter(row => String(row.id) !== String(props.employeeId)) },
}))

const toForm = data => ({
  ...emptyForm(),
  ...Object.fromEntries(PERSONAL_FIELDS.map(field => [field, data[field] ?? null])),
  gender: data.gender ? String(data.gender) : null,
  address_city: data.address?.city ?? null,
  address_area: data.address?.area ?? null,
  address_street: data.address?.street ?? null,
  address_building: data.address?.building ?? null,
  address_number: data.address?.number ?? null,
  address_unit: data.address?.unit ?? null,
  address_zipcode: data.address?.zipcode ?? null,
  employee_number: data.employee_number,
  branch_id: data.branch_id,
  department_id: data.department_id,
  manager_id: data.manager_id,
  work_location_id: data.work_location_id,
  employment_start_date: data.employment_start_date,
  contract_start_date: data.contract_start_date,
  employment_status: data.is_employed ? data.employment_status : null,
  notes: data.notes ?? '',
})

const load = () => {
  if (!isEdit.value)
    return
  loaded.value = false
  hrEmployeesApi.show(props.employeeId).then(response => {
    employee.value = response.data.data
    form.value = toForm(employee.value)
    loaded.value = true
  }).catch(() => {
    router.replace({ name: 'hr-employees-list' })
  })
}

onMounted(load)

const ATTENDANCE_FIELDS = ['attendance_mode', 'gps_policy', 'grace_minutes', 'attendance_policy_id', 'shift_id']
const ATTENDANCE_CONFIG_FIELDS = ['attendance_mode', 'gps_policy', 'grace_minutes', 'attendance_policy_id']

// Without it, new employees start with manual attendance and no policy; the server rejects anything else.
const canConfigureAttendance = computed(() => can('edit_hr-attendance-config', 'edit_hr-attendance-config'))

const buildPayload = () => {
  const payload = { ...form.value, id_or_residence_number: normalizeSaudiId(form.value.id_or_residence_number) }

  if (!payload.employee_number)
    delete payload.employee_number

  if (isEdit.value) {
    ['mode', 'user_id', 'role_id', ...ATTENDANCE_FIELDS].forEach(field => delete payload[field])
    if (!payload.effective_date)
      delete payload.effective_date
    if (!employee.value?.is_employed)
      delete payload.employment_status
  }
  else {
    ['effective_date', 'change_reason'].forEach(field => delete payload[field])
    if (payload.mode === 'new')
      delete payload.user_id
    else
      delete payload.role_id
    if (!canConfigureAttendance.value)
      ATTENDANCE_CONFIG_FIELDS.forEach(field => delete payload[field])
    else if (payload.attendance_mode !== 'electronic')
      payload.gps_policy = null
  }

  return payload
}

const save = () => {
  saving.value = true
  fieldErrors.value = {}

  const payload = buildPayload()

  const request = isEdit.value
    ? hrEmployeesApi.update(props.employeeId, payload)
    : hrEmployeesApi.create(payload)

  request.then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    router.push({ name: 'hr-employees-view-id', params: { id: response.data.data.id } })
  }).catch(error => {
    fieldErrors.value = error?.response?.data?.errors ?? {}
  }).finally(() => {
    saving.value = false
  })
}

const cancel = () => {
  if (isEdit.value)
    router.push({ name: 'hr-employees-view-id', params: { id: props.employeeId } })
  else
    router.push({ name: 'hr-employees-list' })
}
</script>

<template>
  <div
    v-if="loaded"
    class="hr-employee-form"
  >
    <VCard
      v-if="!isEdit"
      class="mb-4"
      :title="$t('hr.employees.sections.account')"
    >
      <VCardText>
        <VRadioGroup
          v-model="form.mode"
          inline
          class="mb-4"
        >
          <VRadio
            value="existing"
            :label="$t('hr.employees.mode_existing')"
          />
          <VRadio
            value="new"
            :label="$t('hr.employees.mode_new')"
          />
        </VRadioGroup>
        <VRow>
          <VCol
            v-if="form.mode === 'existing'"
            cols="12"
            md="6"
          >
            <AppAutocomplete
              v-model="form.user_id"
              :server-search="searchCandidates"
              preload
              item-title="name"
              item-value="id"
              :label="$t('hr.employees.staff_member')"
              :hint="$t('hr.employees.staff_member_hint')"
              persistent-hint
              :error-messages="errorsOf('user_id')"
            />
          </VCol>
          <VCol
            v-else
            cols="12"
            md="6"
          >
            <AppSelect
              v-model="form.role_id"
              :items="lookups.staff_roles"
              :label="$t('hr.fields.role')"
              :hint="$t('hr.employees.new_user_hint')"
              persistent-hint
              :error-messages="errorsOf('role_id')"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard
      class="mb-4"
      :title="$t('hr.employees.sections.personal')"
    >
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.name"
              :label="`${$t('hr.fields.full_name')} *`"
              :error-messages="errorsOf('name')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppSelect
              v-model="form.nationality"
              :items="nationalityItems"
              :label="`${$t('hr.fields.nationality')} *`"
              :error-messages="errorsOf('nationality')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppSelect
              v-model="form.gender"
              :items="genderTypes()"
              :label="`${$t('hr.fields.gender')} *`"
              :error-messages="errorsOf('gender')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              v-model="form.id_or_residence_number"
              :label="`${$t('hr.fields.id_number')} *`"
              :hint="idNumberHint"
              persistent-hint
              maxlength="10"
              dir="ltr"
              :error-messages="idNumberError"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppDateTimePicker
              v-model="form.id_expiry_date"
              :label="$t('hr.fields.id_expiry_date')"
              :error-messages="errorsOf('id_expiry_date')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppDateTimePicker
              v-model="form.birthdate"
              :label="`${$t('hr.fields.birthdate')} *`"
              :error-messages="errorsOf('birthdate')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              v-model="form.phone"
              :label="`${$t('hr.fields.phone')} *`"
              dir="ltr"
              :error-messages="errorsOf('phone')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              v-model="form.email"
              type="email"
              :label="$t('hr.fields.email')"
              dir="ltr"
              :error-messages="errorsOf('email')"
            />
          </VCol>
        </VRow>
        <h4 class="hr-page__section-title mt-6">
          {{ $t('hr.fields.address') }}
        </h4>
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppTextField
              v-model="form.address_city"
              :label="$t('hr.fields.city')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="3"
          >
            <AppTextField
              v-model="form.address_area"
              :label="$t('hr.fields.district')"
            />
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <AppTextField
              v-model="form.address_street"
              :label="$t('hr.fields.street')"
            />
          </VCol>
          <VCol
            cols="6"
            md="3"
          >
            <AppTextField
              v-model="form.address_building"
              :label="$t('hr.fields.building_number')"
              dir="ltr"
            />
          </VCol>
          <VCol
            cols="6"
            md="3"
          >
            <AppTextField
              v-model="form.address_number"
              :label="$t('hr.fields.additional_number')"
              dir="ltr"
            />
          </VCol>
          <VCol
            cols="6"
            md="3"
          >
            <AppTextField
              v-model="form.address_unit"
              :label="$t('hr.fields.unit')"
              dir="ltr"
            />
          </VCol>
          <VCol
            cols="6"
            md="3"
          >
            <AppTextField
              v-model="form.address_zipcode"
              :label="$t('hr.fields.postal_code')"
              dir="ltr"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard
      class="mb-4"
      :title="$t('hr.employees.sections.employment')"
    >
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              v-model="form.employee_number"
              :label="$t('hr.fields.employee_number')"
              :hint="isEdit ? '' : $t('hr.employees.number_auto_hint')"
              persistent-hint
              dir="ltr"
              :error-messages="errorsOf('employee_number')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppCombobox
              v-model="form.job_title"
              :items="jobTitleItems"
              :label="`${$t('hr.fields.job_title')} *`"
              :error-messages="errorsOf('job_title')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="form.employment_status"
              :items="statusItems"
              :label="$t('hr.fields.employment_status')"
              :disabled="isEdit && !employee?.is_employed"
              :hint="isEdit && !employee?.is_employed ? $t('hr.employees.status_inactive_hint') : ''"
              persistent-hint
              :error-messages="errorsOf('employment_status')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="form.branch_id"
              :items="lookups.branches"
              :label="`${$t('hr.fields.branch')} *`"
              :error-messages="errorsOf('branch_id')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="form.department_id"
              :items="lookups.departments"
              :label="`${$t('hr.fields.department')} *`"
              :error-messages="errorsOf('department_id')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppAutocomplete
              v-model="form.manager_id"
              :server-search="searchManagers"
              preload
              item-title="name"
              item-value="id"
              :label="$t('hr.fields.manager')"
              clearable
              clear-icon="tabler-x"
              :error-messages="errorsOf('manager_id')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppDateTimePicker
              v-model="form.hire_date"
              :label="`${$t('hr.fields.hire_date')} *`"
              :error-messages="errorsOf('hire_date')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppDateTimePicker
              v-model="form.employment_start_date"
              :label="`${$t('hr.fields.employment_start_date')} *`"
              :error-messages="errorsOf('employment_start_date')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="form.contract_type"
              :items="contractTypeItems()"
              :label="$t('hr.fields.contract_type')"
              clearable
              clear-icon="tabler-x"
              :error-messages="errorsOf('contract_type')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppDateTimePicker
              v-model="form.contract_start_date"
              :label="$t('hr.fields.contract_start_date')"
              :error-messages="errorsOf('contract_start_date')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppDateTimePicker
              v-model="form.contract_end_date"
              :label="form.contract_type === 'temporary' ? `${$t('hr.fields.contract_end_date')} *` : $t('hr.fields.contract_end_date')"
              :error-messages="errorsOf('contract_end_date')"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.notes"
              :label="$t('Notes')"
              rows="2"
              auto-grow
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard
      v-if="!isEdit"
      class="mb-4"
      :title="$t('hr.employees.sections.attendance')"
    >
      <VCardText>
        <VAlert
          v-if="!canConfigureAttendance"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ $t('hr.attendance_config.create_without_permission') }}
        </VAlert>
        <VRow>
          <VCol
            v-if="canConfigureAttendance"
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="form.attendance_mode"
              :items="lookups.attendance_modes"
              :label="`${$t('hr.fields.attendance_mode')} *`"
              :error-messages="errorsOf('attendance_mode')"
            />
          </VCol>
          <VCol
            v-if="canConfigureAttendance && form.attendance_mode === 'electronic'"
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="form.gps_policy"
              :items="lookups.gps_policies"
              :label="$t('hr.attendance_config.gps_verification')"
              :error-messages="errorsOf('gps_policy')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="form.work_location_id"
              :items="workLocationOptions"
              :label="$t('hr.fields.work_location')"
              :disabled="!form.branch_id"
              clearable
              clear-icon="tabler-x"
              :error-messages="errorsOf('work_location_id')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="form.shift_id"
              :items="shiftOptions"
              :label="$t('hr.fields.shift')"
              clearable
              clear-icon="tabler-x"
              :error-messages="errorsOf('shift_id')"
            />
          </VCol>
          <VCol
            v-if="canConfigureAttendance"
            cols="12"
            sm="6"
            md="4"
          >
            <AppSelect
              v-model="form.attendance_policy_id"
              :items="lookups.attendance_policies"
              :label="$t('hr.fields.attendance_policy')"
              clearable
              clear-icon="tabler-x"
              :error-messages="errorsOf('attendance_policy_id')"
            />
          </VCol>
          <VCol
            v-if="canConfigureAttendance"
            cols="12"
            sm="6"
            md="4"
          >
            <AppTextField
              v-model.number="form.grace_minutes"
              type="number"
              min="0"
              max="240"
              :label="$t('hr.fields.grace_minutes')"
              :hint="$t('hr.employees.grace_hint')"
              persistent-hint
              :error-messages="errorsOf('grace_minutes')"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard
      v-if="isEdit"
      class="mb-4"
      :title="$t('hr.employees.sections.change')"
    >
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          {{ $t('hr.employees.change_hint') }}
        </VAlert>
        <VRow>
          <VCol
            cols="12"
            sm="4"
          >
            <AppDateTimePicker
              v-model="form.effective_date"
              :label="$t('hr.fields.effective_date')"
              :placeholder="$t('hr.employees.today')"
              :error-messages="errorsOf('effective_date')"
            />
          </VCol>
          <VCol
            cols="12"
            sm="8"
          >
            <AppTextField
              v-model="form.change_reason"
              :label="$t('hr.fields.change_reason')"
              :error-messages="errorsOf('change_reason')"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <div class="d-flex justify-end gap-3">
      <VBtn
        variant="tonal"
        color="secondary"
        @click="cancel"
      >
        {{ $t('Cancel') }}
      </VBtn>
      <VBtn
        :loading="saving"
        prepend-icon="tabler-device-floppy"
        @click="save"
      >
        {{ $t('Save') }}
      </VBtn>
    </div>
  </div>
  <div
    v-else
    class="d-flex justify-center pa-10"
  >
    <VProgressCircular indeterminate />
  </div>
</template>
