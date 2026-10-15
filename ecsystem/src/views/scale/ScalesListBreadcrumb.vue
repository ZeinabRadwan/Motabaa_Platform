<script setup>
defineProps({
  steps: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['home', 'step'])
</script>

<template>
  <nav
    class="scales-list-breadcrumb"
    :aria-label="$t('scales.breadcrumb')"
  >
    <button
      v-if="steps.length"
      type="button"
      class="scales-list-breadcrumb__home"
      @click="emit('home')"
    >
      <VIcon
        icon="tabler-home"
        size="16"
      />
      {{ $t('scales.all_scales') }}
    </button>
    <span
      v-else
      class="scales-list-breadcrumb__home"
      style="cursor: default;"
    >
      <VIcon
        icon="tabler-layout-grid"
        size="16"
      />
      {{ $t('scales.all_scales') }}
    </span>
    <template
      v-for="step in steps"
      :key="step.id"
    >
      <VIcon
        class="scales-list-breadcrumb__sep"
        icon="tabler-chevron-left"
        size="16"
      />
      <button
        type="button"
        class="scales-list-breadcrumb__step"
        @click="emit('step', step)"
      >
        {{ step.title }}
      </button>
    </template>
  </nav>
</template>
