<script setup>
import Message from '@/views/messages/Message.vue'
import ParentPageShell from '@/layouts/components/ParentPageShell.vue'
import { centerActivitiesApi } from '@/plugins/apis/centerActivitiesRequest'
import { isParentUser } from '@core/utils/staffSessionVisibility'

const activitiesStore = centerActivitiesApi()
const entries = ref([])
const loading = ref(true)
const loadError = ref(false)

const loadFeed = () => {
  if (!isParentUser()) {
    loading.value = false

    return
  }

  loading.value = true
  loadError.value = false

  activitiesStore.fetchParentFeed({
    page: 1,
    options: { itemsPerPage: 50, page: 1, sortBy: [], groupBy: [] },
  }).then(response => {
    entries.value = response.data?.data ?? []
  }).catch(() => {
    entries.value = []
    loadError.value = true
  }).finally(() => {
    loading.value = false
  })
}

onMounted(loadFeed)
</script>

<template>
  <ParentPageShell
    title-i18n-key="center_activities.parent_title"
    subtitle-i18n-key="center_activities.parent_subtitle"
  >
    <div
      v-if="loading"
      class="parent-page-skeleton"
    >
      <div
        v-for="n in 3"
        :key="n"
        class="parent-page-skeleton__bar"
        style="block-size: 5rem; inline-size: 100%;"
      />
    </div>

    <p
      v-else-if="loadError"
      class="text-medium-emphasis text-center pa-6"
    >
      {{ $t('dashboard.parent_activities_error') }}
    </p>

    <template v-else>
      <div
        v-for="entry in entries"
        :key="entry.id"
        class="mb-4 parent-activity-entry"
      >
        <p
          v-if="entry.activity"
          class="font-weight-bold mb-2"
        >
          {{ entry.activity.title }}
        </p>
        <Message
          :message="entry"
          :can-delete="false"
          is-center-activity-entry
        />
      </div>
      <p
        v-if="!entries.length"
        class="text-center text-medium-emphasis pa-6"
      >
        {{ $t('dashboard.parent_activities_empty') }}
      </p>
    </template>
  </ParentPageShell>
</template>

<style lang="scss" scoped>
@import '@styles/parent-experience.scss';

.parent-activity-entry {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  padding: 1rem;
  background: rgb(var(--v-theme-surface));
}
</style>

<route lang="yaml">
  meta:
    action: parent
    subject: parent
</route>
