<script setup>
import BarChart from '@core/libs/chartjs/components/BarChart'
import LineChart from '@core/libs/chartjs/components/LineChart'
import {
  atharGoalTypeBarColors,
  getAtharAreaLineChartConfig,
  getAtharMonthlyBarChartConfig,
} from '@core/libs/chartjs/chartjsConfig'
import i18n from '@/plugins/i18n/index.js'
import { useTheme } from 'vuetify'

const props = defineProps({
  daily: {
    type: Array,
    default: () => [],
  },
  monthly: {
    type: Array,
    default: () => [],
  },
  title: {
    type: String,
    default: 'daily_goals',
  },
  /** Distinguishes chart instance id + header icon only; visuals are shared. */
  chartSet: {
    type: String,
    default: 'activities',
    validator: value => ['activities', 'attendance'].includes(value),
  },
})

const { t } = useI18n()
const vuetifyTheme = useTheme()
const currentActiveTab = ref(0)

const isAttendance = computed(() => props.chartSet === 'attendance')

const headerIcon = computed(() => (
  isAttendance.value ? 'tabler-calendar-check' : 'tabler-activity'
))

function dailyAreaFillGradient(context) {
  const chart = context.chart
  const { ctx, chartArea } = chart

  if (!chartArea)
    return 'rgba(8, 126, 217, 0.16)'

  const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)

  gradient.addColorStop(0, 'rgba(8, 126, 217, 0.32)')
  gradient.addColorStop(0.55, 'rgba(8, 126, 217, 0.1)')
  gradient.addColorStop(1, 'rgba(8, 126, 217, 0.02)')

  return gradient
}

const dailyLineDataset = {
  fill: true,
  tension: 0.38,
  borderWidth: 2.5,
  borderColor: '#087ed9',
  backgroundColor: dailyAreaFillGradient,
  pointRadius: 0,
  pointHitRadius: 12,
  pointHoverRadius: 5,
  pointBackgroundColor: '#087ed9',
  pointBorderColor: '#fff',
  pointBorderWidth: 2,
  pointHoverBackgroundColor: '#087ed9',
  pointHoverBorderColor: '#fff',
}

function formatDailyLabel(item) {
  const locale = i18n.global.locale.value === 'ar' ? 'ar-SA' : 'en-US'
  const date = new Date(Number(item.year), Number(item.month) - 1, Number(item.day))

  return new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short' }).format(date)
}

function formatMonthlyLabel(item) {
  const locale = i18n.global.locale.value === 'ar' ? 'ar-SA' : 'en-US'
  const date = new Date(Number(item.year), Number(item.month) - 1, 1)

  return new Intl.DateTimeFormat(locale, { month: 'short', year: 'numeric' }).format(date)
}

const dailyCategories = computed(() => (props.daily || []).map(formatDailyLabel))
const dailySeries = computed(() => (props.daily || []).map(item => Number(item.count) || 0))

const monthlyCategories = computed(() => (props.monthly || []).map(formatMonthlyLabel))
const monthlySeries = computed(() => (props.monthly || []).map(item => Number(item.count) || 0))

const hasDailyData = computed(() => dailySeries.value.some(value => value > 0))
const hasMonthlyData = computed(() => monthlySeries.value.some(value => value > 0))

const dailyMax = computed(() => Math.max(0, ...dailySeries.value))
const monthlyMax = computed(() => Math.max(0, ...monthlySeries.value))

const dailyChartData = computed(() => ({
  labels: dailyCategories.value,
  datasets: [{
    ...dailyLineDataset,
    data: dailySeries.value,
  }],
}))

const monthlyChartData = computed(() => {
  const barColors = atharGoalTypeBarColors(monthlySeries.value.length)

  return {
    labels: monthlyCategories.value,
    datasets: [{
      data: monthlySeries.value,
      backgroundColor: barColors,
      hoverBackgroundColor: barColors,
      borderRadius: 10,
      borderSkipped: false,
      maxBarThickness: 34,
    }],
  }
})

function mergeChartPresentation(baseOptions, { isBar = false } = {}) {
  const isRtl = i18n.global.locale.value === 'ar'

  return {
    ...baseOptions,
    scales: {
      ...baseOptions.scales,
      x: {
        ...baseOptions.scales?.x,
        ticks: {
          ...baseOptions.scales?.x?.ticks,
          color: '#64748b',
          font: { size: 11, weight: '500' },
          ...(isRtl ? { textDirection: 'rtl' } : {}),
        },
        grid: isBar
          ? {
              ...baseOptions.scales?.x?.grid,
              display: false,
              drawBorder: false,
            }
          : {
              ...baseOptions.scales?.x?.grid,
              tickLength: 0,
              color: 'rgba(18, 59, 102, 0.08)',
            },
      },
      y: {
        ...baseOptions.scales?.y,
        ticks: {
          ...baseOptions.scales?.y?.ticks,
          color: '#64748b',
          font: { size: 11, weight: '500' },
          maxTicksLimit: 5,
        },
        grid: {
          ...baseOptions.scales?.y?.grid,
          tickLength: 0,
          color: 'rgba(18, 59, 102, 0.08)',
        },
      },
    },
    plugins: {
      ...baseOptions.plugins,
      legend: { display: false },
      tooltip: {
        backgroundColor: '#0b2d4d',
        titleFont: { size: 12, weight: '600' },
        bodyFont: { size: 13, weight: '500' },
        padding: 10,
        cornerRadius: 8,
        displayColors: !isBar,
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
            const value = context.parsed?.y ?? context.raw

            return String(value ?? '')
          },
        },
      },
    },
  }
}

