<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { useHrCrudTable } from '@/composables/useHrCrudTable'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrBranchesApi, hrEmployeesApi } from '@/plugins/apis/hrRequest'
import cuntries from '@core/utils/cuntries'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

const { t, locale } = useI18n()
const { lookups, refresh: refreshLookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-organization', 'edit_hr-organization'))
const canAdmin = computed(() => can('admin_hr-organization', 'admin_hr-organization'))

// Without it the user only sees the branches they manage and cannot create branches or assign managers.
const allBranches = computed(() => can('access_hr-all-branches', 'access_hr-all-branches'))

const statusFilter = ref(null)
const cityFilter = ref(null)
const managerFilter = ref(null)
const cities = ref([])

const countryTitleKey = computed(() => (locale.value === 'ar' ? 'country_arName' : 'country_enName'))
const countryName = code => cuntries.find(country => country.country_code === code)?.[countryTitleKey.value] ?? code

const statusItems = computed(() => [
  { value: 1, title: t('Active') },
  { value: 0, title: t('Inactive') },
])

const managerItems = computed(() => [
  { value: 1, title: t('hr.organization.with_manager') },
  { value: 0, title: t('hr.organization.without_manager') },
])

const emptyForm = () => ({
  code: '',
  name_ar: '',
  name_en: '',
  country: 'SA',
  city: '',
  address: '',
  phone: '',
  email: '',
  manager_employee_id: null,
  default_shift_id: null,
  cr_number: '',
  mol_establishment_number: '',
  gosi_registration_number: '',
  is_headquarters: false,
  is_active: true,
})

const loadCities = () => hrBranchesApi.cities().then(response => {
  cities.value = response.data.data ?? []
}).catch(() => {})

const {
  items, total, loading, search, showTrashed, options, updateOptions, fetchItems,
  isDialogVisible, editingId, form, saving, openDialog, openCreateDialog, save,
  confirm, askConfirm, runConfirmed,
} = useHrCrudTable(hrBranchesApi, {
  filters: () => ({
    is_active: statusFilter.value ?? undefined,
    city: cityFilter.value || undefined,
    has_manager: managerFilter.value ?? undefined,
  }),
  emptyForm,
  toForm: item => Object.fromEntries(Object.keys(emptyForm()).map(key => [key, item[key] ?? emptyForm()[key]])),
  toPayload: payload => {
    const data = { ...payload }

    // Status changes after creation go through activate / deactivate.
    if (editingId.value)
      delete data.is_active
    if (!allBranches.value) {
      delete data.manager_employee_id
      delete data.is_headquarters
    }

    return data
  },
  onSaved: () => {
    refreshLookups().catch(() => {})
    loadCities()
  },
})

onMounted(loadCities)

// Rotating shifts need a per-employee cycle start, so they cannot be a branch default for new hires.
const defaultShiftItems = computed(() => lookups.value.shifts.filter(option => !option.is_rotating
  && (!option.branch_id || option.branch_id === editingId.value)))

const searchManagers = params => hrEmployeesApi.list({
  q: params.q || undefined,
  ids: params.ids,
  employed_only: params.ids?.length ? undefined : 1,
  options: { page: 1, itemsPerPage: 20 },
})

// Activate / deactivate
const statusTarget = ref(null)

const changeStatus = () => {
  const branch = statusTarget.value

  statusTarget.value = null
  if (!branch)
    return

  const request = branch.is_active ? hrBranchesApi.deactivate(branch.id) : hrBranchesApi.activate(branch.id)

  request.then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    afterStatusChange()
  }).catch(() => {})
}

const afterStatusChange = () => {
  refreshLookups().catch(() => {})
  fetchItems()
}

const headers = computed(() => [
  { title: t('hr.fields.code'), key: 'code' },
  { title: t('hr.fields.name'), key: 'name' },
  { title: t('hr.fields.city'), key: 'city' },
  { title: t('hr.organization.branch_manager'), key: 'manager', sortable: false },
  { title: t('hr.organization.work_locations'), key: 'work_locations_count' },
  { title: t('hr.employees.title'), key: 'employees_count' },
  { title: t('Status'), key: 'is_active', sortable: false },
  { title: t('Actions'), key: 'actions', sortable: false },
])

const branchRoute = item => ({ name: 'hr-organization-branches-id', params: { id: item.id } })
</script>

