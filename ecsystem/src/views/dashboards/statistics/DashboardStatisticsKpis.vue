<script setup>
import DashboardKpiSparkline from '@/views/dashboards/statistics/DashboardKpiSparkline.vue'

const props = defineProps({
  statistics: {
    type: Object,
    required: true,
  },
})

const kpis = computed(() => [
  {
    value: props.statistics?.cases_count,
    labelKey: 'cases_total',
    icon: 'tabler-users',
  },
  {
    value: props.statistics?.teachers_count,
    labelKey: 'teachers_total',
    icon: 'tabler-school',
  },
  {
    value: props.statistics?.specialists_count,
    labelKey: 'specialists_total',
    icon: 'tabler-stethoscope',
  },
  {
    value: props.statistics?.recent_assessments,
    labelKey: 'assessments_total',
    icon: 'tabler-target',
  },
  {
    value: props.statistics?.recent_goals_count,
    labelKey: 'goals_total',
    icon: 'tabler-activity',
  },
])
</script>

<template>
  <div class="dashboard-kpi-grid">
    <article
      v-for="(kpi, index) in kpis"
      :key="index"
      class="dashboard-kpi-card"
    >
      <div class="dashboard-kpi-card__head">
        <div
          class="dashboard-kpi-card__icon"
          aria-hidden="true"
        >
          <VIcon
            :icon="kpi.icon"
            size="21"
          />
        </div>
        <p class="dashboard-kpi-card__label">
          {{ $t(kpi.labelKey) }}
        </p>
      </div>

      <p class="dashboard-kpi-card__value">
        {{ kpi.value ?? '—' }}
      </p>

      <DashboardKpiSparkline
        :spark-key="index"
        class="dashboard-kpi-card__sparkline"
      />
    </article>
  </div>
</template>

<style lang="scss">
@import '@/styles/dashboard-kpi.scss';
</style>
