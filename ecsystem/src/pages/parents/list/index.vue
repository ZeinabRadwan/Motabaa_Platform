<script setup>
import { applyServerTableOptions, debounceRefAssign, watchServerTableFetch } from '@core/utils/tableFetch'
import { paginationMeta } from '@/@fake-db/utils'
import i18n from '@/plugins/i18n/index.js'
import AddNewUserDrawer from '@/views/apps/user/list/AddNewUserDrawer.vue'
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import { avatarText } from '@core/utils/formatters'
import { useRoute, useRouter } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { can } from '@layouts/plugins/casl'
import {casesApi} from "@/plugins/apis/casesReqest"
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import PreviewDataTable from '@/views/preview/DataTable.vue';

const rules = [fileList => !fileList || !fileList.length || fileList[0].size < (100 * 1024 * 1024) || 'validtion.less_than_100MB']


const route = useRoute()
const router = useRouter()
const casesReqest = casesApi()

const snackbarRef = ref(null);
const userListStore = useUserListStore()
const searchQuery = ref('')
const selectedPlan = ref()
const selectedStatus = ref('active')
const totalPage = ref(1)
const totalUsers = ref(0)
const users = ref([])
const selectedCase = ref(null);
const search = ref()
const loading = ref({
  users: true,
})
const fileUpload = ref('')
const file = ref('')
const fileName = ref('file')
const errorsMessage = ref({
  file: undefined,
})
const preview = ref(false)
const previewData = ref([])
const previewLoading = ref(false)
const importLoading = ref(false)

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

const searchParentNames = params => userListStore.searchItems({
  ...params,
  status: 'all',
  onlyParents: true,
})

const searchCases = params => casesReqest.selectItems(params)


const onTableSearch = debounceRefAssign(searchQuery)

// 👉 Fetching users
const fetchUsers = () => {
  loading.value.users = true;
  userListStore.fetchUsers({
    q: searchQuery.value,
    status: selectedStatus.value,
    plan: selectedPlan.value,
    onlyParents: true,
    case_id: selectedCase.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    users.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalUsers.value = response.data.total
    options.value.page = response.data.currentPage
    loading.value.users = false;
  }).catch(error => {
    console.error(error)
  })
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchUsers, {
  search: searchQuery,
  filters: () => [
    selectedCase.value,
    selectedStatus.value,
  ],
  options,
})


const translatedHeaders = () => {
  let headers = [
    {
      title: 'Name',
      key: 'user',
      sortable: false,
    },
    {
      title: 'Cases',
      key: 'cases',
      sortable: false,
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
  }));

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
  ];
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }));

  return translatedItems;
};

const resolveUserStatusVariant = stat => {
  const statLowerCase = stat.toLowerCase()
  if (statLowerCase === 'pending')
    return 'warning'
  if (statLowerCase === 'active')
    return 'success'
  if (statLowerCase === 'inactive')
    return 'secondary'
  
  return 'primary'
}

const isAddNewUserDrawerVisible = ref(false)

const addNewUser = userData => {
  userListStore.addUser(userData)

  // refetch User
  fetchUsers()
}

const deleteUser = id => {
  userListStore.deleteUser(id).then(() =>{
    fetchUsers()
  })
}

const restoreUser = id => {
  userListStore.restoreUser(id).then(() =>{
    fetchUsers()
  })
}

// 👉 Export Employees
const exportParents = () => {
  previewLoading.value = true;
  userListStore.fetchUsers({
    q: searchQuery.value,
    status: selectedStatus.value,
    plan: selectedPlan.value,
    onlyParents: true,
    case_id: selectedCase.value,
    export: 'export_parents'
  }).then(response => {
    previewLoading.value = false;
    window.open(response.data.data.url, '_blank');
  })
  .catch(error => {
  })
}

const fileUploadfun = () => {
  fileUpload.value.click()
}

const onFileSelected = () => {
  previewLoading.value = true;
  if (file.value[0]) {
    fileName.value = file.value[0].name;
    if(file.value[0].size <= (100 * 1024 * 1024)){

      const formData = new FormData();
      formData.append('center_id', Number(localStorage.getItem('center')));
      formData.append('import', 'import_parents');
      formData.append('action', 'preview');
      formData.append('file', file.value[0]);

      userListStore.import(formData)
      .then(response => {
        if(response.status == 200){
          previewData.value = response.data.data;
          preview.value = true;
          previewLoading.value = false;
        }
        file.value = null;
      }).catch(error => {
        previewLoading.value = false;
        errorsMessage.value = error.response.data.errors
      })
    }else{
      previewLoading.value = false;
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.less_than_100MB'), 'error');
    }
  }else{
      previewLoading.value = false;
      fileName.value = 'file';
  }
  errorsMessage.value.file = { file: undefined,};
}

