<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useRoute, useRouter } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { centersApi } from "@/plugins/apis/centersRequest"
import { avatarText } from '@core/utils/formatters'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { can } from '@layouts/plugins/casl'
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import {cuntriesIndexed} from "@core/utils/cuntries";

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const snackbarRef = ref(null);
const searchQuery = ref('')
const centerListStore = centersApi()
const selectedStatus = ref('active')
const centers = ref([])
const totalPage = ref(1)
const totalCenters = ref(0)

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

// 👉 Headers
const translatedHeaders = () => {
  const headers = [
    {
      title: 'centers.name',
      key: 'title',
      width: '30%',
    },
    {
      title: 'centers.country',
      key: 'country',
      width: '15%',
    },
    {
      title: 'centers.address',
      key: 'city',
      width: '15%',
    },
    {
      title: 'centers.package',
      key: 'package_name',
      width: '15%',
    },
    {
      title: 'Active',
      key: 'active',
      sortable: false,
      width: '13%',
    },
    {
      title: 'Actions',
      key: 'actions',
      sortable: false,
      width: '12%',
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
      title: 'new',
      value: 'new',
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

const resolveCenterStatusVariant = (center) => {
  if (center.deleted_at)
    return 'warning'
  else if (Number(center.status) == 1)
    return 'success'
  else
    return 'primary'
  
  return 'secondary'
}


// 👉 Fetching Centers
const fetchCenters = () => {
  centerListStore.fetchCenters({
    q: searchQuery.value,
    status: selectedStatus.value,
    options: options.value,
    page: options.value.page,
  }).then(response => {
    centers.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalCenters.value = response.data.total
    options.value.page = response.data.currentPage
  }).catch(error => {
    console.error(error)
  })
}

const deleteCenter = id => {
  centerListStore.deleteCenter(id).then(response => {
    if(response.data['status']){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      fetchCenters()
    }
  })
}

const restoreCenter = id => {
  centerListStore.restoreCenter(id).then(response => {
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      fetchCenters()
  })
}

const activateCenter = id => {
  centerListStore.activateCenter(id).then(response => {
    if(response.data['status']){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('updated_successfully'), 'success');
      fetchCenters()
    }
  })
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchCenters, {
  search: searchQuery,
  filters: () => [selectedStatus.value],
  options,
})
</script>

<template>
  <section>
    <VRow>

      <VCol cols="12">
        <div class="page-header">
          <div>
            <h1 class="page-header__title">
              {{ $t('Centers') }}
            </h1>
            <p class="page-header__subtitle">
              {{ $t('center_list_subtitle') }}
            </p>
          </div>
          <VBtn
            v-if="can('edit_centers', 'edit_centers')"
            prepend-icon="tabler-plus"
            @click="()=> router.push(route.query.to ? String(route.query.to) : '/centers/put/0')"
          >
            {{ $t('centers.add_center') }}
          </VBtn>
        </div>
        <VCard>
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
              <div style="inline-size: 12rem;">
                <AppSelect
                  v-model="selectedStatus"
                  :label="$t('Active')"
                  :items="statusItems()"
                  clearable
                  clear-icon="tabler-x"
                />
              </div>
              <div style="inline-size: 20rem;">
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
            :items="centers"
            :items-length="totalCenters"
            :headers="translatedHeaders()"
            class="dataTable-hidescroller-y"
            @update:options="onTableOptions"
          >
            <!-- User -->
            <template #item.title="{ item }">
              <div class="d-flex align-center">
                <VAvatar
                  size="34"
                  :variant="!item.raw.logo ? 'tonal' : undefined"
                  class="me-3"
                >
                  <VImg
                    v-if="item.raw.logo"
                    :src="item.raw.logo.file_url"
                  />
                  <span v-else>{{ avatarText(item.raw.title) }}</span>
                </VAvatar>

                <div class="d-flex flex-column">
                  {{ item.raw.title }}
                </div>
              </div>
            </template>

            <template #item.country="{ item }">
              {{ cuntriesIndexed[item.raw.country][i18n.global.locale.value == "ar" ? "country_arName" : "country_enName"] }}
            </template>
          
          <!-- Active -->
          <template #item.active="{ item }">
            <VChip
              :color="resolveCenterStatusVariant(item.raw)"
              size="small"
              label
              class="text-capitalize"
            >
              {{ item.raw.deleted_at ? $t('Inactive') : ( Number(item.raw.status)==1 ? $t('Active_user') : $t('new')) }}
            </VChip>
          </template>

            <!-- Actions -->
            <template #item.actions="{ item }">

              <IconBtn v-if="can('show_centers', 'show_centers')" @click="()=> router.push('/centers/view/'+item.raw.id)">
                <VIcon icon="tabler-eye" />
              </IconBtn>

              <IconBtn v-if="can('edit_centers', 'edit_centers')" @click="()=> router.push('/centers/put/'+item.raw.id)">
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
                      v-if="!item.raw.deleted_at && can('admin_centers', 'admin_centers')" 
                      @click="deleteCenter(item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-trash" />
                      </template>
                      <VListItemTitle>{{ $t('delete') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="item.raw.deleted_at && can('admin_centers', 'admin_centers')" 
                      @click="restoreCenter(item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-refresh" />
                      </template>
                      <VListItemTitle>{{ $t('restore') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="!item.raw.deleted_at && Number(item.raw.status)!=1 && can('admin_centers', 'admin_centers')" 
                      @click="activateCenter(item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-check" />
                      </template>
                      <VListItemTitle>{{ $t('activate') }}</VListItemTitle>
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
                  {{ paginationMeta(options, totalCenters) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalCenters / options.itemsPerPage)"
                  total-visible="5"
                  :total-visible="$vuetify.display.xs ? 1 : Math.ceil(totalCenters / options.itemsPerPage)"
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
    action: access_centers
    subject: access_centers
</route>