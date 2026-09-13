<script setup>
import i18n from '@/plugins/i18n/index.js'
import chartsTabs from '@/views/dashboards/statistics/chartsTabs.vue';
import BarChart from '@/views/dashboards/statistics/BarChart.vue'
import add_case from '@images/svg/add_case.svg'
import show_cases from '@images/svg/show_cases.svg'
import { useTheme } from 'vuetify'
import { statisticsApi } from "@/plugins/apis/statisticsRequest";
import { logsApi } from "@/plugins/apis/logsRequest";
import { can } from '@layouts/plugins/casl'
import case_total from '@images/svg/case_total.svg'
import teachers_total from '@images/svg/teachers_total.svg'
import specialists_total from '@images/svg/specialists_total.svg'

const vuetifyTheme = useTheme()
const currentTheme = vuetifyTheme.current.value.colors
const route = useRoute()
const router = useRouter()
const statisticsListStore = statisticsApi()
const logsListStore = logsApi()
const statistics = ref()
const goalsCategories = ref([]);
const goalsSeries = ref([]);
const logComponentKey = ref(0);
const logType = ref('cases');
const logsTypes = ref([]);
const logsNames = ref([]);
const logsValues = ref([]);
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

  response.data.data.recent_goals.forEach(item => {
    var goalsCategory = i18n.global.t('statistics.'+item['category'])
    goalsCategories.value.push(goalsCategory);
    goalsSeries.value.push(item['count']);
  });

  response.data.data.disabilities.forEach(item => {
    disabilitiesNames.value.push(item['name']);
    disabilitiesValues.value.push(item['count']);
  });

  response.data.data.recent_logs.forEach(item => {
    logsNames.value.push(item['name']);
    logsValues.value.push(item['count']);
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
    logsNames.value = [];
    logsValues.value = [];
    response.data.data.forEach(item => {
      logsNames.value.push(item['name']);
      logsValues.value.push(item['count']);
    });
    logComponentKey.value += 1;
  }).catch(error => {
    console.error(error)
  })
})
</script>

