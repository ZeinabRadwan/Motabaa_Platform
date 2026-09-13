<script setup>
import { useTheme } from 'vuetify'
import { getNewLineChartConfig } from '@core/libs/chartjs/chartjsConfig'
import LineChart from '@core/libs/chartjs/components/LineChart'

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
const chartConfig = computed(() => getNewLineChartConfig(vuetifyTheme.current.value))

const colors = {
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
  datasets: [
    {
      fill: false,
      tension: 0.5,
      pointHoverRadius: 10,
      borderColor: colors.primary,
      backgroundColor: colors.primary,
      pointHoverBorderWidth: 10,
      pointHoverBorderColor: colors.white,
      pointHoverBackgroundColor: colors.primary,
      data: props.series,
    },
  ],
}
</script>

<template>
  <LineChart
    :height="400"
    :chart-options="chartConfig"
    :chart-data="data"
  />
</template>
