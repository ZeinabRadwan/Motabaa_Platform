<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'
import { EMPLOYMENT_STATUS_COLORS } from '@/utils/hrFormat'
import HrEmploymentLifecycleDialog from '@/views/hr/employees/HrEmploymentLifecycleDialog.vue'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

const { t } = useI18n()
const route = useRoute()
const { lookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-employees', 'edit_hr-employees'))
const canAdmin = computed(() => can('admin_hr-employees', 'admin_hr-employees'))

const queryNumber = key => (route.query[key] ? Number(route.query[key]) : null)

const items = ref([])
const total = ref(0)
const loading = ref(false)
const search = ref('')
const branchId = ref(queryNumber('branch_id'))
const departmentId = ref(queryNumber('department_id'))
const employmentStatus = ref(route.query.employment_status || null)
const jobTitle = ref(route.query.job_title || null)
const attendanceMode = ref(route.query.attendance_mode || null)
const workLocationId = ref(queryNumber('work_location_id'))
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })

const fetchEmployees = () => {
  loading.value = true

  return hrEmployeesApi.list({
    q: search.value || undefined,
    branch_id: branchId.value || undefined,
    department_id: departmentId.value || undefined,
    employment_status: employmentStatus.value || undefined,
    job_title: jobTitle.value || undefined,
    attendance_mode: attendanceMode.value || undefined,
    work_location_id: workLocationId.value || undefined,
    options: options.value,
  }).then(response => {
    items.value = response.data.data
    total.value = response.data.total
  }).catch(() => {
    items.value = []
    total.value = 0
  }).finally(() => {
    loading.value = false
  })
}

watchServerTableFetch(fetchEmployees, {
  search,
  filters: () => [branchId.value, departmentId.value, employmentStatus.value, jobTitle.value, attendanceMode.value, workLocationId.value],
  options,
})

const workLocationOptions = computed(() => lookups.value.work_locations.filter(option => !branchId.value || option.branch_id === branchId.value))

watch(branchId, () => {
  if (workLocationId.value && !workLocationOptions.value.some(option => option.value === workLocationId.value))
    workLocationId.value = null
})

const headers = computed(() => [
  { title: t('hr.fields.employee_number'), key: 'employee_number' },
  { title: t('hr.fields.full_name'), key: 'name' },
  { title: t('hr.fields.id_number'), key: 'id_or_residence_number' },
  { title: t('hr.fields.job_title'), key: 'job_title' },
  { title: t('hr.fields.department'), key: 'department_name', sortable: false },
  { title: t('hr.fields.branch'), key: 'branch_name', sortable: false },
  { title: t('hr.fields.hire_date'), key: 'hire_date' },
  { title: t('hr.fields.employment_start_date'), key: 'employment_start_date' },
  { title: t('hr.fields.attendance_mode'), key: 'attendance_mode' },
  { title: t('hr.fields.employment_status'), key: 'employment_status' },
  { title: t('Actions'), key: 'actions', sortable: false },
])

const initials = name => String(name || '?').trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('')

const lifecycle = ref({ visible: false, employee: null })

const openLifecycle = employee => {
  lifecycle.value = { visible: true, employee }
}
</script>

