<script setup>
/** Fixed decorative sparklines — not tied to KPI data */
const props = defineProps({
  sparkKey: {
    type: [String, Number],
    default: 0,
  },
})

const fillGradientId = computed(() => `dashboard-kpi-sparkline-fill-${props.sparkKey}`)

const SPARK_WIDTH = 100
const SPARK_HEIGHT = 32

const DECORATIVE_PATTERNS = Object.freeze([
  // 1. Soft Wave
  [
    { x: 0, y: 21 },
    { x: 12, y: 17 },
    { x: 24, y: 20 },
    { x: 36, y: 14 },
    { x: 48, y: 18 },
    { x: 60, y: 12 },
    { x: 72, y: 16 },
    { x: 84, y: 10 },
    { x: 100, y: 14 },
  ],

  // 2. Mountain Peaks
  [
    { x: 0, y: 25 },
    { x: 15, y: 24 },
    { x: 28, y: 8 },
    { x: 40, y: 23 },
    { x: 58, y: 18 },
    { x: 72, y: 5 },
    { x: 84, y: 22 },
    { x: 100, y: 18 },
  ],

  // 3. Double Wave
  [
    { x: 0, y: 20 },
    { x: 12, y: 16 },
    { x: 24, y: 11 },
    { x: 36, y: 15 },
    { x: 48, y: 22 },
    { x: 60, y: 24 },
    { x: 72, y: 17 },
    { x: 84, y: 10 },
    { x: 92, y: 13 },
    { x: 100, y: 18 },
  ],

  // 4. Smooth Valley
  [
    { x: 0, y: 10 },
    { x: 15, y: 15 },
    { x: 30, y: 24 },
    { x: 45, y: 27 },
    { x: 60, y: 22 },
    { x: 75, y: 14 },
    { x: 88, y: 10 },
    { x: 100, y: 13 },
  ],

  // 5. Pulse
  [
    { x: 0, y: 20 },
    { x: 18, y: 20 },
    { x: 30, y: 7 },
    { x: 38, y: 24 },
    { x: 48, y: 20 },
    { x: 62, y: 20 },
    { x: 74, y: 6 },
    { x: 82, y: 23 },
    { x: 100, y: 20 },
  ],
])

function geometryFromCoords(coords) {
  const line = coords.map(({ x, y }) => `${x},${y}`).join(' ')
  const area = [
    `${coords[0].x},${SPARK_HEIGHT}`,
    ...coords.map(({ x, y }) => `${x},${y}`),
    `${coords[coords.length - 1].x},${SPARK_HEIGHT}`,
  ].join(' ')

  return { line, area }
}

const decorativeGeometry = computed(() => {
  const index = Math.abs(Number(props.sparkKey) || 0) % DECORATIVE_PATTERNS.length
  const coords = DECORATIVE_PATTERNS[index]

  return geometryFromCoords(coords)
})
</script>

<template>
  <svg
    class="dashboard-kpi-sparkline"
    :viewBox="`0 0 ${SPARK_WIDTH} ${SPARK_HEIGHT}`"
    preserveAspectRatio="none"
    aria-hidden="true"
  >
    <defs>
      <linearGradient
        :id="fillGradientId"
        x1="0"
        y1="0"
        x2="0"
        y2="1"
      >
        <stop
          offset="0%"
          stop-color="currentColor"
          stop-opacity="0.22"
        />
        <stop
          offset="100%"
          stop-color="currentColor"
          stop-opacity="0.02"
        />
      </linearGradient>
    </defs>

    <polygon
      :fill="`url(#${fillGradientId})`"
      :points="decorativeGeometry.area"
    />
    <polyline
      fill="none"
      stroke="currentColor"
      stroke-width="1.75"
      stroke-linecap="round"
      stroke-linejoin="round"
      vector-effect="non-scaling-stroke"
      :points="decorativeGeometry.line"
    />
  </svg>
</template>

<style lang="scss">
.dashboard-kpi-sparkline {
  display: block;
  inline-size: 100%;
  block-size: 2rem;
  color: #087ed9;
}
</style>
