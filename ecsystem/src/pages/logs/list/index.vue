<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useRoute, useRouter } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { can } from '@layouts/plugins/casl'
import { logsApi } from "@/plugins/apis/logsRequest";
import moment from '@/plugins/moment'
import { useUserListStore } from '@/views/apps/user/useUserListStore'

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const logsListStore = logsApi()
const userListStore = useUserListStore()
const searchQuery = ref('')
const searchActionBy = ref('')
const users = ref([])
const search = ref()
const searchActionModel = ref('')
const models = ref([])
const logs = ref('')
const totalPage = ref(1)
const totalLogs = ref('')

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

const loading = ref({
  users: false,
})

const searchUsers = params => userListStore.searchItems(params)


// 👉 Fetching logs
const fetchLogs = () => {
  logsListStore.fetchLogs({
    q: searchQuery.value,
    action_by: searchActionBy.value,
    action_type: searchActionModel.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    logs.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalLogs.value = response.data.total
    options.value.page = response.data.currentPage
  }).catch(error => {
    console.error(error)
  })
}

logsListStore.fetchModels().then(response => {
  models.value = response.data.data
})

// 👉 Headers
const translatedHeaders = () => {
  const headers = [
    {
      title: '#',
      key: 'id',
      width: '5%',
    },
    {
      title: 'logs.created_by',
      key: 'created_by_name',
      width: '20%',
    },
    {
      title: 'logs.model_type',
      key: 'model_type_name',
      width: '15%',
    },
    {
      title: 'logs.model_id',
      key: 'model_id_name',
      width: '20%',
    },
    {
      title: 'logs.type',
      key: 'type',
      width: '15%',
    },
    {
      title: 'logs.description',
      key: 'description',
      width: '13%',
    },
    {
      title: 'logs.date',
      key: 'created_at',
      width: '12%',
    },
  ]

  let translatedHeaders = headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }))

  return translatedHeaders;
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchLogs, {
  search: searchQuery,
  filters: () => [searchActionBy.value, searchActionModel.value],
  options,
})
</script>

<template>
  <section>
    <VRow>

      <VCol cols="12">
        <VCard>
          <!-- 👉 Filters -->
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                sm="4"
              > 
                <AppAutocomplete
                  v-model="searchActionBy"
                  :server-search="searchUsers"
                  :label="$t('logs.created_by')"
                  :item-title="'name'"
                  :item-value="'id'"
                  clear-icon="tabler-x"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="searchActionModel"
                  :label="$t('logs.model_type')"
                  :items="models"
                  :item-title="item => item.name"
                  :item-value="item => item.id"
                  clearable
                  clear-icon="tabler-x"
                  class="pa-1"
                >
                </AppSelect>
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VCardText class="d-flex flex-wrap py-4 gap-4">
            <div class="me-3 d-flex gap-3">
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
            </div>
            <VSpacer />

            <div class="app-payment-search-filter justify-end d-flex align-center flex-wrap gap-4">
              <!-- 👉 Search  -->
              <div style="inline-size: 10rem;">
                <AppTextField
                  v-model="searchQuery"
                  :placeholder="$t('Search')"
                  density="compact"
                />
              </div>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="logs"
            :items-length="totalLogs"
            :headers="translatedHeaders()"
            class="dataTable-hidescroller-y"
            v-sortable-data-table
            @sorted="saveOrder"
            @update:options="onTableOptions"
          >
            <template #item.model_type_name="{ item }">
              {{ $t('logs.'+item.raw.model_type_name) }}
            </template>

            <template #item.type="{ item }">
              {{ $t('logs.'+item.raw.type) }}
            </template>

            <template #item.description="{ item }">
              <span v-if="item.raw.is_description_json" v-for="(value, key) in item.raw.description">
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('logs.'+key) }}:</span>{{ value }}</span><br>
              </span>
              <span v-else>
                {{ item.raw.description }}
              </span>
            </template>

            <template #item.created_at="{ item }">
              {{ moment(item.raw.created_at).locale(i18n.global.locale.value).fromNow() }}
            </template>

            <!-- pagination -->
            <template #bottom>
              <VDivider />
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalLogs) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalLogs / options.itemsPerPage)"
                  :total-visible="5"
                >
                  <template #prev="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      Previous
                    </VBtn>
                  </template>

                  <template #next="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      Next
                    </VBtn>
                  </template>
                </VPagination>
              </div>
            </template>
          </VDataTableServer>
          <!-- SECTION -->
        </VCard>
      </vcol>
    </vrow>
  </section>
</template>

<style lang="scss">
  .name-route:not(:hover) {
    color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
  }
  .text-capitalize {
    text-transform: capitalize;
  }
</style>

<route lang="yaml">
  meta:
    action: access_logs
    subject: access_logs
</route>