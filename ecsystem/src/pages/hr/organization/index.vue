<script setup>
import HrBranchesTable from '@/views/hr/organization/HrBranchesTable.vue'
import HrDepartmentsTable from '@/views/hr/organization/HrDepartmentsTable.vue'
import HrWorkLocationsTable from '@/views/hr/organization/HrWorkLocationsTable.vue'
import { useRoute, useRouter } from 'vue-router'

const TABS = ['branches', 'departments', 'work_locations']

const route = useRoute()
const router = useRouter()

const activeTab = computed({
  get: () => (TABS.includes(route.query.tab) ? route.query.tab : TABS[0]),
  set: tab => router.replace({ query: { ...route.query, tab } }),
})
</script>

<template>
  <VCard class="hr-page">
    <VTabs
      v-model="activeTab"
      class="hr-page__tabs"
    >
      <VTab value="branches">
        <VIcon
          icon="tabler-building"
          start
        />
        {{ $t('hr.organization.branches') }}
      </VTab>
      <VTab value="departments">
        <VIcon
          icon="tabler-sitemap"
          start
        />
        {{ $t('hr.organization.departments') }}
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
      <VWindowItem value="branches">
        <HrBranchesTable />
      </VWindowItem>
      <VWindowItem value="departments">
        <HrDepartmentsTable />
      </VWindowItem>
      <VWindowItem value="work_locations">
        <HrWorkLocationsTable />
      </VWindowItem>
    </VWindow>
  </VCard>
</template>

<route lang="yaml">
meta:
  action: access_hr-organization
  subject: access_hr-organization
  pageTitleKey: hr.organization.title
  pageSubtitleKey: hr.organization.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
