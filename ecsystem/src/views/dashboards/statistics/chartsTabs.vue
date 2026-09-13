<script setup>
import BarChart from '@/views/dashboards/statistics/BarChart.vue'
import LineChart from '@/views/dashboards/statistics/LineChart.vue'

const tabs = [
  {
    tabName: 'يومي',
  },
  {
    tabName: 'شهري',
  }
]

const currentActiveTab = ref()
const props = defineProps({
  daily: {
    type: Object,
    required: true,
  },
  monthly: {
    type: Object,
    required: true,
  },
  title: 'title'
})

const months = {
  1: 'Jan',
  2: 'Feb',
  3: 'Mar',
  4: 'Apr',
  5: 'May',
  6: 'Jun',
  7: 'Jul',
  8: 'Aug',
  9: 'Sep',
  10: 'Oct',
  11: 'Nov',
  12: 'Dec'
};

const dailyCategories = ref([]);
const dailySeries = ref([]);
props.daily.forEach(item => {
  var dailyCategory = item['day']+" "+months[item['month']]
  dailyCategories.value.push(dailyCategory);
  dailySeries.value.push(item['count']);
});

const monthlyCategories = ref([]);
const monthlySeries = ref([]);
props.monthly.forEach(item => {
  var monthlyCategory = months[item['month']]+" "+item['year']
  monthlyCategories.value.push(monthlyCategory);
  monthlySeries.value.push(item['count']);
});

</script>

<template>
  <VCard
    :title="$t('statistics.'+props.title)"
  >
    <VTabs
      v-model="currentActiveTab"
      grow
    >
      <VTab
        v-for="tab in tabs"
        :key="tab.tabName"
      >
        {{ tab.tabName }}
      </VTab>
    </VTabs>

    <VCardText>
      <VWindow
        v-model="currentActiveTab"
        class="mt-6 disable-tab-transition"
        :touch="false"
      >
        <VWindowItem>
          <LineChart :categories="dailyCategories" :series="dailySeries" />
        </VWindowItem>

        <VWindowItem>
          <BarChart :categories="monthlyCategories" :series="monthlySeries" />
        </VWindowItem>
      </VWindow>
    </VCardText>
  </VCard>
</template>
