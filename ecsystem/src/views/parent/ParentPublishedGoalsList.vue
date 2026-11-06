<script setup>
import { goalsApi } from '@/plugins/apis/goalsReqest'

const props = defineProps({
  caseId: {
    type: [Number, String],
    required: true,
  },
  termId: {
    type: [Number, String],
    required: true,
  },
  category: {
    type: String,
    default: 'independent',
  },
  goalRouteName: {
    type: String,
    default: 'independent_goals-sessions-case-term-goal',
  },
  searchQuery: {
    type: String,
    default: '',
  },
})

const goalsReqest = goalsApi()
const goals = ref([])
const loading = ref(true)
const loadError = ref(false)

const fetchGoals = () => {
  loading.value = true
  loadError.value = false

  goalsReqest.fetchAll({
    q: props.searchQuery,
    status: 'active',
    category: props.category,
    case_id: props.caseId,
    term_id: props.termId,
    page: 1,
    options: { page: 1, itemsPerPage: 200, sortBy: [], groupBy: [] },
  }).then(response => {
    goals.value = response.data?.data ?? []
  }).catch(() => {
    goals.value = []
    loadError.value = true
  }).finally(() => {
    loading.value = false
  })
}

watch(
  () => [props.caseId, props.termId, props.searchQuery],
  fetchGoals,
  { immediate: true },
)

const goalLinkParams = goal => ({
  name: props.goalRouteName,
  params: {
    case: props.caseId,
    term: props.termId,
    goal: goal.id,
  },
})
</script>

<template>
  <div class="parent-published-goals">
    <div
      v-if="loading"
      class="parent-page-skeleton pa-6"
    >
      <div
        class="parent-page-skeleton__bar"
        style="block-size: 3rem;"
      />
      <div
        class="parent-page-skeleton__bar"
        style="block-size: 3rem;"
      />
    </div>

    <VAlert
      v-else-if="loadError"
      type="error"
      variant="tonal"
    >
      {{ $t('dashboard.parent_beneficiaries_error') }}
    </VAlert>

    <p
      v-else-if="!goals.length"
      class="text-medium-emphasis text-body-2 mb-0 pa-4"
    >
      {{ $t('independent.parent_no_published_skills') }}
    </p>

    <VList
      v-else
      class="parent-published-goals__list"
      lines="two"
    >
      <VListItem
        v-for="goal in goals"
        :key="goal.id"
        :to="goalLinkParams(goal)"
        link
      >
        <VListItemTitle class="font-weight-medium">
          {{ goal.title }}
        </VListItemTitle>
        <VListItemSubtitle v-if="goal.custom_first_feild">
          {{ goal.custom_first_feild }}
        </VListItemSubtitle>
        <template #append>
          <VIcon
            icon="tabler-chevron-right"
            size="20"
            class="text-medium-emphasis"
          />
        </template>
      </VListItem>
    </VList>
  </div>
</template>

<style lang="scss" scoped>
@import '@styles/parent-experience.scss';

.parent-published-goals__list {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.5rem;
  background: rgb(var(--v-theme-surface));
}
</style>
