<script setup>
import BarChart from '@core/libs/chartjs/components/BarChart'
import { useTheme } from 'vuetify'
import { getNewestBarChartConfig } from '@core/libs/chartjs/chartjsConfig'

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
const chartOptions = computed(() => getNewestBarChartConfig(vuetifyTheme.current.value))

const chartJsCustomColors = {
  white: '#fff',
  yellow: '#ffe802',
  primary: '#836af9',
  areaChartBlue: '#2c9aff',
  barChartYellow: '#ffcf5c',
  polarChartGrey: '#4f5d70',
  polarChartInfo: '#299aff',
  lineChartYellow: '#d4e157',
  polarChartGreen: '#28dac6',
  lineChartPrimary: '#9e69fd',
  lineChartWarning: '#ff9800',
  horizontalBarInfo: '#26c6da',
  polarChartWarning: '#ff8131',
  scatterChartGreen: '#28c76f',
  warningShade: '#ffbd1f',
  areaChartBlueLight: '#84d0ff',
  areaChartGreyLight: '#edf1f4',
  scatterChartWarning: '#ff9f43',
}

const data = {
  labels: props.categories,
  datasets: [{
    data: props.series,
    maxBarThickness: 40,
    borderRadius: {
      topRight: 5,
      topLeft: 5,
    },
    backgroundColor: [
      '#836af9',
      '#2c9aff',
      '#ffcf5c',
      '#4f5d70',
      '#299aff',
      '#d4e157',
      '#28dac6',
      '#9e69fd',
      '#ff9800',
      '#26c6da',
      '#ff8131',
      '#28c76f',
      '#ffbd1f',
      '#84d0ff',
      '#edf1f4',
      '#ff9f43',
    ],
  }]
};
</script>

<template>
  <BarChart
    :chart-data="data"
    :chart-options="chartOptions"
  />
</template>
