<script setup>
import BarChart from '@core/libs/chartjs/components/BarChart'
import {
  atharGoalTypeBarColors,
  getAtharGoalsTypesChartConfig,
} from '@core/libs/chartjs/chartjsConfig'
import { useTheme } from 'vuetify'

const props = defineProps({
  categories: {
    type: Array,
    default: () => [],
  },
  series: {
    type: Array,
    default: () => [],
  },
})

const vuetifyTheme = useTheme()
const { locale } = useI18n()

const sortedRows = computed(() => {
  const rows = (props.categories || []).map((name, index) => ({
    name: name ?? '',
    count: Number(props.series?.[index]) || 0,
  }))

  return rows.sort((a, b) => b.count - a.count || a.name.localeCompare(b.name, 'ar'))
})

/** Chart.js draws index 0 at the bottom — reverse so highest count appears on top */
const chartRows = computed(() => [...sortedRows.value].reverse())

const hasData = computed(() => {
  if (!chartRows.value.length)
    return false

  return chartRows.value.some(row => row.count > 0)
})

const maxValue = computed(() => {
  if (!chartRows.value.length)
    return 0

  return Math.max(...chartRows.value.map(row => row.count))
})

const chartHeight = computed(() => {
  const rows = Math.max(chartRows.value.length, 1)

  return `${Math.min(360, Math.max(180, rows * 52))}px`
})

const barColors = computed(() => atharGoalTypeBarColors(chartRows.value.length))

const chartData = computed(() => ({
  labels: chartRows.value.map(row => row.name),
  datasets: [{
    data: chartRows.value.map(row => row.count),
    backgroundColor: barColors.value,
    hoverBackgroundColor: barColors.value.map(color => color),
    barThickness: 20,
    maxBarThickness: 24,
    borderRadius: 10,
    borderSkipped: false,
  }],
}))

const totalCount = computed(() => (
  chartRows.value.reduce((sum, row) => sum + row.count, 0)
))

const isRtl = computed(() => locale.value === 'ar')

const valueLabelPlugin = {
  id: 'disabilitiesBarValues',
  afterDatasetsDraw(chart) {
    const { ctx, chartArea } = chart
    const dataset = chart.data.datasets[0]
    const meta = chart.getDatasetMeta(0)

    if (!dataset || !meta?.data?.length)
      return

    const total = dataset.data.reduce((sum, value) => sum + (Number(value) || 0), 0) || 1

    ctx.save()
    ctx.fillStyle = '#123b66'
    ctx.font = '600 13px inherit'

    meta.data.forEach((bar, index) => {
      const value = dataset.data[index]
      if (value == null || value === '')
        return

      const percent = ((Number(value) / total) * 100).toFixed(1)
      const text = `${percent}%`
      const padding = 8
      const { x, y, width } = bar.getProps(['x', 'y', 'width'], true)
      const barEnd = x + (width / 2)
      const labelX = Math.min(barEnd + padding, chartArea.right - 2)

      ctx.textAlign = 'left'
      ctx.textBaseline = 'middle'
      ctx.fillText(text, labelX, y)
    })

    ctx.restore()
  },
}

const chartOptions = computed(() => {
  const base = getAtharGoalsTypesChartConfig(
    vuetifyTheme.current.value,
    maxValue.value ? Math.ceil(maxValue.value * 1.12) : undefined,
  )

  return {
    ...base,
    scales: {
      ...base.scales,
      x: {
        ...base.scales.x,
        grid: {
          ...base.scales.x.grid,
          tickLength: 0,
          color: 'rgba(18, 59, 102, 0.08)',
        },
        ticks: {
          ...base.scales.x.ticks,
          color: '#64748b',
          font: { size: 11, weight: '500' },
          maxTicksLimit: 5,
        },
      },
      y: {
        ...base.scales.y,
        ticks: {
          ...base.scales.y.ticks,
          color: '#123b66',
          font: { size: 13, weight: '600' },
          padding: 8,
          textDirection: isRtl.value ? 'rtl' : 'ltr',
        },
      },
    },
    plugins: {
      ...base.plugins,
      legend: { display: false },
      tooltip: {
        ...base.plugins.tooltip,
        displayColors: true,
        boxPadding: 4,
        callbacks: {
          title(items) {
            return items[0]?.label ?? ''
          },
          labelColor() {
            return {
              borderColor: 'transparent',
              backgroundColor: '#087ed9',
            }
          },
          label(context) {
            const value = Number(context.parsed?.x ?? context.raw ?? 0)
            const total = totalCount.value || 1
            const percent = ((value / total) * 100).toFixed(1)

            return `${value} (${percent}%)`
          },
        },
      },
    },
  }
})

const chartPlugins = [valueLabelPlugin]
</script>

<template>
  <article class="dashboard-chart-card dashboard-chart-card--insight dashboard-disabilities-chart">
    <header class="dashboard-chart-card__head">
      <div class="dashboard-chart-card__title-wrap">
        <span class="dashboard-chart-card__icon">
          <VIcon
            icon="tabler-wheelchair"
            size="20"
          />
        </span>
        <h3 class="dashboard-chart-card__title">
          {{ $t('statistics.disabilities') }}
        </h3>
      </div>
    </header>

    <div
      v-if="hasData"
      class="dashboard-chart-card__body dashboard-disabilities-chart__body"
      :style="{ blockSize: chartHeight }"
    >
      <BarChart
        chart-id="dashboard-disabilities"
        :chart-data="chartData"
        :chart-options="chartOptions"
        :plugins="chartPlugins"
        css-classes="dashboard-chart-card__canvas"
      />
    </div>

    <div
      v-else
      class="dashboard-chart-card__empty dashboard-chart-card__empty--compact"
    >
      <VIcon
        icon="tabler-chart-bar-off"
        size="26"
        class="dashboard-chart-card__empty-icon-inline"
      />
      <p class="dashboard-chart-card__empty-title">
        {{ $t('statistics.chart_no_data') }}
      </p>
    </div>
  </article>
</template>

<style lang="scss">
@import '@/styles/dashboard-chart.scss';
@import '@/styles/dashboard-disabilities-chart.scss';
</style>
