<script setup>
import {casesApi} from "@/plugins/apis/casesReqest"
import UserBioPanel from '@/views/apps/user/view/UserBioPanel.vue'
import CaseBioPanel from '@/views/case/components/CaseBioPanel.vue';
import CaseAttendances from '@/views/case/CaseAttendances.vue';
import CaseInfoView from '@/views/case/CaseInfoView.vue';
import CaseMessages from '@/views/case/CaseMessages.vue';
import CaseFiles from '@/views/case/CaseFiles.vue';
import CasePayments from '@/views/case/CasePayments.vue';
import CaseStatistics from '@/views/case/CaseStatistics.vue';

import UserTabAccount from '@/views/apps/user/view/UserTabAccount.vue'
import UserTabBillingsPlans from '@/views/apps/user/view/UserTabBillingsPlans.vue'
import UserTabConnections from '@/views/apps/user/view/UserTabConnections.vue'
import UserTabNotifications from '@/views/apps/user/view/UserTabNotifications.vue'
import UserTabSecurity from '@/views/apps/user/view/UserTabSecurity.vue'
import { abilityCanAny, isParentUser } from '@core/utils/staffSessionVisibility'

const casesReqest = casesApi()
const route = useRoute()
const userData = ref()
const loadFailed = ref(false)

// Fees API is gated by the study-fees module; parents without it would only get a 403.
const parentCanSeeFees = () => abilityCanAny([
  'access_study-fees',
  'show_study-fees',
  'edit_study-fees',
  'admin_study-fees',
])

const hiddenForParent = tab => isParentUser()
  && (tab.staffOnly || (tab.id === 'payments' && !parentCanSeeFees()))

// `messages` keeps its id: WhatsApp notifications link to /cases/view/messages/{id}.
const tabDefinitions = [
  {
    id: 'info',
    icon: 'tabler-user',
    titleKey: 'case_profile.tab_info',
  },
  {
    id: 'messages',
    icon: 'tabler-video',
    titleKey: 'case_profile.tab_session_clips',
  },
  {
    id: 'attendance',
    icon: 'tabler-calendar-check',
    titleKey: 'case_profile.tab_attendance',
    staffOnly: true,
  },
  {
    id: 'payments',
    icon: 'tabler-currency-dollar',
    titleKey: 'case_profile.tab_payments',
  },
  {
    id: 'files',
    icon: 'tabler-file-description',
    titleKey: 'case_profile.tab_files',
  },
  {
    id: 'statistics',
    icon: 'tabler-chart-bar',
    titleKey: 'case_profile.tab_statistics',
  },
]

const tabs = computed(() => tabDefinitions.filter(tab => !hiddenForParent(tab)))

const showTab = id => tabs.value.some(tab => tab.id === id)

const resolveTabIndex = tabParam => {
  const param = tabParam || 'info'
  if (!showTab(param)) {
    return 0
  }
  const idx = tabs.value.findIndex(tab => tab.id === param)

  return idx >= 0 ? idx : 0
}

const userTab = ref(resolveTabIndex(route.params.tab))

watch(() => route.params.tab, tabParam => {
  userTab.value = resolveTabIndex(tabParam)
})

const loadCase = id => {
  loadFailed.value = false
  casesReqest.fetchCase(Number(id)).then(response => {
    userData.value = response.data.data
    loadFailed.value = !response.data.data
  }).catch(() => {
    userData.value = null
    loadFailed.value = true
  })
}

loadCase(route.params.id)

watch(() => route.params.id, (id, previousId) => {
  if (id && id !== previousId)
    loadCase(id)
})
</script>

<template>
  <VAlert
    v-if="loadFailed && isParentUser()"
    type="warning"
    variant="tonal"
  >
    {{ $t('dashboard.parent_case_profile_unavailable') }}
  </VAlert>

  <VRow
    v-else-if="userData"
    :key="userData.id"
  >
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <CaseBioPanel :user-data="userData" />
    </VCol>

    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <VTabs
        v-model="userTab"
        grow
        class="v-tabs-pill"
      >
        <VTab
          v-for="tab in tabs"
          :key="tab.id"
        >
          <VIcon
            :size="18"
            :icon="tab.icon"
            class="me-1"
          />
          <span>{{ $t(tab.titleKey) }}</span>
        </VTab>
      </VTabs>

      <VWindow
        v-model="userTab"
        class="mt-6 disable-tab-transition"
        :touch="false"
      >
        <VWindowItem>
          <CaseInfoView :case-data="userData" />
        </VWindowItem>

        <VWindowItem>
          <CaseMessages :case-data="userData" />
        </VWindowItem>

        <VWindowItem v-if="showTab('attendance')">
          <CaseAttendances :case-data="userData" />
        </VWindowItem>

        <VWindowItem v-if="showTab('payments')">
          <CasePayments :case-data="userData" />
        </VWindowItem>

        <VWindowItem>
          <CaseFiles />
        </VWindowItem>

        <VWindowItem>
          <CaseStatistics :case-data="userData" />
        </VWindowItem>
      </VWindow>
    </VCol>
  </VRow>
</template>
<route lang="yaml">
  meta:
    action: show_cases
    subject: show_cases
</route>