<script setup>
import { useTheme } from 'vuetify'
import i18n from '@/plugins/i18n/index.js'
import { getNewLineChartConfig } from '@core/libs/chartjs/chartjsConfig'
import LineChart from '@core/libs/chartjs/components/LineChart'

const props = defineProps({
  setOne: {
    type: Object,
    required: true,
  },
  setTwo: {
    type: Object,
    required: true,
  },
})

const vuetifyTheme = useTheme()
const chartConfig = computed(() => getNewLineChartConfig(vuetifyTheme.current.value, true))

const colors = [
  '#836af9', //primary
  '#2c9aff', //areaChartBlue
  '#ffcf5c', //barChartYellow
  '#4f5d70', //polarChartGrey
  '#299aff', //polarChartInfo
  '#ffe802', //yellow
  '#d4e157', //lineChartYellow
  '#28dac6', //polarChartGreen
  '#9e69fd', //lineChartPrimary
  '#ff9800', //lineChartWarning
  '#26c6da', //horizontalBarInfo
  '#ff8131', //polarChartWarning
  '#28c76f', //scatterChartGreen
  '#ffbd1f', //warningShade
  '#84d0ff', //areaChartBlueLight
  '#edf1f4', //areaChartGreyLight
  '#ff9f43', //scatterChartWarning
];

let datasets = [];
let colorIndex = 0;

props.setOne.forEach(term => {
  let termData = [];
  termData.push(term.count);
  props.setTwo.forEach(item => {
    termData.push(term.count);
  });
  datasets.push({
    label: ' '+term.title+' ',
    fill: false,
    tension: 0.5,
    borderColor: colors[colorIndex],
    backgroundColor: '#fff', //white
    pointBackgroundColor: colors[colorIndex],
    pointRadius: 4, // Set the point radius here
    pointHoverRadius: 10,
    pointHoverBorderWidth: 10,
    pointHoverBorderColor: '#fff', //white
    pointHoverBackgroundColor: colors[colorIndex],
    data: termData,
  });
  colorIndex++;
});

let labels = [''];
let periods = [0];
props.setTwo.forEach(item => {
  labels.push(item.title);
  periods.push(item.count);
});
datasets.push({
  label: '  '+i18n.global.t('goals.Periods')+'  ',
  fill: false,
  tension: 0.5,
  borderColor: colors[colorIndex],
  backgroundColor: '#fff', //white
  pointBackgroundColor: colors[colorIndex],
  pointRadius: 4, // Set the point radius here
  pointHoverRadius: 10,
  pointHoverBorderWidth: 10,
  pointHoverBorderColor: '#fff', //white
  pointHoverBackgroundColor: colors[colorIndex],
  data: periods,
})

const data = {
  labels: labels,
  datasets: datasets,
}
</script>

<template>
  <LineChart
    :height="400"
    :chart-options="chartConfig"
    :chart-data="data"
  />
</template>