const importParents = () => {
  importLoading.value = true;
  userListStore.import({
    center_id: Number(localStorage.getItem('center')), 
    import: 'import_parents', 
    action: 'import', 
    data: previewData.value.records
  })
  .then(response => {
    if(response.status == 200){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      preview.value = false;
      importLoading.value = false;
      fetchUsers()
    }
    file.value = null;
  }).catch(error => {
    importLoading.value = false;
    errorsMessage.value = error.response.data.errors
  })
}

const cancelImport = () => {
  preview.value = false;
  importLoading.value = false;
}
</script>

<template>
  <section>
    <VRow  v-if="!preview">
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
                  v-model="selectedCase"
                  :server-search="searchCases"
                  :label="$t('Cases')"
                  :item-title="'name'"
                  :item-value="'id'"
                  clearable
                  :placeholder="$t('Type Case Name')"
                  clear-icon="tabler-x"
                />
              </VCol>
              <!-- 👉 Select Status -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedStatus"
                  :label="$t('Active')"
                  :items="statusItems()"
                  clearable
                  clear-icon="tabler-x"
                  
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
                ]"
                style="width: 6.25rem;"
                @update:model-value="options.itemsPerPage = parseInt($event, 10)"
              />
            </div>
            <VSpacer />

            <div class="justify-end d-flex align-center flex-wrap gap-4">
              <!-- 👉 Search  -->
              <div style="inline-size: 20rem;">
                <AppAutocomplete
                  :model-value="searchQuery"
                  :server-search="searchParentNames"
                  item-title="name"
                  item-value="name"
                  :placeholder="$t('Search')"
                  density="compact"
                  clearable
                  @update:model-value="val => searchQuery = val ?? ''"
                  @update:search="onTableSearch"
                />
              </div>

              <VMenu v-if="can('edit_parents','edit_parents')">
                <template #activator="{ props }">
                <VBtn color="success" v-bind="props" :loading="previewLoading">
                    {{ $t('data') }}
                </VBtn>
                </template>
                <VList>
                <VListItem @click="exportParents()"><VIcon icon="tabler-cloud-download" /> {{ $t('export_data') }}</VListItem>
                <VListItem @click="fileUploadfun()"><VIcon icon="tabler-cloud-upload" /> {{ $t('import_data') }}</VListItem>
                </VList>
              </VMenu>
              <!-- 👉 Add user button -->
              <VBtn
                v-if="can('edit_parents','edit_parents')"
                prepend-icon="tabler-plus"
                @click="()=> router.push('/parents/add?to='+route.path)"
              >
                {{ $t('Add Parent') }}
              </VBtn>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="users"
            :items-length="totalUsers"
            :headers="translatedHeaders()"
            class="text-no-wrap dataTable-hidescroller-y"
            @update:options="onTableOptions"
          >          
            <!-- User -->
            <template #item.user="{ item }">
              <div class="d-flex align-center">
                <VAvatar
                  size="34"
                  :variant="!item.raw.picture ? 'tonal' : undefined"
                  class="me-3"
                >
                  <VImg
                    v-if="item.raw.picture"
                    :src="item.raw.picture.file_url"
                  />
                  <span v-else>{{ avatarText(item.raw.name) }}</span>
                </VAvatar>

                <div class="d-flex flex-column">
                  <h6 class="text-base">
                    <RouterLink
                      :to="{ name: 'parents-view-id', params: { id: item.raw.id } }"
                      class="font-weight-medium user-list-name"
                    >
                      {{ item.raw.name }}
                    </RouterLink>
                  </h6>

                  <span class="text-sm text-medium-emphasis">{{ item.raw.email }}</span>
                </div>
              </div>
            </template>

            <!-- User -->
            <template #item.cases="{ item }">
              <div class="d-flex align-center" style="width: 200px; white-space: pre-wrap;">
                <div class="d-flex flex-column">
                  <h6 v-for="scase in item.raw.scaseParent" class="text-base">
                    <RouterLink
                      :to="{ name: 'cases-view-tab-id', params: { id: scase.id, tab: 'info' } }"
                      class="font-weight-medium user-list-name"
                    >
                      {{ scase.name }}
                    </RouterLink>
                  </h6>
                  <br>
                </div>
              </div>
            </template>


            <template #item.active="{ item }">
              <VChip
                :color="resolveUserStatusVariant(item.raw.deleted_at? 'inactive' : 'active' )"
                size="small"
                label
                class="text-capitalize"
              >
                {{ item.raw.deleted_at? $t('Inactive') :$t('Active_user') }}<VIcon v-if="item.raw.can_login" icon="tabler-login" /><VIcon v-if="!item.raw.can_login" icon="tabler-user-x" />
              </VChip>
            </template>

            <!-- Actions -->
            <template #item.actions="{ item }">

              <IconBtn v-if="can('show_parents','show_parents')" :title="$t('View')"  @click="()=> router.push('/parents/view/'+item.raw.id + '?to='+route.path)">
                <VIcon icon="tabler-eye" />
              </IconBtn>

              <IconBtn v-if="!item.raw.deleted_at && can('edit_parents','edit_parents')" :title="$t('Edit')" @click="()=> router.push('/parents/edit/'+item.raw.id + '?to='+route.path)">
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
                    <VListItem v-if="!item.raw.deleted_at && can('admin_parents','admin_parents')" @click="()=> router.push('/parents/change-password/'+item.raw.id + '?to='+route.path)">
                      <template #prepend>
                        <VIcon icon="tabler-password" />
                      </template>
                      <VListItemTitle>{{ $t('Change Password') }}</VListItemTitle>
                    </VListItem>
                    <VListItem v-if="!item.raw.deleted_at && can('admin_parents','admin_parents')" @click="deleteUser(item.raw.id)">
                      <template #prepend>
                        <VIcon icon="tabler-trash" />
                      </template>
                      <VListItemTitle>{{ $t('delete_user') }}</VListItemTitle>
                    </VListItem>
                    <VListItem v-if="item.raw.deleted_at && can('admin_parents','admin_parents')" @click="restoreUser(item.raw.id)">
                      <template #prepend>
                        <VIcon icon="tabler-refresh" />
                      </template>
                      <VListItemTitle>{{ $t('restore_user') }}</VListItemTitle>
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
                  {{ paginationMeta(options, totalUsers) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :disabled="loading.users"
                  :length="Math.ceil(totalUsers / options.itemsPerPage)"
                  :total-visible="5"
                >
                  <template #prev="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      {{ $t('$vuetify.pagination.ariaLabel.previous') }}
                    </VBtn>
                  </template>

                  <template #next="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                    {{ $t('$vuetify.pagination.ariaLabel.next') }}
                    </VBtn>
                  </template>
                </VPagination>
              </div>
            </template>
          </VDataTableServer>
          <!-- SECTION -->
        </VCard>

        <!-- 👉 Add New User -->
        <AddNewUserDrawer
          v-model:isDrawerOpen="isAddNewUserDrawerVisible"
          @user-data="addNewUser"
        />
      </vcol>
    </VRow>
    <VRow v-else>
      <VCol cols="12">
        <VCard class="padding-30p">
          <template v-slot:title>
            <VRow>
              <span class="v-col v-col-6 d-flex gap-4 text-h4">{{ $t('parents_data') }}</span>
              <VCol
                cols="6"
                class="d-flex gap-4 justify-end"
              >
                <VBtn
                  v-if="can('edit_parents','edit_parents') && previewData.records && previewData.n_errors==0"
                  :loading="importLoading"
                  color="primary"
                  @click="importParents()"
                >
                  {{ $t('Save') }}
                </VBtn>
                <VBtn
                  color="secondary"
                  @click="cancelImport()"
                >
                  {{ $t('Cancel') }}
                </VBtn>
              </VCol>
            </VRow>
          </template>
          <VDivider/>
          <PreviewDataTable :headers="previewData.headers" :data="previewData.records"/>
        </VCard>
      </VCol>
    </VRow>
    <VFileInput v-show="false" v-model="file" ref="fileUpload" @change="onFileSelected" />
    <SnackbarComponent ref="snackbarRef" />
  </section>
</template>

<style lang="scss">

.text-capitalize {
  text-transform: capitalize;
}

.user-list-name:not(:hover) {
  color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
}
</style>
<route lang="yaml">
  meta:
    action: access_parents
    subject: access_parents
</route>