<template>
  <VCard>
    <VCardText>
      <VRow>
        <VCol
          cols="12"
          md="4"
        >
          <AppTextField
            v-model="search"
            :placeholder="$t('hr.employees.search_placeholder')"
            prepend-inner-icon="tabler-search"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="2"
        >
          <AppSelect
            v-model="branchId"
            :items="lookups.branches"
            :placeholder="$t('hr.fields.branch')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="2"
        >
          <AppSelect
            v-model="departmentId"
            :items="lookups.departments"
            :placeholder="$t('hr.fields.department')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          v-if="canEdit"
          cols="12"
          md="4"
          class="d-flex align-center justify-end"
        >
          <VBtn
            prepend-icon="tabler-user-plus"
            :to="{ name: 'hr-employees-add' }"
          >
            {{ $t('hr.employees.add') }}
          </VBtn>
        </VCol>
        <VCol
          cols="12"
          sm="4"
          md="2"
        >
          <AppSelect
            v-model="employmentStatus"
            :items="lookups.employment_statuses"
            :placeholder="$t('hr.fields.employment_status')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
          md="2"
        >
          <AppSelect
            v-model="jobTitle"
            :items="lookups.job_titles"
            :placeholder="$t('hr.fields.job_title')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
          md="2"
        >
          <AppSelect
            v-model="attendanceMode"
            :items="lookups.attendance_modes"
            :placeholder="$t('hr.fields.attendance_mode')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
          md="2"
        >
          <AppSelect
            v-model="workLocationId"
            :items="workLocationOptions"
            :placeholder="$t('hr.fields.work_location')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VDataTableServer
      v-model:items-per-page="options.itemsPerPage"
      v-model:page="options.page"
      :items="items"
      :items-length="total"
      :headers="headers"
      :loading="loading"
      class="text-no-wrap"
      @update:options="applyServerTableOptions(options, $event)"
    >
      <template #item.employee_number="{ item }">
        <span dir="ltr">{{ item.raw.employee_number }}</span>
      </template>
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-3">
          <VAvatar
            size="34"
            color="primary"
            variant="tonal"
          >
            <VImg
              v-if="item.raw.photo_url"
              :src="item.raw.photo_url"
              cover
            />
            <span
              v-else
              class="text-sm"
            >{{ initials(item.raw.name) }}</span>
          </VAvatar>
          <RouterLink
            :to="{ name: 'hr-employees-view-id', params: { id: item.raw.id } }"
            class="font-weight-medium"
          >
            {{ item.raw.name }}
          </RouterLink>
        </div>
      </template>
      <template #item.id_or_residence_number="{ item }">
        <span dir="ltr">{{ item.raw.id_or_residence_number || '—' }}</span>
      </template>
      <template #item.job_title="{ item }">
        {{ item.raw.job_title || '—' }}
      </template>
      <template #item.department_name="{ item }">
        {{ item.raw.department_name || '—' }}
      </template>
      <template #item.branch_name="{ item }">
        {{ item.raw.branch_name || '—' }}
      </template>
      <template #item.hire_date="{ item }">
        <span dir="ltr">{{ item.raw.hire_date || '—' }}</span>
      </template>
      <template #item.employment_start_date="{ item }">
        <span dir="ltr">{{ item.raw.employment_start_date || '—' }}</span>
      </template>
      <template #item.attendance_mode="{ item }">
        {{ item.raw.attendance_mode_label }}
      </template>
      <template #item.employment_status="{ item }">
        <VChip
          size="small"
          label
          :color="EMPLOYMENT_STATUS_COLORS[item.raw.employment_status] || 'secondary'"
        >
          {{ item.raw.employment_status_label }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <VBtn
            icon
            variant="text"
            size="small"
            :to="{ name: 'hr-employees-view-id', params: { id: item.raw.id } }"
          >
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent">
              {{ $t('hr.employees.view') }}
            </VTooltip>
          </VBtn>
          <VBtn
            v-if="canEdit"
            icon
            variant="text"
            size="small"
            :to="{ name: 'hr-employees-edit-id', params: { id: item.raw.id } }"
          >
            <VIcon icon="tabler-edit" />
            <VTooltip activator="parent">
              {{ $t('hr.employees.edit') }}
            </VTooltip>
          </VBtn>
          <VBtn
            v-if="canAdmin"
            icon
            variant="text"
            size="small"
            :color="item.raw.is_employed ? 'error' : 'success'"
            @click="openLifecycle(item.raw)"
          >
            <VIcon :icon="item.raw.is_employed ? 'tabler-user-off' : 'tabler-user-check'" />
            <VTooltip activator="parent">
              {{ $t(item.raw.is_employed ? 'hr.employees.deactivate' : 'hr.employees.activate') }}
            </VTooltip>
          </VBtn>
        </div>
      </template>
      <template #no-data>
        <div class="pa-6 text-center hr-page__muted">
          {{ $t('hr.employees.empty') }}
        </div>
      </template>
    </VDataTableServer>

    <HrEmploymentLifecycleDialog
      v-model="lifecycle.visible"
      :employee="lifecycle.employee"
      @saved="fetchEmployees"
    />
  </VCard>
</template>
