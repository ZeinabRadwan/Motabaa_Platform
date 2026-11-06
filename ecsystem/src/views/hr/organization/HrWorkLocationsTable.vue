<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import HrLocationMap from '@/components/hr/HrLocationMap.vue'
import HrPlacedEmployeesTable from '@/components/hr/HrPlacedEmployeesTable.vue'
import { useHrCrudTable } from '@/composables/useHrCrudTable'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrWorkLocationsApi } from '@/plugins/apis/hrRequest'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

const props = defineProps({
  // Set on the branch page: the table is limited to that branch and the branch filter is hidden.
  branchId: { type: [Number, String], default: null },
})

const emit = defineEmits(['changed'])

const { t } = useI18n()
const { lookups, refresh: refreshLookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-organization', 'edit_hr-organization'))
const canAdmin = computed(() => can('admin_hr-organization', 'admin_hr-organization'))

const viewMode = ref('list')
const branchFilter = ref(null)
const statusFilter = ref(null)
const coordinatesFilter = ref(null)

const effectiveBranch = computed(() => props.branchId ?? branchFilter.value)

const statusItems = computed(() => [
  { value: 1, title: t('Active') },
  { value: 0, title: t('Inactive') },
])

const coordinatesItems = computed(() => [
  { value: 1, title: t('hr.organization.with_coordinates') },
  { value: 0, title: t('hr.organization.no_coordinates') },
])

const listFilters = () => ({
  branch_id: effectiveBranch.value || undefined,
  is_active: statusFilter.value ?? undefined,
  has_coordinates: coordinatesFilter.value ?? undefined,
})

const emptyForm = () => ({
  branch_id: effectiveBranch.value,
  name_ar: '',
  name_en: '',
  address: '',
  latitude: null,
  longitude: null,
  radius_meters: null,
  is_active: true,
})

const toNumberOrNull = value => (value === '' || value === null || value === undefined ? null : Number(value))

// Map view
const mapLocations = ref([])
const mapLoading = ref(false)
const selectedLocationId = ref(null)

const loadMap = () => {
  mapLoading.value = true

  return hrWorkLocationsApi.map({ ...listFilters(), q: search.value || undefined }).then(response => {
    mapLocations.value = response.data.data ?? []
  }).catch(() => {
    mapLocations.value = []
  }).finally(() => {
    mapLoading.value = false
  })
}

const afterChange = () => {
  refreshLookups().catch(() => {})
  if (viewMode.value === 'map')
    loadMap()
  emit('changed')
}

const {
  items, total, loading, search, showTrashed, options, updateOptions, fetchItems,
  isDialogVisible, editingId, form, saving, openDialog, openCreateDialog, save,
  confirm, askConfirm, runConfirmed,
} = useHrCrudTable(hrWorkLocationsApi, {
  filters: listFilters,
  emptyForm,
  toForm: item => ({
    branch_id: item.branch_id,
    name_ar: item.name_ar,
    name_en: item.name_en ?? '',
    address: item.address ?? '',
    latitude: item.latitude,
    longitude: item.longitude,
    radius_meters: item.radius_meters,
    is_active: item.is_active,
  }),
  toPayload: payload => {
    const data = {
      ...payload,
      latitude: toNumberOrNull(payload.latitude),
      longitude: toNumberOrNull(payload.longitude),
      radius_meters: toNumberOrNull(payload.radius_meters),
    }

    // Omitted radius falls back to the center's default GPS radius setting.
    if (data.radius_meters === null)
      delete data.radius_meters
    if (editingId.value)
      delete data.is_active

    return data
  },
  onSaved: afterChange,
})

watch(viewMode, mode => {
  if (mode === 'map')
    loadMap()
})

// The picker shows the other saved locations for context.
watch(isDialogVisible, visible => {
  if (visible && !mapLocations.value.length)
    loadMap()
})
watch([search, branchFilter, statusFilter, coordinatesFilter], () => {
  if (viewMode.value === 'map')
    loadMap()
})

const clearCoordinates = () => {
  form.value.latitude = null
  form.value.longitude = null
}

// Activate / deactivate
const statusTarget = ref(null)

const changeStatus = () => {
  const location = statusTarget.value

  statusTarget.value = null
  if (!location)
    return

  const request = location.is_active ? hrWorkLocationsApi.deactivate(location.id) : hrWorkLocationsApi.activate(location.id)

  request.then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    fetchItems()
    afterChange()
  }).catch(() => {})
}

// Assigned employees
const employeesOf = ref(null)
const fetchLocationEmployees = params => hrWorkLocationsApi.employees(employeesOf.value.id, params)