<template>
  <VRow class="match-height" v-if="statistics">
    <VCol       
      cols="12"
      md="12">
      
        <VRow>
          <VCol md="4">
            <VCard @click="router.push('/cases/add')" class="d-flex justify-center align-center">
              <v-card-text class="d-flex justify-center align-center text-center">
                <div>
                  <VImg :src="add_case" width="120" height="120" />
                  <h3>{{ $t('Register Case') }}</h3>
                </div>
              </v-card-text>
            </VCard>
          </VCol>
          <VCol md="4">
            <VCard @click="router.push('/cases/list')" class="d-flex ">
              <v-card-text class="d-flex justify-center align-center">
                <div class="align-center text-center">
                  <VImg :src="show_cases" width="120" height="120" />
                  <div class="align-center"><h3>{{ $t('List Cases') }}</h3></div>
                </div>
              </v-card-text>
            </VCard>
          </VCol>
          <VCol md="4">
            <VCard @click="router.push('/cases/assessments')" class="d-flex justify-center align-center">
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
        
        <VRow>
          <VCol md="12">
            <VCard :title="$t('counters')">
              <VCardText>
                <VRow>
                  <VCol md="2.5">
                    <VCard height="180" color="#1A75CF26">
                      <VCardText>
                          <div class="mb-4">
                            <VImg :src="case_total" width="50" height="50" />
                          </div>
                          <div class="text-body-1 mb-3">
                            <h6 class="text-lg font-weight-medium">{{ statistics ? statistics.cases_count : '' }}</h6>
                          </div>
                          <div class="d-flex gap-x-2">
                            <span class="text-sm">{{ $t('cases_total') }}</span>
                          </div>
                      </VCardText>
                    </VCard>
                  </VCol>
                  <VCol md="2.5">
                    <VCard height="180" color="#8833FF33">
                      <VCardText>
                          <div class="mb-4">
                          <VImg :src="teachers_total" width="50" height="50" />
                          </div>
                          <div class="text-body-1 mb-3">
                            <h6 class="text-lg font-weight-medium">{{ statistics ? statistics.teachers_count : '' }}</h6>
                          </div>
                          <div class="d-flex gap-x-2">
                            <span class="text-sm">{{ $t('teachers_total') }}</span>
                          </div>
                      </VCardText>
                    </VCard>
                  </VCol>
                  <VCol md="2.5">
                    <VCard height="180" color="#FFCB3326">
                      <VCardText>
                          <div class="mb-4">
                          <VImg :src="specialists_total" width="50" height="50" />
                          </div>
                          <div class="text-body-1 mb-3">
                            <h6 class="text-lg font-weight-medium">{{ statistics ? statistics.specialists_count : '' }}</h6>
                          </div>
                          <div class="d-flex gap-x-2">
                            <span class="text-sm">{{ $t('specialists_total') }}</span>
                          </div>
                      </VCardText>
                    </VCard>
                  </VCol>
                  <VCol md="2.5">
                    <VCard height="180" color="#29CC3933">
                      <VCardText>
                          <div class="mb-4">
                            <VAvatar
                              color="success"
                              size="50"
                            >
                              <VIcon icon="tabler-credit-card" />
                            </VAvatar>
                          </div>
                          <div class="text-body-1 mb-3">
                            <h6 class="text-lg font-weight-medium">{{ statistics ? statistics.recent_assessments : '' }}</h6>
                          </div>
                          <div class="d-flex gap-x-2">
                            <span class="text-sm">{{ $t('assessments_total') }}</span>
                          </div>
                      </VCardText>
                    </VCard>
                  </VCol>
                  <VCol md="2.5">
                    <VCard height="180" color="#E62E2E1F">
                      <VCardText>
                          <div class="mb-4">
                            <VAvatar
                              color="error"
                              size="50"
                            >
                              <VIcon icon="tabler-analyze" />
                            </VAvatar>
                          </div>
                          <div class="text-body-1 mb-3">
                            <h6 class="text-lg font-weight-medium">{{ statistics ? statistics.recent_goals_count : '' }}</h6>
                          </div>
                          <div class="d-flex gap-x-2">
                            <span class="text-sm">{{ $t('goals_total') }}</span>
                          </div>
                      </VCardText>
                    </VCard>
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>

        <VRow>
          <VCol md="6">
            <VCard :title="$t('statistics.goals_types')">
              <VCardText>
                <BarChart :categories="goalsCategories" :series="goalsSeries" />
              </VCardText>
            </VCard>
          </VCol>
          <VCol md="6">
            <VCard :title="$t('statistics.disabilities')">
              <VCardText>
                <BarChart :categories="disabilitiesNames" :series="disabilitiesValues" />
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
        <VRow>
          <VCol md="6">
            <chartsTabs :daily="statistics.goals_daily" :monthly="statistics.goals_monthly" title="daily_goals"/>
          </VCol>
          <VCol md="6">
            <chartsTabs :daily="statistics.attendance_daily" :monthly="statistics.attendance_monthly" title="attendance"/>
          </VCol>
        </VRow>

        <VRow v-if="canViewLogs">
          <VCol md="12">
            <VCard>
              <VCardText class="d-flex flex-wrap py-4 gap-4">
                <div class="me-3 d-flex gap-3 text-h5">
                  {{ $t('statistics.recent_logs') }}
                </div>
                <VSpacer />
                <div class="me-3 justify-end">
                  <AppSelect
                    v-model="logType"
                    :items="logsTypes"
                    :item-title="item => $t('statistics.'+item.category)"
                    :item-value="item => item.category"
                    style="width: 20rem;"
                  />
                </div>
              </VCardText>
              <VCardText>
                <BarChart :key="logComponentKey" :categories="logsNames" :series="logsValues" />
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
    </VCol>

  </VRow>
</template>

<style lang="scss">
@use "@core/scss/template/libs/apex-chart.scss";
</style>
<route lang="yaml">
  meta:
    action: all-users
    subject: Auth
</route>