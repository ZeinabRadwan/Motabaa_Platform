<script setup>
import VueApexCharts from 'vue3-apexcharts'
import { useTheme } from 'vuetify'
import { getNewLineChartSimpleConfig } from '@core/libs/apex-chart/apexCharConfig'

const props = defineProps({
  categories: {
    type: Object,
    required: true,
  },
  series: {
    type: Object,
    required: true,
  },
})

const vuetifyTheme = useTheme()
const balanceChartConfig = computed(() => getNewLineChartSimpleConfig(vuetifyTheme.current.value, props.categories))

const series = [{
  data: props.series,
}]
</script>

<template>
  <VueApexCharts
    type="line"
    height="400"
    :options="balanceChartConfig"
    :series="series"
  />
</template>
