<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import HrLeaveBalancesTable from '@/views/hr/leave/HrLeaveBalancesTable.vue'
import HrLeaveRequestDialog from '@/views/hr/leave/HrLeaveRequestDialog.vue'
import HrLeaveRequestsTable from '@/views/hr/leave/HrLeaveRequestsTable.vue'
import HrLeaveTypesTable from '@/views/hr/leave/HrLeaveTypesTable.vue'
import { can } from '@layouts/plugins/casl'
import { useRoute, useRouter } from 'vue-router'

const TABS = [
  { value: 'requests', icon: 'tabler-list-details', title: 'hr.leave.requests' },
  { value: 'balances', icon: 'tabler-scale', title: 'hr.leave.balances' },
  { value: 'types', icon: 'tabler-category', title: 'hr.leave.types' },
]

const route = useRoute()
const router = useRouter()
const { lookups } = useHrLookups()

const activeTab = computed({
  get: () => (TABS.some(tab => tab.value === route.query.tab) ? route.query.tab : TABS[0].value),
  set: tab => router.replace({ query: { ...route.query, tab } }),
})

const canEdit = computed(() => can('edit_hr-leave', 'edit_hr-leave') || can('admin_hr-leave', 'admin_hr-leave'))
const canAdmin = computed(() => can('admin_hr-leave', 'admin_hr-leave'))
const leaveTypes = computed(() => lookups.value.leave_types ?? [])

const requestOpen = ref(false)
const requests = ref(null)
const balances = ref(null)

const onChanged = () => {
  requests.value?.refresh()
  balances.value?.refresh()
}
</script>

<template>
  <VCard class="hr-page">
    <div class="d-flex align-center flex-wrap">
      <VTabs
        v-model="activeTab"
        class="hr-page__tabs flex-grow-1"
        show-arrows
      >
        <VTab
          v-for="tab in TABS"
          :key="tab.value"
          :value="tab.value"
        >
          <VIcon
            :icon="tab.icon"
            start
          />
          {{ $t(tab.title) }}
        </VTab>
      </VTabs>
      <VBtn
        v-if="canEdit"
        class="ma-2"
        prepend-icon="tabler-plus"
        @click="requestOpen = true"
      >
        {{ $t('hr.leave.new_request') }}
      </VBtn>
    </div>
    <VDivider />

    <VWindow
      v-model="activeTab"
      :touch="false"
    >
      <VWindowItem value="requests">
        <HrLeaveRequestsTable
          ref="requests"
          mode="hr"
          :leave-types="leaveTypes"
          :can-cancel="canEdit"
          :can-cancel-approved="canAdmin"
          @changed="balances?.refresh()"
        />
      </VWindowItem>
      <VWindowItem value="balances">
        <HrLeaveBalancesTable
          ref="balances"
          :can-adjust="canAdmin"
        />
      </VWindowItem>
      <VWindowItem value="types">
        <HrLeaveTypesTable />
      </VWindowItem>
    </VWindow>

    <HrLeaveRequestDialog
      v-model="requestOpen"
      mode="hr"
      :leave-types="leaveTypes"
      :can-record="canAdmin"
      @saved="onChanged"
    />
  </VCard>
</template>

<route lang="yaml">
meta:
  action: access_hr-leave
  subject: access_hr-leave
  actionsAny:
    - access_hr-leave
    - edit_hr-leave
    - admin_hr-leave
    - apply_hr-leave
  pageTitleKey: hr.leave.title
  pageSubtitleKey: hr.leave.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