const dailyChartOptions = computed(() => {
  const suggestedMax = dailyMax.value ? Math.ceil(dailyMax.value * 1.2) : undefined
  const base = getAtharAreaLineChartConfig(vuetifyTheme.current.value, suggestedMax)

  return mergeChartPresentation(base, { isBar: false })
})

const monthlyChartOptions = computed(() => {
  const suggestedMax = monthlyMax.value ? Math.ceil(monthlyMax.value * 1.15) : undefined
  const base = getAtharMonthlyBarChartConfig(vuetifyTheme.current.value, suggestedMax)

  return mergeChartPresentation(base, { isBar: true })
})

const chartIdPrefix = computed(() => `dashboard-${props.chartSet}`)
</script>

<template>
  <article class="dashboard-chart-card dashboard-chart-card--tabs dashboard-chart-card--timeseries">
    <header class="dashboard-chart-card__head dashboard-chart-card__head--tabs">
      <div class="dashboard-chart-card__title-wrap">
        <span class="dashboard-chart-card__icon">
          <VIcon
            :icon="headerIcon"
            size="20"
          />
        </span>
        <h3 class="dashboard-chart-card__title">
          {{ $t(`statistics.${title}`) }}
        </h3>
      </div>

      <div
        class="dashboard-chart-tabs dashboard-chart-tabs--segmented"
        role="tablist"
        :aria-label="$t(`statistics.${title}`)"
      >
        <button
          type="button"
          role="tab"
          class="dashboard-chart-tabs__btn"
          :class="{ 'dashboard-chart-tabs__btn--active': currentActiveTab === 0 }"
          :aria-selected="currentActiveTab === 0"
          @click="currentActiveTab = 0"
        >
          <VIcon
            icon="tabler-chart-area-line"
            size="16"
            class="dashboard-chart-tabs__btn-icon"
          />
          {{ t('statistics.period_daily') }}
        </button>
        <button
          type="button"
          role="tab"
          class="dashboard-chart-tabs__btn"
          :class="{ 'dashboard-chart-tabs__btn--active': currentActiveTab === 1 }"
          :aria-selected="currentActiveTab === 1"
          @click="currentActiveTab = 1"
        >
          <VIcon
            icon="tabler-chart-bar"
            size="16"
            class="dashboard-chart-tabs__btn-icon"
          />
          {{ t('statistics.period_monthly') }}
        </button>
      </div>
    </header>

    <div class="dashboard-chart-card__body dashboard-chart-card__body--fixed">
      <div
        v-show="currentActiveTab === 0"
        class="dashboard-chart-panel"
        role="tabpanel"
      >
        <div
          v-if="hasDailyData"
          class="dashboard-chart-panel__canvas"
        >
          <LineChart
            :chart-id="`${chartIdPrefix}-daily`"
            :chart-data="dailyChartData"
            :chart-options="dailyChartOptions"
            css-classes="dashboard-chart-card__canvas"
          />
        </div>
        <div
          v-else
          class="dashboard-chart-card__empty dashboard-chart-card__empty--compact"
        >
          <VIcon
            icon="tabler-chart-area-line"
            size="26"
            class="dashboard-chart-card__empty-icon-inline"
          />
          <p class="dashboard-chart-card__empty-title">
            {{ t('statistics.chart_no_data') }}
          </p>
        </div>
      </div>

      <div
        v-show="currentActiveTab === 1"
        class="dashboard-chart-panel"
        role="tabpanel"
      >
        <div
          v-if="hasMonthlyData"
          class="dashboard-chart-panel__canvas"
        >
          <BarChart
            :chart-id="`${chartIdPrefix}-monthly`"
            :chart-data="monthlyChartData"
            :chart-options="monthlyChartOptions"
            css-classes="dashboard-chart-card__canvas"
          />
        </div>
        <div
          v-else
          class="dashboard-chart-card__empty dashboard-chart-card__empty--compact"
        >
          <VIcon
            icon="tabler-chart-bar"
            size="26"
            class="dashboard-chart-card__empty-icon-inline"
          />
          <p class="dashboard-chart-card__empty-title">
            {{ t('statistics.chart_no_data') }}
          </p>
        </div>
      </div>
    </div>
  </article>
</template>

<style lang="scss">
@import '@/styles/dashboard-chart.scss';

.dashboard-chart-card--timeseries {
  .dashboard-chart-card__icon {
    background: #eaf5ff;
    color: #087ed9;
  }
}
</style>
