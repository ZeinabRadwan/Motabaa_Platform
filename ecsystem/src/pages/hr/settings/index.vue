<script setup>
import HrApprovalWorkflows from '@/views/hr/settings/HrApprovalWorkflows.vue'
import HrSettingsForm from '@/views/hr/settings/HrSettingsForm.vue'
import { can } from '@layouts/plugins/casl'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const tabs = computed(() => [
  ...(can('access_hr-settings', 'access_hr-settings') ? [{ value: 'general', titleKey: 'hr.settings.title', icon: 'tabler-settings' }] : []),
  ...(can('access_hr-approvals', 'access_hr-approvals') || can('edit_hr-approvals', 'edit_hr-approvals')
    ? [{ value: 'workflows', titleKey: 'hr.workflows.title', icon: 'tabler-git-branch' }]
    : []),
])

const activeTab = computed({
  get: () => (tabs.value.some(tab => tab.value === route.query.tab) ? route.query.tab : tabs.value[0]?.value),
  set: tab => router.replace({ query: { ...route.query, tab } }),
})
</script>

<template>
  <VCard class="hr-page">
    <VTabs
      v-model="activeTab"
      class="hr-page__tabs"
    >
      <VTab
        v-for="tab in tabs"
        :key="tab.value"
        :value="tab.value"
      >
        <VIcon
          :icon="tab.icon"
          start
        />
        {{ $t(tab.titleKey) }}
      </VTab>
    </VTabs>
    <VDivider />

    <VWindow
      v-model="activeTab"
      :touch="false"
    >
      <VWindowItem
        v-if="tabs.some(tab => tab.value === 'general')"
        value="general"
      >
        <HrSettingsForm />
      </VWindowItem>
      <VWindowItem
        v-if="tabs.some(tab => tab.value === 'workflows')"
        value="workflows"
      >
        <HrApprovalWorkflows />
      </VWindowItem>
    </VWindow>
  </VCard>
</template>

<route lang="yaml">
meta:
  action: access_hr-settings
  subject: access_hr-settings
  actionsAny:
    - access_hr-settings
    - access_hr-approvals
    - edit_hr-approvals
  pageTitleKey: hr.settings.page_title
  pageSubtitleKey: hr.settings.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
