<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useRoute, useRouter } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { termsApi } from "@/plugins/apis/termsRequest";
import { avatarText } from '@core/utils/formatters'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { can } from '@layouts/plugins/casl'
import SnackbarComponent from '@core/components/SnackbarCustom.vue';

const route = useRoute()
const router = useRouter()
const snackbarRef = ref(null)
const refVForm = ref()
const termListStore = termsApi()
const searchQuery = ref('')
const selectedFromDate = ref()
const selectedToDate = ref()
const selectedStatus = ref('active')
const canViewPastTerms = computed(() => {
  const userData = JSON.parse(localStorage.getItem('userData') || '{}')
  const roleNames = (userData.roles || []).map(role => String(role.default_name || '').toLowerCase())

  return roleNames.includes('admin') || roleNames.includes('manager')
})
const totalPage = ref(1)
const totalTerms = ref(0)
const terms = ref([])

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

// Headers
const translatedHeaders = () => {
  const headers = [
    {
      title: 'Name',
      key: 'title',
    },
    {
      title: 'date',
      key: 'date',
    },
    {
      title: 'Active',
      key: 'active',
      sortable: false,
    },
    {
      title: 'Actions',
      key: 'actions',
      sortable: false,
    },
  ]
  let translatedHeaders = headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }))

  return translatedHeaders;
}

const statusItems = () => {
  let items = [
    {
      title: 'All',
      value: 'all',
    },
    {
      title: 'Active_user',
      value: 'active',
    },
    {
      title: 'Inactive',
      value: 'inactive',
    },
  ]
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))

  return translatedItems;
}

const resolveTermStatusVariant = stat => {
  const statLowerCase = stat.toLowerCase()
  if (statLowerCase === 'pending')
    return 'warning'
  if (statLowerCase === 'active')
    return 'success'
  if (statLowerCase === 'inactive')
    return 'secondary'
  
  return 'primary'
}


// 👉 Fetching terms
const fetchTerms = () => {
  termListStore.fetchTerms({
    q: searchQuery.value,
    from_date: selectedFromDate.value,
    to_date: selectedToDate.value,
    status: canViewPastTerms.value ? selectedStatus.value : 'active',
    options: options.value,
    page: options.value.page,
  }).then(response => {
    terms.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalTerms.value = response.data.total
    options.value.page = response.data.currentPage
  }).catch(error => {
    console.error(error)
  })
}

const deleteTerm = id => {
  termListStore.deleteTerm(id).then(response => {
    if(response.data['status']){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      fetchTerms()
    }
  })
}

const restoreTerm = id => {
  termListStore.restoreTerm(id).then(response => {
    if(response.data['status']){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      fetchTerms()
    }
  })
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchTerms, {
  search: searchQuery,
  filters: () => [selectedFromDate.value, selectedToDate.value, selectedStatus.value],
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

              <!-- 👉 Select Start Date -->
              <VCol
                v-if="canViewPastTerms"
                cols="12"
                sm="4"
              >
                <div class="pa-1">
                  {{ $t('date') }}
                </div>
                <VRow>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppDateTimePicker
                      v-model="selectedFromDate"
                      :placeholder="$t('from')"
                    />
                  </VCol>
                  
                  <!-- 👉 Select End Date -->
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppDateTimePicker
                      v-model="selectedToDate"
                      :placeholder="$t('to')"
                    />
                  </VCol>
                </VRow>
              </VCol>

              <!-- 👉 Select Status -->
              <VCol
                v-if="canViewPastTerms"
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedStatus"
                  :label="$t('Active')"
                  :items="statusItems()"
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
                  { value: 100, title: 'All' },
                ]"
                style="width: 6.25rem;"
                @update:model-value="options.itemsPerPage = parseInt($event, 10)"
              />
            </div>
            <VSpacer />

            <div class="justify-end d-flex align-center flex-wrap gap-4">
              <!-- 👉 Search  -->
              <div style="inline-size: 20rem;">
                <AppTextField
                  v-model="searchQuery"
                  :placeholder="$t('Search')"
                  density="compact"
                />
              </div>
              
              <!-- 👉 Add user button -->
              <VBtn
                v-if="can('edit_qualifying-classes', 'edit_qualifying-classes')"
                prepend-icon="tabler-plus"
                @click="()=> router.push(route.query.to ? String(route.query.to) : '/classes/edit/0')"
              >
                {{ $t('Add Term') }}
              </VBtn>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="terms"
            :items-length="totalTerms"
            :headers="translatedHeaders()"
            class="dataTable-hidescroller-y"
            @update:options="onTableOptions"
          >
            <!-- Name -->
            <template #item.title="{ item }">
              <RouterLink
                :to="{ name: 'classes-view-id', params: { id: item.raw.id } }"
                class="font-weight-medium name-route"
              >
                {{ item.raw.title }}
              </RouterLink>
            </template>

            <!-- Date -->
            <template #item.date="{ item }">
              {{ $t('from') }} {{ item.raw.starts_at }} {{ $t('to') }} {{ item.raw.ends_at }}
            </template>
          
            <!-- Active -->
            <template #item.active="{ item }">
              <VChip
                :color="resolveTermStatusVariant(item.raw.deleted_at ? 'inactive' : 'active' )"
                size="small"
                label
                class="text-capitalize"
              >
                {{ item.raw.deleted_at ? $t('Inactive') : $t('Active_user') }}
              </VChip>
            </template>

            <!-- Actions -->
            <template #item.actions="{ item }">

              <IconBtn v-if="can('show_qualifying-classes', 'show_qualifying-classes')" @click="()=> router.push('/classes/view/'+item.raw.id)">
                <VIcon icon="tabler-eye" />
              </IconBtn>

              <IconBtn v-if="can('edit_qualifying-classes', 'edit_qualifying-classes')" @click="()=> router.push('/classes/edit/'+item.raw.id)">
                <VIcon icon="tabler-edit" />
              </IconBtn>

              <VBtn
                icon
                variant="text"
                size="small"
                color="medium-emphasis"
              >
                <VIcon
                  size="24"
                  icon="tabler-dots-vertical"
                />
                <VMenu activator="parent">
                  <VList>
                    <VListItem 
                      v-if="!item.raw.deleted_at && can('admin_qualifying-classes', 'admin_qualifying-classes')" 
                      @click="deleteTerm(item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-trash" />
                      </template>
                      <VListItemTitle>{{ $t('delete_user') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="item.raw.deleted_at && can('admin_qualifying-classes', 'admin_qualifying-classes')" 
                      @click="restoreTerm(item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-refresh" />
                      </template>
                      <VListItemTitle>{{ $t('restore') }}</VListItemTitle>
                    </VListItem>
                  </VList>
                </VMenu>
              </VBtn>
            </template>

            <!-- pagination -->
            <template #bottom>
              <VDivider />
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalTerms) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalTerms / options.itemsPerPage)"
                  :total-visible="$vuetify.display.xs ? 1 : Math.ceil(totalTerms / options.itemsPerPage)"
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
      </VCol>
    </VRow>
  <SnackbarComponent ref="snackbarRef"></SnackbarComponent>
  </section>
</template>

<style lang="scss">
  .text-capitalize {
    text-transform: capitalize;
  }

  .name-route:not(:hover) {
    color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
  }
</style>

<route lang="yaml">
  meta:
    action: access_qualifying-classes
    subject: access_qualifying-classes
</route>