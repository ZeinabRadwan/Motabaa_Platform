<script setup>
import i18n from '@/plugins/i18n/index.js';
import { useRoute } from 'vue-router'
import { can } from '@layouts/plugins/casl'
import GoalView from '@/views/plans/goal/list/GoalView.vue';
import GeneralInfoView from '@/views/plans/GeneralInfoView.vue';
import rolesUsersTable from '@/views/plans/rolesUsersTable.vue';
import employeesTable from '@/views/plans/employeesTable.vue';
import planningTeamTable from '@/views/plans/planningTeamTable.vue';
import { operationalPlansApi } from "@/plugins/apis/operationalPlansRequest";

const route = useRoute()
const planListStore = operationalPlansApi()
const planTabs = ref(null)
const plan = ref()

if(route.params.tab == 'general_info') {
  planTabs.value = 0;
}
else if(route.params.tab == 'roles_users') {
  planTabs.value = 1;
}
else if(route.params.tab == 'employees') {
  planTabs.value = 2;
}
else if(route.params.tab == 'planning_team') {
  planTabs.value = 3;
}
else if(route.params.tab == 'goals') {
  planTabs.value = 4;
}

const tabs = [
  {
    number: 1,
    title: 'البيانات',
  },
  {
    number: 2,
    title: 'الكوادر',
  },
  {
    number: 3,
    title: 'الموظفين',
  },
  {
    number: 4,
    title: 'فريق التخطيط',
  },
  {
    number: 5,
    title: 'الأهداف والبرامج',
  },
]

planListStore.fetchPlan(Number(route.params.id)).then(response => {
  plan.value = response.data.data
})
</script>

<template>
  <section v-if="plan">
    <VRow>
      <VCol cols="12">
        <VCol
          cols="12"
          md="12"
          lg="12"
        >
          <VTabs
            v-model="planTabs"
            grow
            class="v-tabs-pill"
          >
            <VTab
              v-for="tab in tabs"
              :key="tab.number"
            >
              <span>{{ tab.title }}</span>
            </VTab>
          </VTabs>
        </VCol>

        <VWindow
          v-model="planTabs"
          class="disable-tab-transition"
          :touch="false"
        >
          <VWindowItem>
            <GeneralInfoView :plan="plan"/>
          </VWindowItem>

          <VWindowItem>
            <rolesUsersTable/>
          </VWindowItem>

          <VWindowItem>
            <employeesTable/>
          </VWindowItem>

          <VWindowItem>
            <planningTeamTable/>
          </VWindowItem>

          <VWindowItem>
            <GoalView/>
          </VWindowItem>
        </VWindow>
      </VCol>
    </VRow>
  </section>
</template>
<route lang="yaml">
  meta:
    action: show_operation-plans
    subject: show_operation-plans
</route>