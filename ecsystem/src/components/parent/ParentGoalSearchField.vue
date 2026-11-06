<script setup>
const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },

  /** Goals loaded for the current filters. */
  total: {
    type: Number,
    default: 0,
  },

  /** Goals left after the search. */
  shown: {
    type: Number,
    default: 0,
  },
})

const emit = defineEmits(['update:modelValue'])

const query = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value ?? ''),
})

const searching = computed(() => !!String(props.modelValue || '').trim())
</script>

<template>
  <div class="parent-goal-search">
    <AppTextField
      v-model="query"
      type="search"
      :label="$t('parent_goal_search.label')"
      :placeholder="$t('parent_goal_search.placeholder')"
      prepend-inner-icon="tabler-search"
      clearable
      clear-icon="tabler-x"
      enterkeyhint="search"
      :aria-label="$t('parent_goal_search.label')"
    />
    <p
      v-if="searching && total"
      class="text-body-2 text-medium-emphasis mt-2 mb-0"
      aria-live="polite"
    >
      {{ $t('parent_goal_search.results', { shown, total }) }}
    </p>
  </div>
</template>
