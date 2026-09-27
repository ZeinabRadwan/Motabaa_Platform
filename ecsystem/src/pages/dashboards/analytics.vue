<script setup>
import i18n from '@/plugins/i18n/index.js'
import chartsTabs from '@/views/dashboards/statistics/chartsTabs.vue';
import DashboardDisabilitiesChart from '@/views/dashboards/statistics/DashboardDisabilitiesChart.vue'
import DashboardStaffActivityRank from '@/views/dashboards/statistics/DashboardStaffActivityRank.vue'
import DashboardQuickActions from '@/views/dashboards/statistics/DashboardQuickActions.vue'
import { statisticsApi } from "@/plugins/apis/statisticsRequest";
import { logsApi } from "@/plugins/apis/logsRequest";
import { can } from '@layouts/plugins/casl'
import DashboardGoalsTypesChart from '@/views/dashboards/statistics/DashboardGoalsTypesChart.vue'
import DashboardStatisticsKpis from '@/views/dashboards/statistics/DashboardStatisticsKpis.vue'
import DashboardWelcome from '@/views/dashboards/statistics/DashboardWelcome.vue'
import dashboardBg from '@images/dashboard.png'

const statisticsListStore = statisticsApi()
const logsListStore = logsApi()
const statistics = ref()
const goalsCategories = ref([]);
const goalsSeries = ref([]);
const logType = ref('cases');
const logsTypes = ref([]);
const logsStaff = ref([]);
const disabilitiesNames = ref([]);
const disabilitiesValues = ref([]);
const canViewLogs = computed(() => can('access_logs', 'access_logs') || can('admin_logs', 'admin_logs'))

if (canViewLogs.value) {
  logsListStore.fetchTypes().then(response => {
    logsTypes.value = response?.data?.data || []
    logsTypes.value.push({'category': 'all'});
  }).catch(() => {
    logsTypes.value = []
  })
}

statisticsListStore.fetchAdminDshboard({
  log_type: logType.value
}).then(response => {
  statistics.value = response.data.data

  ;(response.data.data.recent_goals || []).forEach(item => {
    var goalsCategory = i18n.global.t('statistics.'+item['category'])
    goalsCategories.value.push(goalsCategory);
    goalsSeries.value.push(item['count']);
  });

  response.data.data.disabilities.forEach(item => {
    disabilitiesNames.value.push(item['name']);
    disabilitiesValues.value.push(item['count']);
  });

  response.data.data.recent_logs.forEach(item => {
    logsStaff.value.push({
      id: item.id,
      name: item.name,
      count: item.count,
    });
  });
}).catch(error => {
  console.error(error)
})

watch(logType, query => {
  if (!canViewLogs.value)
    return
  logsListStore.fetchStatistics({
    category: query,
  }).then(response => {
    logsStaff.value = [];
    response.data.data.forEach(item => {
      logsStaff.value.push({
        id: item.id,
        name: item.name,
        count: item.count,
      });
    });
  }).catch(error => {
    console.error(error)
  })
})
</script>

<template>
  <div
    class="athar-dashboard-view"
    :style="{ '--athar-dashboard-bg': `url(${dashboardBg})` }"
  >
    <div
      class="athar-dashboard-view__backdrop"
      aria-hidden="true"
    />

    <div class="athar-dashboard">
    <DashboardWelcome />

    <template v-if="statistics">
    <section
      class="athar-dashboard__zone athar-dashboard__zone--actions"
      :aria-label="$t('dashboard.section_quick_actions')"
    >
      <DashboardQuickActions />
    </section>

    <section
      class="athar-dashboard__zone athar-dashboard__zone--kpis"
      :aria-label="$t('dashboard.section_kpis')"
    >
      <DashboardStatisticsKpis :statistics="statistics" />
    </section>

    <section
      class="athar-dashboard__zone athar-dashboard__zone--analytics"
      :aria-label="$t('dashboard.section_analytics')"
    >
      <div class="athar-dashboard__grid athar-dashboard__grid--charts-sm">
        <DashboardDisabilitiesChart
          :categories="disabilitiesNames"
          :series="disabilitiesValues"
        />
        <DashboardGoalsTypesChart
          :categories="goalsCategories"
          :series="goalsSeries"
        />
      </div>

      <div class="athar-dashboard__grid athar-dashboard__grid--charts-lg">
        <chartsTabs
          chart-set="attendance"
          :daily="statistics.attendance_daily"
          :monthly="statistics.attendance_monthly"
          title="attendance"
        />
        <chartsTabs
          chart-set="activities"
          :daily="statistics.goals_daily"
          :monthly="statistics.goals_monthly"
          title="daily_goals"
        />
      </div>
    </section>

    <section
      v-if="canViewLogs"
      class="athar-dashboard__zone athar-dashboard__zone--staff"
      :aria-label="$t('dashboard.section_staff')"
    >
      <DashboardStaffActivityRank
        v-model:category="logType"
        :categories="logsTypes"
        :rows="logsStaff"
      />
    </section>
    </template>
    </div>
  </div>
</template>

<style lang="scss">
@use "@core/scss/template/libs/apex-chart.scss";
@import '@/styles/dashboard-page.scss';
</style>
<route lang="yaml">
  meta:
    action: all-users
    subject: Auth
</route>
