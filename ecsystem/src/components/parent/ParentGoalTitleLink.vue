<script setup>
const props = defineProps({
  goal: {
    type: Object,
    required: true,
  },

  /** Route location of the details view; when null the title renders as plain text. */
  to: {
    type: Object,
    default: null,
  },
})

const { t } = useI18n()

const ariaLabel = computed(() => t('parent_goal_details.open_goal_aria', { title: props.goal.title ?? '' }))
</script>

<template>
  <RouterLink
    v-if="to"
    :to="to"
    class="parent-goal-title-link"
    :aria-label="ariaLabel"
  >
    <span>{{ goal.title }}</span>
    <span v-if="goal.standard"> {{ $t(goal.standard) }}</span>
    <span v-if="goal.generalization"> {{ $t(goal.generalization) }}</span>
  </RouterLink>
  <span v-else>
    <span>{{ goal.title }}</span>
    <span v-if="goal.standard"> {{ $t(goal.standard) }}</span>
    <span v-if="goal.generalization"> {{ $t(goal.generalization) }}</span>
  </span>
</template>

<style lang="scss" scoped>
.parent-goal-title-link {
  display: inline;
  border-radius: 0.25rem;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-weight: 500;
  cursor: pointer;
  text-decoration: none;
  text-decoration-color: transparent;
  text-underline-offset: 0.2em;
  transition: color 0.15s ease, text-decoration-color 0.15s ease;

  &:hover,
  &:focus-visible {
    color: rgb(var(--v-theme-primary));
    text-decoration: underline;
    text-decoration-color: rgba(var(--v-theme-primary), 0.45);
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
}
</style>
