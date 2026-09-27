<script setup>
import Message from '@/views/messages/Message.vue'
import { centerActivitiesApi } from '@/plugins/apis/centerActivitiesRequest'
import { isParentUser } from '@core/utils/staffSessionVisibility'

const activitiesStore = centerActivitiesApi()
const entries = ref([])

if (isParentUser()) {
  activitiesStore.fetchParentFeed({ page: 1, options: { itemsPerPage: 50 } }).then(response => {
    entries.value = response.data.data || []
  })
}
</script>

<template>
  <VRow>
    <VCol cols="12">
      <h4 class="text-h4 mb-4">
        {{ $t('center_activities.parent_title') }}
      </h4>
      <div
        v-for="entry in entries"
        :key="entry.id"
        class="mb-4"
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
        {{ $t('No data available') }}
      </p>
    </VCol>
  </VRow>
</template>

<route lang="yaml">
  meta:
    action: parent
    subject: parent
</route>