const headers = computed(() => [
  { title: t('hr.fields.name'), key: 'name' },
  ...(props.branchId ? [] : [{ title: t('hr.fields.branch'), key: 'branch_name', sortable: false }]),
  { title: t('hr.fields.coordinates'), key: 'coordinates', sortable: false },
  { title: t('hr.fields.radius_meters'), key: 'radius_meters' },
  { title: t('hr.organization.assigned_employees'), key: 'employees_count' },
  { title: t('Status'), key: 'is_active', sortable: false },
  { title: t('Actions'), key: 'actions', sortable: false },
])

const showOnMap = item => {
  selectedLocationId.value = item.id
  viewMode.value = 'map'
}

defineExpose({ refresh: fetchItems })
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
          v-if="!branchId"
          cols="12"
          sm="6"
          md="2"
        >
          <AppSelect
            v-model="branchFilter"
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
            v-model="coordinatesFilter"
            :items="coordinatesItems"
            :placeholder="$t('hr.fields.coordinates')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          :md="branchId ? 5 : 3"
          class="d-flex align-center justify-end flex-wrap gap-3"
        >
          <VBtnToggle
            v-model="viewMode"
            mandatory
            density="compact"
            variant="outlined"
            divided
          >
            <VBtn
              value="list"
              icon="tabler-list"
            />
            <VBtn
              value="map"
              icon="tabler-map-2"
            />
          </VBtnToggle>
          <VBtn
            v-if="canEdit"
            prepend-icon="tabler-plus"
            :disabled="!lookups.branches.length"
            @click="openCreateDialog"
          >
            {{ $t('hr.organization.add_work_location') }}
          </VBtn>
        </VCol>
        <VCol
          v-if="canAdmin && viewMode === 'list'"
          cols="12"
          class="py-0"
        >
          <VSwitch
            v-model="showTrashed"
            :label="$t('hr.common.show_deleted')"
            hide-details
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VCardText v-if="viewMode === 'map'">
      <VRow>
        <VCol
          cols="12"
          md="8"
        >
          <HrLocationMap
            :locations="mapLocations"
            :selected-id="selectedLocationId"
            height="480px"
            @select="selectedLocationId = $event.id"
          />
        </VCol>
        <VCol
          cols="12"
          md="4"
        >
          <VProgressLinear
            v-if="mapLoading"
            indeterminate
            class="mb-2"
          />
          <VList
            density="compact"
            class="hr-locations-list"
          >
            <VListItem
              v-for="location in mapLocations"
              :key="location.id"
              :active="location.id === selectedLocationId"
              color="primary"
              @click="selectedLocationId = location.id"
            >
              <VListItemTitle>{{ location.name }}</VListItemTitle>
              <VListItemSubtitle>
                {{ location.branch_name }} · {{ $t('hr.map.radius_value', { meters: location.radius_meters }) }}
              </VListItemSubtitle>
              <template #append>
                <VChip
                  size="x-small"
                  label
                  :color="location.is_active ? 'success' : 'secondary'"
                >
                  {{ location.employees_count }}
                </VChip>
              </template>
            </VListItem>
            <VListItem v-if="!mapLocations.length && !mapLoading">
              <VListItemSubtitle>{{ $t('hr.map.no_locations') }}</VListItemSubtitle>
            </VListItem>
          </VList>
          <p class="text-caption text-medium-emphasis mt-2">
            {{ $t('hr.map.missing_coordinates_hint') }}
          </p>
        </VCol>
      </VRow>
    </VCardText>

    <VDataTableServer
      v-else
      v-model:items-per-page="options.itemsPerPage"
      v-model:page="options.page"
      :items="items"
      :items-length="total"
      :headers="headers"
      :loading="loading"
      class="text-no-wrap"
      @update:options="updateOptions"
    >
      <template #item.branch_name="{ item }">
        <span>{{ item.raw.branch_name }}</span>
        <VChip
          v-if="item.raw.branch_is_active === false"
          size="x-small"
          label
          class="ms-1"
        >
          {{ $t('Inactive') }}
        </VChip>
      </template>
      <template #item.coordinates="{ item }">
        <a
          v-if="item.raw.has_coordinates && !item.raw.deleted_at"
          href="#"
          dir="ltr"
          @click.prevent="showOnMap(item.raw)"
        >
          {{ item.raw.latitude }}, {{ item.raw.longitude }}
        </a>
        <span
          v-else-if="item.raw.has_coordinates"
          dir="ltr"
        >{{ item.raw.latitude }}, {{ item.raw.longitude }}</span>
        <span
          v-else
          class="text-medium-emphasis"
        >{{ $t('hr.organization.no_coordinates') }}</span>
      </template>
      <template #item.employees_count="{ item }">
        <VBtn
          variant="text"
          size="small"
          :disabled="!!item.raw.deleted_at"
          @click="employeesOf = item.raw"
        >
          {{ item.raw.employees_count ?? 0 }}
        </VBtn>
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
              v-if="canAdmin"
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
              v-if="canEdit"
              icon
              variant="text"
              size="small"
              @click="openDialog(item.raw)"
            >
              <VIcon icon="tabler-edit" />
            </VBtn>
            <VBtn
              v-if="canAdmin"
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
              v-if="canAdmin"
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
      max-width="900"
      scrollable
    >
      <VCard :title="$t(editingId ? 'hr.organization.edit_work_location' : 'hr.organization.add_work_location')">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="5"
            >
              <VRow>
                <VCol cols="12">
                  <AppSelect
                    v-model="form.branch_id"
                    :items="lookups.branches"
                    :label="`${$t('hr.fields.branch')} *`"
                    :disabled="!!branchId"
                  />
                </VCol>
                <VCol cols="12">
                  <AppTextField
                    v-model="form.name_ar"
                    :label="`${$t('hr.fields.name_ar')} *`"
                    dir="rtl"
                  />
                </VCol>
                <VCol cols="12">
                  <AppTextField
                    v-model="form.name_en"
                    :label="$t('hr.fields.name_en')"
                    dir="ltr"
                  />
                </VCol>
                <VCol cols="12">
                  <AppTextField
                    v-model="form.address"
                    :label="$t('hr.fields.address')"
                  />
                </VCol>
                <VCol cols="6">
                  <AppTextField
                    v-model="form.latitude"
                    type="number"
                    step="0.0000001"
                    :label="$t('hr.fields.latitude')"
                    dir="ltr"
                  />
                </VCol>
                <VCol cols="6">
                  <AppTextField
                    v-model="form.longitude"
                    type="number"
                    step="0.0000001"
                    :label="$t('hr.fields.longitude')"
                    dir="ltr"
                  />
                </VCol>
                <VCol cols="12">
                  <AppTextField
                    v-model="form.radius_meters"
                    type="number"
                    min="10"
                    max="5000"
                    :label="$t('hr.fields.radius_meters')"
                    :hint="$t('hr.fields.radius_hint')"
                    persistent-hint
                    dir="ltr"
                  />
                </VCol>
                <VCol
                  v-if="!editingId"
                  cols="12"
                  class="d-flex align-center"
                >
                  <VSwitch
                    v-model="form.is_active"
                    :label="$t('Active')"
                    hide-details
                  />
                </VCol>
              </VRow>
            </VCol>
            <VCol
              cols="12"
              md="7"
            >
              <HrLocationMap
                v-model:latitude="form.latitude"
                v-model:longitude="form.longitude"
                :radius="form.radius_meters"
                :locations="mapLocations.filter(location => location.id !== editingId)"
                editable
                height="420px"
              />
              <div class="d-flex align-center justify-space-between mt-2">
                <span class="text-caption text-medium-emphasis">{{ $t('hr.map.picker_hint') }}</span>
                <VBtn
                  v-if="form.latitude !== null && form.latitude !== ''"
                  size="small"
                  variant="text"
                  color="error"
                  prepend-icon="tabler-map-pin-off"
                  @click="clearCoordinates"
                >
                  {{ $t('hr.map.clear_point') }}
                </VBtn>
              </div>
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

    <VDialog
      :model-value="!!employeesOf"
      max-width="860"
      @update:model-value="employeesOf = null"
    >
      <VCard
        v-if="employeesOf"
        :title="$t('hr.organization.employees_of', { name: employeesOf.name })"
      >
        <HrPlacedEmployeesTable
          :fetcher="fetchLocationEmployees"
          :show-location="false"
        />
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="employeesOf = null"
          >
            {{ $t('Close') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <HrConfirmDialog
      v-model="confirm.visible"
      :action="confirm.action"
      @confirm="runConfirmed"
    />

    <HrConfirmDialog
      :model-value="!!statusTarget"
      :action="statusTarget?.is_active ? 'delete' : 'restore'"
      :message="$t(statusTarget?.is_active ? 'hr.organization.confirm_deactivate_location' : 'hr.organization.confirm_activate_location')"
      :confirm-label="$t(statusTarget?.is_active ? 'hr.organization.deactivate' : 'hr.organization.activate')"
      @update:model-value="statusTarget = null"
      @confirm="changeStatus"
    />
  </div>
</template>

<style lang="scss">
.hr-locations-list {
  overflow-y: auto;
  max-block-size: 440px;
}
</style>
