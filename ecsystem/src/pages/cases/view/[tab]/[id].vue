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

const casesReqest = casesApi()
const route = useRoute()
const userData = ref()
const userTab = ref(null)

if(route.params.tab && route.params.tab == 'info') {
  userTab.value = 0
}
else if(route.params.tab && route.params.tab == 'messages') {
  userTab.value = 1
}
else if(route.params.tab && route.params.tab == 'attendance') {
  userTab.value = 2
}
else if(route.params.tab && route.params.tab == 'payments') {
  userTab.value = 3
}
else if(route.params.tab && route.params.tab == 'files') {
  userTab.value = 4
}
else if(route.params.tab && route.params.tab == 'statistics') {
  userTab.value = 5
}

const tabs = [
  {
    icon: 'tabler-user',
    title: 'بيانات الحالة',
  },
  {
    icon: 'tabler-message',
    title: 'تعليقات',
  },
  {
    icon: 'tabler-calendar-check',
    title: 'الحضور',
  },
  {
    icon: 'tabler-currency-dollar',
    title: 'الرسوم الدراسية',
  },
  {
    icon: 'tabler-file-description',
    title: 'المرفقات',
  },
  {
    icon: 'tabler-file-description',
    title: 'الإحصائيات',
  },
]

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

        <VWindowItem>
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