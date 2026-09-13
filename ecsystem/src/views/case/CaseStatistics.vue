<script setup>
import i18n from '@/plugins/i18n/index.js'
import chartsTabs from '@/views/dashboards/statistics/chartsTabs.vue';
import BarChart from '@/views/dashboards/statistics/BarChart.vue'
import MultiLineChart from '@/views/dashboards/statistics/MultiLineChart.vue'
import { useTheme } from 'vuetify'
import { statisticsApi } from "@/plugins/apis/statisticsRequest";
import VueApexCharts from 'vue3-apexcharts'
import { getSecondDonutChartConfig } from '@core/libs/apex-chart/apexCharConfig'

const props = defineProps({
  caseData: {
    type: Object,
    required: true,
  },
})

const vuetifyTheme = useTheme()
const currentTheme = vuetifyTheme.current.value.colors
const route = useRoute()
const router = useRouter()
const statisticsListStore = statisticsApi()
const statistics = ref()
const goalsCategories = ref([]);
const goalsSeries = ref([]);

const chartLabels = [
  i18n.global.t('statistics.power'),
  i18n.global.t('statistics.weak'),
  i18n.global.t('statistics.not_assessed')
];

const chartColors = [
  '#2CA02C', 
  '#FF7F0E',
  '#A6A8AA',
];

const expenseRationChartConfig = computed(() => getSecondDonutChartConfig(vuetifyTheme.current.value, chartLabels, chartColors))

statisticsListStore.fetchCase(props.caseData.id).then(response => {
  statistics.value = response.data.data

  response.data.data.recent_goals.forEach(item => {
    var goalsCategory = i18n.global.t('statistics.'+item['category'])
    goalsCategories.value.push(goalsCategory);
    goalsSeries.value.push(item['count']);
  });

  response.data.data.goals.forEach(item => {
  });
}).catch(error => {
  console.error(error)
})

</script>

<template>
        
  <VRow v-if="statistics" v-for="(goal, key) in statistics.goals">
    <VCol
      v-if="goal.ability.length!=0"
      :key="key"
      md="12"
    >
      <VCard 
        :title="$t('statistics.'+goal.category)"
       >
        <VCardText>
          <span>
            <VueApexCharts
              type="donut"
              height="250"
              :options="expenseRationChartConfig"
              :series="goal.values"
            />
          </span>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>

  <VRow class="match-height" v-if="statistics">
    <VCol       
      cols="12"
      md="12">
        <VRow>
          <VCol md="12">
            <VCard :title="$t('statistics.goals_types')">
              <VCardText>
                <BarChart :categories="goalsCategories" :series="goalsSeries" />
              </VCardText>
            </VCard>
          </VCol>

          <VCol md="12">
            <chartsTabs :daily="statistics.goals_daily" :monthly="statistics.goals_monthly" title="daily_goals"/>
          </VCol>
          
          <VCol md="12">
            <VCard :title="$t('statistics.finished_goals')">
              <VCardText>
                <MultiLineChart :setOne="statistics.terms_goals" :setTwo="statistics.goals_evaluations" />
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
    </VCol>
  </VRow>
</template>