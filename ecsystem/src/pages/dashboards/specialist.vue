<script setup>
import add_case from '@images/svg/add_case.svg'
import show_cases from '@images/svg/show_cases.svg'
const route = useRoute()
const router = useRouter()
</script>

<template>
  <VRow class="match-height">
    <VCol       
      cols="12"
      md="12">
        <VRow>
          <VCol md="4">
            <VCard @click="router.push('/cases/add')" class="d-flex justify-center align-center" height="250">
              <v-card-text class="d-flex justify-center align-center text-center">
                <div>
                  <VImg :src="add_case" width="120" height="120" />
                  <h3>{{ $t('Register Case') }}</h3>
                </div>
              </v-card-text>
            </VCard>
          </VCol>
          <VCol md="4">
            <VCard @click="router.push('/cases/list')" class="d-flex " height="250">
              <v-card-text class="d-flex justify-center align-center">
                <div class="align-center text-center">
                  <VImg :src="show_cases" width="120" height="120" />
                  <div class="align-center"><h3>{{ $t('List Cases') }}</h3></div>
                </div>
              </v-card-text>
            </VCard>
          </VCol>
          <VCol md="4">
            <VCard @click="router.push('/cases/assessments')" class="d-flex justify-center align-center" height="250">
              <v-card-text class="d-flex justify-center align-center text-center">
                <div>
                  <div class="d-flex justify-center align-center">
                    <VImg :src="add_case" width="120" height="120" />
                  </div>
                  <div class="align-center"><h3>{{ $t('Comprehensive Assessment') }}</h3></div>
                </div>
              </v-card-text>
            </VCard>
          </VCol>
        </VRow>
    </VCol>

    
    <!-- <VCol
      cols="12"
      md="6"
    >
      <AnalyticsWebsiteAnalytics />
    </VCol>

    <VCol
      cols="12"
      md="3"
      sm="6"
    >
      <AnalyticsSalesOverview />
    </VCol>

    <VCol
      cols="12"
      md="3"
      sm="6"
    >
      <CardStatisticsVertical v-bind="statisticsVertical" />
    </VCol>

    <VCol
      cols="12"
      md="6"
    >
      <AnalyticsEarningReportsWeeklyOverview />
    </VCol>

    <VCol
      cols="12"
      md="6"
    >
      <AnalyticsSupportTracker />
    </VCol>

    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <AnalyticsSalesByCountries />
    </VCol>

    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <AnalyticsTotalEarning />
    </VCol>

    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <AnalyticsMonthlyCampaignState />
    </VCol>

    <VCol
      cols="12"
      sm="6"
      lg="4"
    >
      <AnalyticsSourceVisits />
    </VCol>

    <VCol
      cols="12"
      lg="8"
    >
      <AnalyticsProjectTable />
    </VCol> -->
  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/libs/apex-chart.scss";
</style>
<route lang="yaml">
  meta:
    action: specialist
    subject: specialist
</route>