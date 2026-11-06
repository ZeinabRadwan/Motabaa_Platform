<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

/** Employed staff currently assigned to a branch or work location (GET …/{id}/employees). */
const props = defineProps({
  fetcher: { type: Function, required: true },
  filters: { type: Object, default: () => ({}) },
  locations: { type: Array, default: null },
  showLocation: { type: Boolean, default: true },
})

const { t } = useI18n()

const canOpenProfile = computed(() => can('access_hr-employees', 'access_hr-employees'))

const items = ref([])
const total = ref(0)
const loading = ref(false)
const search = ref('')
const locationFilter = ref(null)
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })

const fetchItems = () => {
  loading.value = true

  return props.fetcher({
    ...props.filters,
    work_location_id: locationFilter.value || undefined,
    q: search.value || undefined,
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

watchServerTableFetch(fetchItems, {
  search,
  filters: () => [locationFilter.value, props.filters],
  options,
})

const updateOptions = incoming => applyServerTableOptions(options, incoming)

const headers = computed(() => [
  { title: t('hr.fields.employee_number'), key: 'employee_number' },
  { title: t('hr.fields.name'), key: 'name' },
  { title: t('hr.fields.department'), key: 'department_name', sortable: false },
  ...(props.showLocation ? [{ title: t('hr.fields.work_location'), key: 'work_location_name', sortable: false }] : []),
  { title: t('hr.organization.assigned_since'), key: 'assigned_since' },
])

defineExpose({ refresh: fetchItems })
</script>

<template>
  <div>
    <VCardText>
      <VRow>
        <VCol
          cols="12"
          sm="6"
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
          v-if="locations"
          cols="12"
          sm="6"
        >
          <AppSelect
            v-model="locationFilter"
            :items="locations"
            item-title="name"
            item-value="id"
            :placeholder="$t('hr.fields.work_location')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
      </VRow>
    </VCardText>
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
      <template #item.employee_number="{ item }">
        <span dir="ltr">{{ item.raw.employee_number }}</span>
      </template>
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-2">
          <VAvatar
            size="30"
            color="primary"
            variant="tonal"
          >
            <VImg
              v-if="item.raw.photo_url"
              :src="item.raw.photo_url"
            />
            <span v-else>{{ (item.raw.name || '?').charAt(0) }}</span>
          </VAvatar>
          <div>
            <RouterLink
              v-if="canOpenProfile"
              :to="{ name: 'hr-employees-view-id', params: { id: item.raw.id } }"
            >
              {{ item.raw.name }}
            </RouterLink>
            <span v-else>{{ item.raw.name }}</span>
            <div class="text-caption text-medium-emphasis">
              {{ item.raw.job_title }}
            </div>
          </div>
        </div>
      </template>
      <template #item.department_name="{ item }">
        {{ item.raw.department_name || '—' }}
      </template>
      <template #item.work_location_name="{ item }">
        {{ item.raw.work_location_name || '—' }}
      </template>
      <template #item.assigned_since="{ item }">
        <span dir="ltr">{{ item.raw.assigned_since || '—' }}</span>
      </template>
    </VDataTableServer>
  </div>
</template>
