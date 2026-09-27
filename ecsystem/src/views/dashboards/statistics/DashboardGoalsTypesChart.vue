<script setup>
import DoughnutChart from '@core/libs/chartjs/components/DoughnutChart'
import {
  atharGoalTypeBarColors,
  getAtharDoughnutChartConfig,
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
const { t } = useI18n()

const rows = computed(() => {
  return (props.categories || []).map((name, index) => ({
    name: name ?? '',
    count: Number(props.series?.[index]) || 0,
  })).sort((a, b) => b.count - a.count || a.name.localeCompare(b.name, 'ar'))
})

const totalCount = computed(() => rows.value.reduce((sum, row) => sum + row.count, 0))

const hasData = computed(() => totalCount.value > 0)

const legendRows = computed(() => {
  const total = totalCount.value || 1

  return rows.value.map((row, index) => ({
    ...row,
    color: atharGoalTypeBarColors(rows.value.length)[index],
    percent: Math.round((row.count / total) * 100),
  }))
})

const chartData = computed(() => ({
  labels: rows.value.map(row => row.name),
  datasets: [{
    data: rows.value.map(row => row.count),
    backgroundColor: atharGoalTypeBarColors(rows.value.length),
    borderWidth: 2,
    borderColor: '#fff',
    hoverBorderColor: '#fff',
  }],
}))

const chartOptions = computed(() => getAtharDoughnutChartConfig(vuetifyTheme.current.value))
</script>

<template>
  <article class="dashboard-chart-card dashboard-chart-card--insight dashboard-goals-types">
    <header class="dashboard-chart-card__head">
      <div class="dashboard-chart-card__title-wrap">
        <span class="dashboard-chart-card__icon">
          <VIcon
            icon="tabler-chart-donut-3"
            size="20"
          />
        </span>
        <h3 class="dashboard-chart-card__title">
          {{ $t('statistics.goals_types') }}
        </h3>
      </div>
    </header>

    <div
      v-if="hasData"
      class="dashboard-chart-card__body dashboard-goals-types__body"
    >
      <ul
        class="dashboard-goals-types__legend"
        role="list"
      >
        <li
          v-for="item in legendRows"
          :key="item.name"
          class="dashboard-goals-types__legend-item"
        >
          <span
            class="dashboard-goals-types__legend-dot"
            :style="{ backgroundColor: item.color }"
          />
          <span class="dashboard-goals-types__legend-name">{{ item.name }}</span>
          <span class="dashboard-goals-types__legend-percent">{{ item.percent }}%</span>
          <span class="dashboard-goals-types__legend-count">{{ item.count }}</span>
        </li>
      </ul>

      <div class="dashboard-goals-types__chart">
        <DoughnutChart
          chart-id="dashboard-goals-types-donut"
          :chart-data="chartData"
          :chart-options="chartOptions"
          css-classes="dashboard-goals-types__canvas"
        />
        <div
          class="dashboard-goals-types__center"
          aria-hidden="true"
        >
          <strong class="dashboard-goals-types__center-value">{{ totalCount }}</strong>
          <span class="dashboard-goals-types__center-label">{{ t('statistics.goals_types_total_label') }}</span>
        </div>
      </div>
    </div>

    <div
      v-else
      class="dashboard-goals-types__empty"
    >
      <span class="dashboard-goals-types__empty-icon">
        <VIcon
          icon="tabler-target-off"
          size="26"
        />
      </span>
      <p class="dashboard-chart-card__empty-title">
        {{ $t('statistics.goals_types_empty') }}
      </p>
    </div>
  </article>
</template>

<style lang="scss">
@import '@/styles/dashboard-chart.scss';

.dashboard-goals-types {
  display: flex;
  flex-direction: column;
  block-size: 100%;
  min-block-size: 0;
}

.dashboard-goals-types__body {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
  align-items: center;
  flex: 1 1 auto;
  min-block-size: 0;
  padding: 0.85rem 1rem 1.1rem;

  @media (min-width: 600px) {
    grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
    gap: 0.75rem 1rem;
  }
}

.dashboard-goals-types__legend {
  display: flex;
  flex-direction: column;
  gap: 0.55rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.dashboard-goals-types__legend-item {
  display: grid;
  grid-template-columns: auto 1fr auto auto;
  align-items: center;
  gap: 0.45rem 0.5rem;
  font-size: 0.8125rem;
}

.dashboard-goals-types__legend-dot {
  inline-size: 0.5rem;
  block-size: 0.5rem;
  border-radius: 50%;
}

.dashboard-goals-types__legend-name {
  overflow: hidden;
  font-weight: 600;
  color: #123b66;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dashboard-goals-types__legend-percent {
  font-weight: 700;
  color: #087ed9;
  font-variant-numeric: tabular-nums;
}

.dashboard-goals-types__legend-count {
  font-weight: 600;
  color: #64748b;
  font-variant-numeric: tabular-nums;
}

.dashboard-goals-types__chart {
  position: relative;
  block-size: 11.5rem;
  min-block-size: 11.5rem;
}

.dashboard-goals-types__canvas {
  block-size: 100% !important;
  inline-size: 100% !important;
}

.dashboard-goals-types__center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.15rem;
  pointer-events: none;
}

.dashboard-goals-types__center-value {
  font-size: 1.375rem;
  font-weight: 800;
  line-height: 1.1;
  color: #0b2d4d;
  font-variant-numeric: tabular-nums;
}

.dashboard-goals-types__center-label {
  font-size: 0.6875rem;
  font-weight: 600;
  color: #64748b;
}

.dashboard-goals-types__empty {
  display: flex;
  flex: 1 1 auto;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-block-size: 0;
  padding: 1rem 1.25rem;
  text-align: center;

  .dashboard-chart-card__empty-title {
    margin: 0;
    max-inline-size: 16rem;
    font-size: 0.875rem;
    line-height: 1.55;
    color: #64748b;
  }
}

.dashboard-goals-types__empty-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  inline-size: 2.75rem;
  block-size: 2.75rem;
  margin-block-end: 0.25rem;
  border-radius: 12px;
  background: #eaf5ff;
  color: rgba(#087ed9, 0.65);
}
</style>
