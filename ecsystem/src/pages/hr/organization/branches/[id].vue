<script setup>
import HrInfoGrid from '@/components/hr/HrInfoGrid.vue'
import HrLocationMap from '@/components/hr/HrLocationMap.vue'
import HrPlacedEmployeesTable from '@/components/hr/HrPlacedEmployeesTable.vue'
import { hrBranchesApi, hrWorkLocationsApi } from '@/plugins/apis/hrRequest'
import HrWorkLocationsTable from '@/views/hr/organization/HrWorkLocationsTable.vue'
import cuntries from '@core/utils/cuntries'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'

const TABS = ['employees', 'work_locations']

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()

const branch = ref(null)
const loading = ref(true)
const locations = ref([])
const employeesTable = ref(null)

const activeTab = computed({
  get: () => (TABS.includes(route.query.tab) ? route.query.tab : TABS[0]),
  set: tab => router.replace({ query: { ...route.query, tab } }),
})

const listRoute = { name: 'hr-organization', query: { tab: 'branches' } }

const countryName = code => cuntries.find(country => country.country_code === code)?.[locale.value === 'ar' ? 'country_arName' : 'country_enName'] ?? code

const infoItems = computed(() => {
  const data = branch.value ?? {}

  return [
    { label: t('hr.fields.code'), value: data.code, ltr: true },
    { label: t('hr.organization.branch_manager'), value: data.manager?.name },
    { label: t('hr.fields.country'), value: countryName(data.country) },
    { label: t('hr.fields.city'), value: data.city },
    { label: t('hr.fields.address'), value: data.address },
    { label: t('hr.fields.phone'), value: data.phone, ltr: true },
    { label: t('hr.fields.email'), value: data.email, ltr: true },
    { label: t('hr.fields.cr_number'), value: data.cr_number, ltr: true },
  ]
})

const loadBranch = () => hrBranchesApi.show(route.params.id).then(response => {
  branch.value = response.data.data
}).catch(() => {
  if (!branch.value)
    router.replace(listRoute)
}).finally(() => {
  loading.value = false
})

const loadLocations = () => hrWorkLocationsApi.map({ branch_id: route.params.id, include_without_coordinates: 1 }).then(response => {
  locations.value = response.data.data ?? []
}).catch(() => {
  locations.value = []
})

const fetchEmployees = params => hrBranchesApi.employees(route.params.id, params)

const onLocationsChanged = () => {
  loadBranch()
  loadLocations()
  employeesTable.value?.refresh()
}

watch(() => route.params.id, id => {
  if (!id)
    return
  branch.value = null
  loading.value = true
  loadBranch()
  loadLocations()
}, { immediate: true })
</script>

<template>
  <section class="hr-page">
    <div
      v-if="loading && !branch"
      class="d-flex justify-center pa-12"
    >
      <VProgressCircular
        indeterminate
        color="primary"
      />
    </div>

    <template v-else-if="branch">
      <VCard class="mb-4">
        <VCardItem>
          <VCardTitle class="d-flex align-center flex-wrap gap-2">
            {{ branch.name }}
            <VChip
              v-if="branch.is_headquarters"
              size="small"
              color="primary"
              label
            >
              {{ $t('hr.fields.is_headquarters') }}
            </VChip>
            <VChip
              size="small"
              label
              :color="branch.is_active ? 'success' : 'secondary'"
            >
              {{ $t(branch.is_active ? 'Active' : 'Inactive') }}
            </VChip>
          </VCardTitle>
          <VCardSubtitle v-if="branch.name_en && branch.name_en !== branch.name">
            {{ branch.name_en }}
          </VCardSubtitle>
        </VCardItem>
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              md="6"
            >
              <HrInfoGrid :items="infoItems" />
              <div class="d-flex gap-4 mt-4">
                <VChip
                  label
                  prepend-icon="tabler-users"
                >
                  {{ $t('hr.organization.employees_count', { count: branch.employees_count ?? 0 }) }}
                </VChip>
                <VChip
                  label
                  prepend-icon="tabler-map-pin"
                >
                  {{ $t('hr.organization.locations_count', { count: branch.work_locations_count ?? 0 }) }}
                </VChip>
              </div>
            </VCol>
            <VCol
              cols="12"
              md="6"
            >
              <HrLocationMap
                :locations="locations"
                height="300px"
              />
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <VCard>
        <VTabs
          v-model="activeTab"
          class="hr-page__tabs"
        >
          <VTab value="employees">
            <VIcon
              icon="tabler-users"
              start
            />
            {{ $t('hr.organization.assigned_employees') }}
          </VTab>
          <VTab value="work_locations">
            <VIcon
              icon="tabler-map-pin"
              start
            />
            {{ $t('hr.organization.work_locations') }}
          </VTab>
        </VTabs>
        <VDivider />
        <VWindow
          v-model="activeTab"
          :touch="false"
        >
          <VWindowItem value="employees">
            <HrPlacedEmployeesTable
              ref="employeesTable"
              :fetcher="fetchEmployees"
              :locations="locations"
            />
          </VWindowItem>
          <VWindowItem value="work_locations">
            <HrWorkLocationsTable
              :branch-id="branch.id"
              @changed="onLocationsChanged"
            />
          </VWindowItem>
        </VWindow>
      </VCard>
    </template>
  </section>
</template>

<route lang="yaml">
meta:
  action: access_hr-organization
  subject: access_hr-organization
  pageTitleKey: hr.organization.branch_details
  pageSubtitleKey: hr.organization.branch_details_subtitle
  backTo:
    name: hr-organization
    query:
      tab: branches
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
