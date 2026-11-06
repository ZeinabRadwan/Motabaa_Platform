<script setup>
import DashboardWelcome from '@/views/dashboards/statistics/DashboardWelcome.vue'
import ParentBeneficiaryCards from '@/views/dashboards/parent/ParentBeneficiaryCards.vue'
import ParentDashboardInsights from '@/views/dashboards/parent/ParentDashboardInsights.vue'
import ParentDashboardStats from '@/views/dashboards/parent/ParentDashboardStats.vue'
import dashboardBg from '@images/dashboard.png'
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
      <DashboardWelcome subtitle-key="dashboard.parent_welcome_subtitle" />

      <section class="athar-dashboard__zone">
        <ParentDashboardStats />
      </section>

      <section
        id="parent-dashboard-beneficiaries"
        class="athar-dashboard__zone"
      >
        <ParentBeneficiaryCards />
      </section>

      <section
        id="parent-dashboard-insights"
        class="athar-dashboard__zone"
      >
        <ParentDashboardInsights />
      </section>
    </div>
  </div>
</template>

<style lang="scss">
@import '@styles/dashboard-page.scss';
</style>

<route lang="yaml">
  meta:
    action: parent
    subject: parent
</route>
