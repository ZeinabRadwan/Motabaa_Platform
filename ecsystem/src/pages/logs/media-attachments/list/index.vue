<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { logsApi } from '@/plugins/apis/logsRequest'
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import moment from '@/plugins/moment'

const logsListStore = logsApi()
const userListStore = useUserListStore()

const searchQuery = ref('')
const filterOperation = ref(null)
const filterOutcome = ref(null)
const filterUserId = ref(null)

const rows = ref([])
const totalPage = ref(1)
const totalRows = ref(0)

const options = ref({
  page: 1,
  itemsPerPage: 25,
  sortBy: [],
  groupBy: [],
})

const operationItems = computed(() => [
  { title: i18n.global.t('media_attachment_audit.operation_upload'), value: 'upload' },
  { title: i18n.global.t('media_attachment_audit.operation_delete'), value: 'delete' },
])

const outcomeItems = computed(() => [
  { title: i18n.global.t('media_attachment_audit.outcome_attempt'), value: 'attempt' },
  { title: i18n.global.t('media_attachment_audit.outcome_success'), value: 'success' },
  { title: i18n.global.t('media_attachment_audit.outcome_failure'), value: 'failure' },
])

const searchUsers = params => userListStore.searchItems(params)

const formatBytes = bytes => {
  if (bytes == null || bytes === '')
    return '—'
  const n = Number(bytes)
  if (Number.isNaN(n))
    return '—'
  if (n < 1024)
    return `${n} B`

  return `${(n / 1024).toFixed(1)} KB`
}

const fetchRows = () => {
  logsListStore.fetchMediaAttachmentLogs({
    q: searchQuery.value,
    operation: filterOperation.value || undefined,
    outcome: filterOutcome.value || undefined,
    user_id: filterUserId.value || undefined,
    options: options.value,
    page: options.value.page,
  }).then(response => {
    rows.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalRows.value = response.data.total
    options.value.page = response.data.currentPage
  }).catch(error => {
    console.error(error)
  })
}

const translatedHeaders = () => {
  const headers = [
    { title: '#', key: 'id', width: '4%' },
    { title: 'media_attachment_audit.date', key: 'created_at', width: '11%' },
    { title: 'media_attachment_audit.user', key: 'user_name', width: '12%' },
    { title: 'media_attachment_audit.operation', key: 'operation', width: '8%' },
    { title: 'media_attachment_audit.outcome', key: 'outcome', width: '8%' },
    { title: 'media_attachment_audit.file', key: 'original_filename', width: '14%' },
    { title: 'media_attachment_audit.entity', key: 'attachable_type', width: '10%' },
    { title: 'media_attachment_audit.entity_id', key: 'attachable_id', width: '7%' },
    { title: 'media_attachment_audit.context', key: 'context_type', width: '11%' },
    { title: 'media_attachment_audit.failure', key: 'failure_reason', width: '15%' },
  ]

  return headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }))
}

const outcomeColor = outcome => {
  if (outcome === 'success')
    return 'success'
  if (outcome === 'failure')
    return 'error'
  if (outcome === 'attempt')
    return 'warning'

  return 'secondary'
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchRows, {
  search: searchQuery,
  filters: () => [filterOperation.value, filterOutcome.value, filterUserId.value],
  options,
})
</script>

<template>
  <section>
    <VRow>
      <VCol cols="12">
        <VCard :title="$t('media_attachment_audit.title')">
          <VCardText class="d-flex flex-wrap gap-3 align-center justify-space-between pb-0">
            <p class="text-medium-emphasis mb-0">
              {{ $t('media_attachment_audit.subtitle') }}
            </p>
            <VBtn
              variant="text"
              :to="{ name: 'logs-list' }"
            >
              {{ $t('Logs') }}
            </VBtn>
          </VCardText>

          <VCardText>
            <VRow>
              <VCol
                cols="12"
                sm="4"
              >
                <AppAutocomplete
                  v-model="filterUserId"
                  :server-search="searchUsers"
                  :label="$t('media_attachment_audit.user')"
                  item-title="name"
                  item-value="id"
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="filterOperation"
                  :label="$t('media_attachment_audit.operation')"
                  :items="operationItems"
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="filterOutcome"
                  :label="$t('media_attachment_audit.outcome')"
                  :items="outcomeItems"
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VCardText class="d-flex flex-wrap py-4 gap-4">
            <AppSelect
              :model-value="options.itemsPerPage"
              :items="[
                { value: 10, title: '10' },
                { value: 25, title: '25' },
                { value: 50, title: '50' },
                { value: 100, title: '100' },
              ]"
              style="width: 6.25rem;"
              @update:model-value="options.itemsPerPage = parseInt($event, 10)"
            />
            <VSpacer />
            <div style="inline-size: 14rem;">
              <AppTextField
                v-model="searchQuery"
                :placeholder="$t('Search')"
                density="compact"
              />
            </div>
          </VCardText>

          <VDivider />

          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="rows"
            :items-length="totalRows"
            :headers="translatedHeaders()"
            class="dataTable-hidescroller-y"
            @update:options="onTableOptions"
          >
            <template #item.created_at="{ item }">
              {{ moment(item.raw.created_at).locale(i18n.global.locale.value).format('DD/MM/YYYY HH:mm') }}
            </template>

            <template #item.operation="{ item }">
              {{ $t(`media_attachment_audit.operation_${item.raw.operation}`) }}
            </template>

            <template #item.outcome="{ item }">
              <VChip
                size="small"
                :color="outcomeColor(item.raw.outcome)"
              >
                {{ $t(`media_attachment_audit.outcome_${item.raw.outcome}`) }}
              </VChip>
            </template>

            <template #item.original_filename="{ item }">
              <div class="text-truncate" style="max-width: 220px;">
                {{ item.raw.original_filename || item.raw.stored_filename || '—' }}
              </div>
              <span
                v-if="item.raw.file_size"
                class="text-caption text-disabled"
              >
                {{ formatBytes(item.raw.file_size) }}
                <span v-if="item.raw.mime_type"> · {{ item.raw.mime_type }}</span>
              </span>
            </template>

            <template #item.attachable_type="{ item }">
              {{ item.raw.attachable_type || '—' }}
            </template>

            <template #item.context_type="{ item }">
              <span v-if="item.raw.context_type">
                {{ item.raw.context_type }}<template v-if="item.raw.context_id"> #{{ item.raw.context_id }}</template>
              </span>
              <span v-else>—</span>
            </template>

            <template #item.failure_reason="{ item }">
              <span
                v-if="item.raw.failure_reason"
                class="text-error text-caption"
              >{{ item.raw.failure_reason }}</span>
              <span v-else>—</span>
            </template>

            <template #bottom>
              <VDivider />
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalRows) }}
                </p>
                <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalRows / options.itemsPerPage) || 1"
                  :total-visible="5"
                />
              </div>
            </template>
          </VDataTableServer>
        </VCard>
      </VCol>
    </VRow>
  </section>
</template>

<route lang="yaml">
  meta:
    action: access_logs
    subject: access_logs
</route>
