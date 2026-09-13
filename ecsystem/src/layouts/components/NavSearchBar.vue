<script setup>
import { useThemeConfig } from '@core/composable/useThemeConfig'

const { appContentLayoutNav } = useThemeConfig()

defineOptions({ inheritAttrs: false })

const isAppSearchBarVisible = ref(false)

const suggestionGroups = [
  {
    title: 'Popular Searches',
    content: [
      {
        icon: 'tabler-smart-home',
        title: 'Analytics',
        url: { name: 'dashboards-analytics' },
      },
      {
        icon: 'tabler-mood-boy',
        title: 'Cases',
        url: { name: 'cases-list' },
      },
      {
        icon: 'tabler-users',
        title: 'Staff',
        url: { name: 'user-list' },
      },
      {
        icon: 'tabler-users-group',
        title: 'Parents',
        url: { name: 'parents-list' },
      },
    ],
  },
  {
    title: 'Apps & Pages',
    content: [
      {
        icon: 'tabler-building-skyscraper',
        title: 'Centers',
        url: { name: 'centers-list' },
      },
      {
        icon: 'tabler-calendar-check',
        title: 'Attendance',
        url: { name: 'attendances-list' },
      },
      {
        icon: 'tabler-notes',
        title: 'Individual Plan',
        url: { name: 'goals-list' },
      },
      {
        icon: 'tabler-settings',
        title: 'Roles & Permissions',
        url: { name: 'roles-list' },
      },
    ],
  },
]

const noDataSuggestions = [
  {
    title: 'Analytics Dashboard',
    icon: 'tabler-smart-home',
    url: { name: 'dashboards-analytics' },
  },
  {
    title: 'Cases',
    icon: 'tabler-mood-boy',
    url: { name: 'cases-list' },
  },
  {
    title: 'Staff',
    icon: 'tabler-users',
    url: { name: 'user-list' },
  },
]

const searchQuery = ref('')
const searchResult = ref([])
const router = useRouter()

watch(searchQuery, query => {
  if (!query) {
    searchResult.value = []

    return
  }

  const needle = query.toLowerCase()
  searchResult.value = suggestionGroups.flatMap(group => group.content)
    .filter(item => item.title.toLowerCase().includes(needle))
    .map(item => ({ ...item, url: item.url }))
})

const redirectToSuggestedOrSearchedPage = selected => {
  router.push(selected.url)
  isAppSearchBarVisible.value = false
  searchQuery.value = ''
}

const LazyAppBarSearch = defineAsyncComponent(() => import('@core/components/AppBarSearch.vue'))
</script>

<template>
  <div
    class="d-flex align-center cursor-pointer"
    v-bind="$attrs"
    style="user-select: none;"
    @click="isAppSearchBarVisible = !isAppSearchBarVisible"
  >
    <IconBtn class="me-1">
      <VIcon
        size="26"
        icon="tabler-search"
      />
    </IconBtn>

    <span
      v-if="appContentLayoutNav === 'vertical'"
      class="d-none d-md-flex align-center text-disabled"
    >
      <span class="me-3">Search</span>
      <span class="meta-key">&#8984;K</span>
    </span>
  </div>

  <LazyAppBarSearch
    v-model:isDialogVisible="isAppSearchBarVisible"
    v-model:search-query="searchQuery"
    :search-results="searchResult"
    :suggestions="suggestionGroups"
    :no-data-suggestion="noDataSuggestions"
    @item-selected="redirectToSuggestedOrSearchedPage"
  />
</template>

<style lang="scss" scoped>
@use "@styles/variables/_vuetify.scss";

.meta-key {
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  block-size: 1.5625rem;
  line-height: 1.3125rem;
  padding-block: 0.125rem;
  padding-inline: 0.25rem;
}
</style>
