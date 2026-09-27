<script setup>
import { avatarText } from '@core/utils/formatters'

const props = defineProps({
  category: {
    type: String,
    required: true,
  },
  categories: {
    type: Array,
    default: () => [],
  },
  rows: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:category'])

const { t } = useI18n()

const searchQuery = ref('')
const showAll = ref(false)

const DEFAULT_VISIBLE = 5
const SMALL_DATASET_MAX = 8

const visibleLimit = ref(DEFAULT_VISIBLE)

watch(() => props.category, () => {
  searchQuery.value = ''
  showAll.value = false
  visibleLimit.value = DEFAULT_VISIBLE
})

watch(() => props.rows, () => {
  showAll.value = false
  visibleLimit.value = DEFAULT_VISIBLE
})

const sortedRows = computed(() => {
  return [...(props.rows || [])]
    .map(row => ({
      id: row.id,
      name: row.name ?? '',
      count: Number(row.count) || 0,
    }))
    .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name, 'ar'))
})

const filteredRows = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  if (!q)
    return sortedRows.value

  return sortedRows.value.filter(row => row.name.toLowerCase().includes(q))
})

const isSmallDataset = computed(() => filteredRows.value.length <= SMALL_DATASET_MAX)

const displayedRows = computed(() => {
  if (showAll.value || isSmallDataset.value || searchQuery.value.trim())
    return filteredRows.value

  return filteredRows.value.slice(0, visibleLimit.value)
})

const maxCount = computed(() => {
  if (!filteredRows.value.length)
    return 0

  return Math.max(...filteredRows.value.map(row => row.count))
})

const hasMore = computed(() => (
  !isSmallDataset.value
  && !showAll.value
  && !searchQuery.value.trim()
  && filteredRows.value.length > visibleLimit.value
))

const remainingCount = computed(() => (
  Math.max(0, filteredRows.value.length - visibleLimit.value)
))

const showAsidePanel = computed(() => hasMore.value)

const TAB_DISPLAY_ORDER = ['administration', 'cases', 'all']

const displayCategories = computed(() => {
  const items = [...(props.categories || [])]

  return items.sort((a, b) => {
    const indexA = TAB_DISPLAY_ORDER.indexOf(a.category)
    const indexB = TAB_DISPLAY_ORDER.indexOf(b.category)

    return (indexA === -1 ? 99 : indexA) - (indexB === -1 ? 99 : indexB)
  })
})

const rankByRow = computed(() => {
  const map = new Map()

  filteredRows.value.forEach((row, index) => {
    map.set(`${row.name}::${row.count}`, index + 1)
  })

  return map
})

function selectCategory(value) {
  emit('update:category', value)
}

function showAllStaff() {
  showAll.value = true
}

function barWidth(count) {
  if (!maxCount.value || !count)
    return '0%'

  return `${Math.max(4, (count / maxCount.value) * 100)}%`
}

function rowRank(row) {
  return rankByRow.value.get(`${row.name}::${row.count}`) ?? 0
}
</script>

<template>
  <article class="dashboard-chart-card dashboard-staff-activity">
    <header class="dashboard-staff-activity__header">
      <div class="dashboard-staff-activity__title">
        <div class="dashboard-chart-card__title-wrap">
          <span class="dashboard-chart-card__icon dashboard-chart-card__icon--navy">
            <VIcon
              icon="tabler-users-group"
              size="20"
            />
          </span>
          <h3 class="dashboard-chart-card__title">
            {{ t('statistics.recent_logs') }}
          </h3>
        </div>
      </div>

      <div class="dashboard-staff-activity__search-wrap">
        <AppTextField
          v-model="searchQuery"
          density="compact"
          prepend-inner-icon="tabler-search"
          :placeholder="t('statistics.staff_activity_search')"
          hide-details
          clearable
          class="dashboard-staff-activity__search"
        />
      </div>

      <div
        v-if="displayCategories.length"
        class="dashboard-chart-tabs dashboard-chart-tabs--segmented dashboard-staff-activity__tabs"
        role="tablist"
        :aria-label="t('statistics.recent_logs')"
      >
        <button
          v-for="item in displayCategories"
          :key="item.category"
          type="button"
          role="tab"
          class="dashboard-chart-tabs__btn"
          :class="{ 'dashboard-chart-tabs__btn--active': category === item.category }"
          :aria-selected="category === item.category"
          @click="selectCategory(item.category)"
        >
          {{ t(`statistics.${item.category}`) }}
        </button>
      </div>
    </header>

    <div
      class="dashboard-staff-activity__body"
      :class="{
        'dashboard-staff-activity__body--compact': isSmallDataset,
        'dashboard-staff-activity__body--expanded': showAll,
      }"
    >
      <div
        v-if="!filteredRows.length"
        class="dashboard-staff-activity__empty"
      >
        <span class="dashboard-staff-activity__empty-icon">
          <VIcon
            icon="tabler-user-search"
            size="26"
          />
        </span>
        <p class="dashboard-chart-card__empty-title">
          {{ searchQuery.trim() ? t('statistics.staff_activity_no_results') : t('statistics.chart_no_data') }}
        </p>
      </div>

      <div
        v-else
        class="dashboard-staff-activity__content"
        :class="{ 'dashboard-staff-activity__content--with-aside': showAsidePanel }"
      >
        <ul
          class="dashboard-staff-activity__list"
          role="list"
        >
          <li
            v-for="(row, index) in displayedRows"
            :key="`${row.id ?? row.name}-${index}`"
            class="dashboard-staff-activity__row"
            :class="{ 'dashboard-staff-activity__row--compact': isSmallDataset }"
          >
            <span
              class="dashboard-staff-activity__rank"
              aria-hidden="true"
            >
              {{ rowRank(row) }}
            </span>

            <VAvatar
              size="36"
              color="primary"
              variant="tonal"
              class="dashboard-staff-activity__avatar"
            >
              <span class="text-caption font-weight-bold">{{ avatarText(row.name) }}</span>
            </VAvatar>

            <div class="dashboard-staff-activity__main">
              <div class="dashboard-staff-activity__label-row">
                <span class="dashboard-staff-activity__name">{{ row.name }}</span>
                <span class="dashboard-staff-activity__count">{{ row.count }}</span>
              </div>
              <div
                class="dashboard-staff-activity__track"
                role="presentation"
              >
                <div
                  class="dashboard-staff-activity__bar"
                  :style="{ inlineSize: barWidth(row.count) }"
                />
              </div>
            </div>
          </li>
        </ul>

        <aside
          v-if="showAsidePanel"
          class="dashboard-staff-activity__aside"
        >
          <span class="dashboard-staff-activity__aside-icon">
            <VIcon
              icon="tabler-users"
              size="32"
            />
          </span>
          <p class="dashboard-staff-activity__aside-title">
            {{ t('statistics.staff_activity_others', { count: remainingCount }) }}
          </p>
          <VBtn
            color="primary"
            size="small"
            @click="showAllStaff"
          >
            {{ t('statistics.staff_activity_show_all') }}
            <VIcon
              end
              icon="tabler-arrow-left"
              size="16"
            />
          </VBtn>
        </aside>
      </div>

    </div>
  </article>
</template>

<style lang="scss">
@import '@/styles/dashboard-chart.scss';
@import '@/styles/dashboard-staff-activity.scss';
</style>
