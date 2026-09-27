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
import { isParentUser } from '@core/utils/staffSessionVisibility'

const casesReqest = casesApi()
const route = useRoute()
const userData = ref()

const tabDefinitions = [
  {
    id: 'info',
    icon: 'tabler-user',
    title: 'بيانات الحالة',
  },
  {
    id: 'messages',
    icon: 'tabler-message',
    title: 'تعليقات',
  },
  {
    id: 'attendance',
    icon: 'tabler-calendar-check',
    title: 'الحضور',
    staffOnly: true,
  },
  {
    id: 'payments',
    icon: 'tabler-currency-dollar',
    title: 'الرسوم الدراسية',
  },
  {
    id: 'files',
    icon: 'tabler-file-description',
    title: 'المرفقات',
  },
  {
    id: 'statistics',
    icon: 'tabler-file-description',
    title: 'الإحصائيات',
  },
]

const tabs = computed(() => tabDefinitions.filter(tab => !(tab.staffOnly && isParentUser())))

const resolveTabIndex = tabParam => {
  const param = tabParam || 'info'
  if (param === 'attendance' && isParentUser()) {
    return 0
  }
  const idx = tabs.value.findIndex(tab => tab.id === param)

  return idx >= 0 ? idx : 0
}

const userTab = ref(resolveTabIndex(route.params.tab))

watch(() => route.params.tab, tabParam => {
  userTab.value = resolveTabIndex(tabParam)
})

casesReqest.fetchCase(Number(route.params.id)).then(response => {
  userData.value = response.data.data
})
</script>

<template>
  <VRow v-if="userData">
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
          :key="tab.icon"
        >
          <VIcon
            :size="18"
            :icon="tab.icon"
            class="me-1"
          />
          <span>{{ tab.title }}</span>
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

        <VWindowItem v-if="!isParentUser()">
          <CaseAttendances :case-data="userData" />
        </VWindowItem>

        <VWindowItem>
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