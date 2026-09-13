<script setup>
import VueApexCharts from 'vue3-apexcharts'
import { useTheme } from 'vuetify'
import { hexToRgb } from '@layouts/utils'

const props = defineProps({
  categories: {
    type: Object,
    required: true,
  },
  series: {
    type: Object,
    required: true,
  },
  responsive: {
    type: Object,
    required: false,
    default: []
  },
})

const vuetifyTheme = useTheme()
const currentTab = ref(0)
const refVueApexChart = ref()

const chartConfigs = computed(() => {
  const currentTheme = vuetifyTheme.current.value.colors
  const variableTheme = vuetifyTheme.current.value.variables
  const labelPrimaryColor = `rgba(${ hexToRgb(currentTheme.primary) },${ variableTheme['dragged-opacity'] })`
  const legendColor = `rgba(${ hexToRgb(currentTheme['on-background']) },${ variableTheme['high-emphasis-opacity'] })`
  const borderColor = `rgba(${ hexToRgb(String(variableTheme['border-color'])) },${ variableTheme['border-opacity'] })`
  const labelColor = `rgba(${ hexToRgb(currentTheme['on-surface']) },${ variableTheme['high-emphasis-opacity'] })`
  
  return [
    {
      chartOptions: {
        chart: {
          type: 'bar',
        },
        plotOptions: {
          bar: {
            columnWidth: '32%',
            startingShape: 'rounded',
            borderRadius: 4,
            distributed: true,
            dataLabels: { position: 'top' },
          },
                },
        grid: {
          show: false,
        },
        colors: [
          currentTheme.primary
        ],
        dataLabels: {
          enabled: true,
          offsetY: -35,
          formatter(val) {
            return `${ val }`
          },
        },
        legend: { show: false },
        tooltip: { enabled: false },
        xaxis: {
          categories: props.categories,
          axisTicks: { show: false },
          labels: {
            style: {
              colors: labelColor
            }
          },
        },
        yaxis: {
          labels: {
            formatter(val) {
              return `${ parseInt(val / 1) }`
            },
            style: {
              colors: labelColor
            },
            min: 0,
            max: 60000,
            tickAmount: 6,
          },
        },
        responsive: props.responsive,
      },
      series: [{
        name: 'count',
        data: props.series,
      }],
    },
  ]
})
</script>

<template>
      <VueApexCharts
        ref="refVueApexChart"
        :key="currentTab"
        :options="chartConfigs[Number(currentTab)].chartOptions"
        :series="chartConfigs[Number(currentTab)].series"
        height="240"
        class="mt-3"
      />
</template>