<template>
  <div>
    <VCardText>
      <VRow>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <AppTextField
            v-model="search"
            :placeholder="$t('Search')"
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
            v-model="statusFilter"
            :items="statusItems"
            :placeholder="$t('Status')"
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
            v-model="cityFilter"
            :items="cities"
            :placeholder="$t('hr.fields.city')"
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
            v-model="managerFilter"
            :items="managerItems"
            :placeholder="$t('hr.organization.branch_manager')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
          class="d-flex align-center justify-end gap-4"
        >
          <VSwitch
            v-if="canAdmin && allBranches"
            v-model="showTrashed"
            :label="$t('hr.common.show_deleted')"
            hide-details
          />
          <VBtn
            v-if="canEdit && allBranches"
            prepend-icon="tabler-plus"
            @click="openCreateDialog"
          >
            {{ $t('hr.organization.add_branch') }}
          </VBtn>
        </VCol>
      </VRow>
      <VAlert
        v-if="!allBranches"
        type="info"
        variant="tonal"
        density="compact"
        class="mt-4"
      >
        {{ $t('hr.organization.scoped_branches_hint') }}
      </VAlert>
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
      @update:options="updateOptions"
    >
      <template #item.name="{ item }">
        <RouterLink
          v-if="!item.raw.deleted_at"
          :to="branchRoute(item.raw)"
          class="font-weight-medium"
        >
          {{ item.raw.name }}
        </RouterLink>
        <span v-else>{{ item.raw.name }}</span>
        <VChip
          v-if="item.raw.is_headquarters"
          size="x-small"
          color="primary"
          label
          class="ms-2"
        >
          {{ $t('hr.fields.is_headquarters') }}
        </VChip>
      </template>
      <template #item.city="{ item }">
        <div>{{ item.raw.city || '—' }}</div>
        <div class="text-caption text-medium-emphasis">
          {{ countryName(item.raw.country) }}
        </div>
      </template>
      <template #item.manager="{ item }">
        <span v-if="item.raw.manager">{{ item.raw.manager.name }}</span>
        <span
          v-else
          class="text-medium-emphasis"
        >{{ $t('hr.organization.no_manager') }}</span>
      </template>
      <template #item.is_active="{ item }">
        <VChip
          size="small"
          label
          :color="item.raw.is_active ? 'success' : 'secondary'"
        >
          {{ $t(item.raw.is_active ? 'Active' : 'Inactive') }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <template v-if="item.raw.deleted_at">
            <VBtn
              v-if="canAdmin && allBranches"
              icon
              variant="text"
              size="small"
              @click="askConfirm('restore', item.raw)"
            >
              <VIcon icon="tabler-arrow-back-up" />
            </VBtn>
          </template>
          <template v-else>
            <VBtn
              icon
              variant="text"
              size="small"
              :to="branchRoute(item.raw)"
            >
              <VIcon icon="tabler-eye" />
              <VTooltip activator="parent">
                {{ $t('hr.organization.view_branch') }}
              </VTooltip>
            </VBtn>
            <VBtn
              v-if="canEdit"
              icon
              variant="text"
              size="small"
              @click="openDialog(item.raw)"
            >
              <VIcon icon="tabler-edit" />
            </VBtn>
            <VBtn
              v-if="canAdmin && allBranches"
              icon
              variant="text"
              size="small"
              :color="item.raw.is_active ? 'warning' : 'success'"
              @click="statusTarget = item.raw"
            >
              <VIcon :icon="item.raw.is_active ? 'tabler-player-pause' : 'tabler-player-play'" />
              <VTooltip activator="parent">
                {{ $t(item.raw.is_active ? 'hr.organization.deactivate' : 'hr.organization.activate') }}
              </VTooltip>
            </VBtn>
            <VBtn
              v-if="canAdmin && allBranches"
              icon
              variant="text"
              size="small"
              color="error"
              @click="askConfirm('delete', item.raw)"
            >
              <VIcon icon="tabler-trash" />
            </VBtn>
          </template>
        </div>
      </template>
    </VDataTableServer>

    <VDialog
      v-model="isDialogVisible"
      max-width="820"
    >
      <VCard :title="$t(editingId ? 'hr.organization.edit_branch' : 'hr.organization.add_branch')">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.code"
                :label="`${$t('hr.fields.code')} *`"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.name_ar"
                :label="`${$t('hr.fields.name_ar')} *`"
                dir="rtl"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.name_en"
                :label="$t('hr.fields.name_en')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppAutocomplete
                v-model="form.country"
                :items="cuntries"
                :item-title="countryTitleKey"
                item-value="country_code"
                :label="$t('hr.fields.country')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.city"
                :label="$t('hr.fields.city')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppAutocomplete
                v-model="form.manager_employee_id"
                :server-search="searchManagers"
                preload
                item-title="name"
                item-value="id"
                :label="$t('hr.organization.branch_manager')"
                :disabled="!allBranches"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="form.address"
                :label="$t('hr.fields.address')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.phone"
                :label="$t('hr.fields.phone')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.email"
                type="email"
                :label="$t('hr.fields.email')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.cr_number"
                :label="$t('hr.fields.cr_number')"
                :hint="$t('hr.fields.cr_number_hint')"
                dir="ltr"
                maxlength="10"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.mol_establishment_number"
                :label="$t('hr.fields.mol_establishment_number')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.gosi_registration_number"
                :label="$t('hr.fields.gosi_registration_number')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppSelect
                v-model="form.default_shift_id"
                :items="defaultShiftItems"
                :label="$t('hr.organization.default_shift')"
                :hint="$t('hr.organization.default_shift_hint')"
                persistent-hint
                clearable
                clear-icon="tabler-x"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
              class="d-flex align-center"
            >
              <VSwitch
                v-model="form.is_headquarters"
                :label="$t('hr.fields.is_headquarters')"
                :disabled="!allBranches"
                hide-details
              />
            </VCol>
            <VCol
              v-if="!editingId"
              cols="12"
              sm="4"
              class="d-flex align-center"
            >
              <VSwitch
                v-model="form.is_active"
                :label="$t('Active')"
                hide-details
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
            @click="save"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <HrConfirmDialog
      v-model="confirm.visible"
      :action="confirm.action"
      :message="confirm.action === 'delete' ? $t('hr.organization.confirm_delete_branch') : ''"
      @confirm="runConfirmed"
    />

    <HrConfirmDialog
      :model-value="!!statusTarget"
      :action="statusTarget?.is_active ? 'delete' : 'restore'"
      :message="$t(statusTarget?.is_active ? 'hr.organization.confirm_deactivate_branch' : 'hr.organization.confirm_activate_branch')"
      :confirm-label="$t(statusTarget?.is_active ? 'hr.organization.deactivate' : 'hr.organization.activate')"
      @update:model-value="statusTarget = null"
      @confirm="changeStatus"
    />
  </div>
</template>
