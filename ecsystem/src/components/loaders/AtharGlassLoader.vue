<script setup>
import atharLogo from '@images/athar.png'

const gradientId = `athar-loader-track-gradient-${Math.random().toString(36).slice(2, 9)}`

defineProps({
  fullScreen: {
    type: Boolean,
    default: true,
  },
  logoSrc: {
    type: String,
    default: atharLogo,
  },
  logoAlt: {
    type: String,
    default: 'Athar',
  },
  ariaLabel: {
    type: String,
    default: 'Loading',
  },
})
</script>

<template>
  <div
    class="athar-glass-loader"
    :class="{ 'athar-glass-loader--inline': !fullScreen }"
    role="status"
    aria-live="polite"
    :aria-label="ariaLabel"
  >
    <div class="athar-glass-loader__stage">
      <div
        class="athar-glass-loader__orbit"
        aria-hidden="true"
      >
        <svg
          class="athar-glass-loader__svg"
          viewBox="0 0 200 200"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <defs>
            <linearGradient
              :id="gradientId"
              x1="24"
              y1="24"
              x2="176"
              y2="176"
              gradientUnits="userSpaceOnUse"
            >
              <stop stop-color="var(--athar-loader-blue-deep, #024282)" />
              <stop
                offset="1"
                stop-color="var(--athar-loader-blue-bright, #00A3FF)"
              />
            </linearGradient>
          </defs>
          <circle
            class="athar-glass-loader__ring athar-glass-loader__ring--outer"
            cx="100"
            cy="100"
            r="96"
          />
          <circle
            class="athar-glass-loader__ring athar-glass-loader__ring--inner"
            cx="100"
            cy="100"
            r="88"
          />
          <circle
            class="athar-glass-loader__ticks athar-glass-loader__ticks--inactive"
            cx="100"
            cy="100"
            r="92"
          />
          <g class="athar-glass-loader__ticks-spin">
            <circle
              class="athar-glass-loader__ticks athar-glass-loader__ticks--active"
              cx="100"
              cy="100"
              r="92"
              :stroke="`url(#${gradientId})`"
            />
          </g>
        </svg>
      </div>

      <div class="athar-glass-loader__glass">
        <img
          :src="logoSrc"
          :alt="logoAlt"
          class="athar-glass-loader__logo"
          width="136"
          height="136"
          decoding="async"
        >
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import '@/styles/athar-glass-loader.scss';
</style>
