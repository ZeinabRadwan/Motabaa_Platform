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
import { useRoleStore } from '@/views/apps/roles/useRoleStore';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import PreviewDataTable from '@/views/preview/DataTable.vue';
import {
  departmentItems,
  hrAlertItems,
  workShiftItems,
} from '@core/utils/generalItems'

const rules = [fileList => !fileList || !fileList.length || fileList[0].size < (100 * 1024 * 1024) || 'validtion.less_than_100MB']

const route = useRoute()
const router = useRouter()
const roleStore = useRoleStore()

const snackbarRef = ref(null);
const userListStore = useUserListStore()
const searchQuery = ref('')
const selectedRoles = ref()
const selectedPlan = ref()
const selectedStatus = ref(route.query.status || 'active')
const selectedDepartment = ref()
const selectedWorkShift = ref()
const selectedHrAlert = ref(route.query.hr_alert || undefined)
const totalPage = ref(1)
const totalUsers = ref(0)
const users = ref([])
const roles = ref([])
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
const filtersOpen = ref(false)


const loading = ref(false)

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

onMounted(() => {
  roleStore.fetchOnlyRoles().then( response =>{
    roles.value  = response.data.data['roles'].filter(item => item.default_name !== "parent")
  });
});

const searchStaffNames = params => userListStore.searchItems({
  ...params,
  status: 'all',
  excludeParent: true,
})

const onTableSearch = debounceRefAssign(searchQuery)

// 👉 Fetching users
const fetchUsers = () => {
  loading.value = true;
  userListStore.fetchUsers({
    q: searchQuery.value,
    status: selectedStatus.value,
    plan: selectedPlan.value,
    excludeParent: true,
    roles: selectedRoles.value,
    department: selectedDepartment.value,
    work_shift: selectedWorkShift.value,
    hr_alert: selectedHrAlert.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    users.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalUsers.value = response.data.total
    options.value.page = response.data.currentPage
    loading.value = false;
    preview.value = false;
  }).catch(error => {
    console.error(error)
  })
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchUsers, {
  search: searchQuery,
  filters: () => [
    selectedRoles.value,
    selectedStatus.value,
    selectedPlan.value,
    selectedDepartment.value,
    selectedWorkShift.value,
    selectedHrAlert.value,
  ],
  options,
})

const plans = [
  {
    title: 'Basic',
    value: 'basic',
  },
  {
    title: 'Company',
    value: 'company',
  },
  {
    title: 'Enterprise',
    value: 'enterprise',
  },
  {
    title: 'Team',
    value: 'team',
  },
]

const translatedHeaders = () => {
  let headers = [
    {
      title: 'Name',
      key: 'user',
      sortable: false,
    },
    {
      title: 'Roles',
      key: 'tasks',
      sortable: false,
    },
    {
      title: 'employee_affairs.job_title',
      key: 'job_title',
      sortable: false,
    },
    {
      title: 'Department',
      key: 'department',
      sortable: false,
    },
    {
      title: 'employee_affairs.work_shift',
      key: 'work_shift',
      sortable: false,
    },
    {
      title: 'Permissions',
      key: 'permissionss',
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

// 👉 List
const userListMeta = [
  {
    icon: 'tabler-user',
    color: 'primary',
    title: 'Session',
    stats: '21,459',
    percentage: +29,
    subtitle: 'Total Users',
  },
  {
    icon: 'tabler-user-plus',
    color: 'error',
    title: 'Paid Users',
    stats: '4,567',
    percentage: +18,
    subtitle: 'Last week analytics',
  },
  {
    icon: 'tabler-user-check',
    color: 'success',
    title: 'Active Users',
    stats: '19,860',
    percentage: -14,
    subtitle: 'Last week analytics',
  },
  {
    icon: 'tabler-user-exclamation',
    color: 'warning',
    title: 'Pending Users',
    stats: '237',
    percentage: +42,
    subtitle: 'Last week analytics',
  },
]

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
const exportEmployees = () => {
  previewLoading.value = true;
  userListStore.fetchUsers({
    q: searchQuery.value,
    status: selectedStatus.value,
    plan: selectedPlan.value,
    excludeParent: true,
    roles: selectedRoles.value,
    department: selectedDepartment.value,
    work_shift: selectedWorkShift.value,
    hr_alert: selectedHrAlert.value,
    export: 'export_employees'
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
      formData.append('import', 'import_employees');
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

const importEmployees = () => {
  importLoading.value = true;
  userListStore.import({
    center_id: Number(localStorage.getItem('center')), 
    import: 'import_employees', 
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

const activeFilterCount = computed(() => {
  let count = 0
  if (Array.isArray(selectedRoles.value) ? selectedRoles.value.length : selectedRoles.value)
    count++
  if (selectedStatus.value && selectedStatus.value !== 'active')
    count++
  if (selectedDepartment.value)
    count++
  if (selectedWorkShift.value)
    count++
  if (selectedHrAlert.value)
    count++

  return count
})
</script>

<template>
  <section>
    <VRow v-if="!preview">
      <!-- <VCol
        v-for="meta in userListMeta"
        :key="meta.title"
        cols="12"
        sm="6"
        lg="3"
      >
        <VCard>
          <VCardText class="d-flex justify-space-between">
            <div>
              <span>{{ meta.title }}</span>
              <div class="d-flex align-center gap-2 my-1">
                <h6 class="text-h4">
                  {{ meta.stats }}
                </h6>
                <span :class="meta.percentage > 0 ? 'text-success' : 'text-error'">( {{ meta.percentage > 0 ? '+' : '' }} {{ meta.percentage }}%)</span>
              </div>
              <span>{{ meta.subtitle }}</span>
            </div>

            <VAvatar
              rounded
              variant="tonal"
              :color="meta.color"
              :icon="meta.icon"
            />
          </VCardText>
        </VCard>
      </VCol> -->
      <VCol cols="12">
        <div class="page-header">
          <div>
            <h1 class="page-header__title">
              {{ $t('Users') }}
            </h1>
            <p class="page-header__subtitle">
              {{ $t('user_list_subtitle') }}
            </p>
          </div>
          <VBtn
            v-if="can('edit_users','edit_users')"
            prepend-icon="tabler-plus"
            @click="()=> router.push(route.query.to ? String(route.query.to) : '/user/add')"
          >
            {{ $t('Add User') }}
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
                ]"
                style="width: 6.25rem;"
                @update:model-value="options.itemsPerPage = parseInt($event, 10)"
              />
            </div>
            <VSpacer />

            <div class="justify-end d-flex align-center flex-wrap gap-4">
              <div style="inline-size: 16rem;">
                <AppAutocomplete
                  :model-value="searchQuery"
                  :server-search="searchStaffNames"
                  item-title="name"
                  item-value="name"
                  :placeholder="$t('Search')"
                  density="compact"
                  clearable
                  @update:model-value="val => searchQuery = val ?? ''"
                  @update:search="onTableSearch"
                />
              </div>

              <VBtn
                variant="tonal"
                color="default"
                prepend-icon="tabler-filter"
                @click="filtersOpen = !filtersOpen"
              >
                {{ $t('Filters') }}
                <VChip
                  v-if="activeFilterCount"
                  size="x-small"
                  color="primary"
                  class="ms-2"
                >
                  {{ activeFilterCount }}
                </VChip>
              </VBtn>

              <VMenu v-if="can('edit_users','edit_users')">
                <template #activator="{ props }">
                <VBtn variant="tonal" color="default" v-bind="props" :loading="previewLoading">
                    {{ $t('data') }}
                </VBtn>
                </template>
                <VList>
                <VListItem @click="exportEmployees()"><VIcon icon="tabler-cloud-download" /> {{ $t('export_data') }}</VListItem>
                <VListItem @click="fileUploadfun()"><VIcon icon="tabler-cloud-upload" /> {{ $t('import_data') }}</VListItem>
                </VList>
              </VMenu>
            </div>
          </VCardText>

          <VExpandTransition>
            <div v-show="filtersOpen">
              <VDivider />
              <VCardText>
                <VRow>
                  <VCol
                    cols="12"
                    sm="4"
                  >
                    <AppSelect
                      v-model="selectedRoles"
                      :label="$t('Roles')"
                      :items="roles"
                      :item-title="'name'"
                      :item-value="'id'"
                      clearable
                      chips
                      multiple
                      clear-icon="tabler-x"
                    />
                  </VCol>
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
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="4"
                  >
                    <AppSelect
                      v-model="selectedDepartment"
                      :label="$t('Department')"
                      :items="departmentItems()"
                      clearable
                      clear-icon="tabler-x"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="4"
                  >
                    <AppSelect
                      v-model="selectedWorkShift"
                      :label="$t('employee_affairs.work_shift')"
                      :items="workShiftItems()"
                      clearable
                      clear-icon="tabler-x"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    sm="4"
                  >
                    <AppSelect
                      v-model="selectedHrAlert"
                      :label="$t('employee_affairs.hr_alert')"
                      :items="hrAlertItems()"
                      clearable
                      clear-icon="tabler-x"
                    />
                  </VCol>
                </VRow>
              </VCardText>
            </div>
          </VExpandTransition>

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
                      :to="{ name: 'user-view-id', params: { id: item.raw.id } }"
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
            <template #item.tasks="{ item }">
              <div class="align-center" style="width: 170px; white-space: pre-wrap;">
                  
                  {{ item.raw.roles.map(obj => obj.name).toString().replaceAll(',', '\n') }}
              </div>
            </template>

            <template #item.job_title="{ item }">
              {{ item.raw.job_title }}
            </template>

            <template #item.department="{ item }">
              {{ item.raw.department_label || (item.raw.department ? $t(`department.${item.raw.department}`) : '') }}
            </template>

            <template #item.work_shift="{ item }">
              {{ item.raw.work_shift_label || '' }}
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

              <IconBtn v-if="can('show_users','show_users')" :title="$t('View')" @click="()=> router.push('/user/view/'+item.raw.id)">
                <VIcon icon="tabler-eye" />
              </IconBtn>

              <IconBtn v-if="!item.raw.deleted_at && can('edit_users','edit_users')" :title="$t('Edit')" @click="()=> router.push('/user/edit/'+item.raw.id)">
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
                    <!-- <VListItem :to="{ name: 'apps-user-view-id', params: { id: item.raw.id } }">
                      <template #prepend>
                        <VIcon icon="tabler-eye" />
                      </template>

                      <VListItemTitle>View</VListItemTitle>
                    </VListItem>

                    <VListItem link>
                      <template #prepend>
                        <VIcon icon="tabler-pencil" />
                      </template>
                      <VListItemTitle>Edit</VListItemTitle>
                    </VListItem> -->

                    <VListItem v-if="!item.raw.deleted_at && can('admin_users','admin_users')" @click="()=> router.push('/user/change-password/'+item.raw.id)">
                      <template #prepend>
                        <VIcon icon="tabler-password" />
                      </template>
                      <VListItemTitle>{{ $t('Change Password') }}</VListItemTitle>
                    </VListItem>
                    <VListItem v-if="!item.raw.deleted_at && can('admin_users','admin_users')" @click="deleteUser(item.raw.id)">
                      <template #prepend>
                        <VIcon icon="tabler-trash" />
                      </template>
                      <VListItemTitle>{{ $t('delete_user') }}</VListItemTitle>
                    </VListItem>
                    <VListItem v-if="item.raw.deleted_at && can('admin_users','admin_users')" @click="restoreUser(item.raw.id)">
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
                  :disabled="loading"
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
              <span class="v-col v-col-6 d-flex gap-4 text-h4">{{ $t('employees_data') }}</span>
              <VCol
                cols="6"
                class="d-flex gap-4 justify-end"
              >
                <VBtn
                  v-if="can('edit_users','edit_users') && previewData.records && previewData.n_errors==0" 
                  :loading="importLoading"
                  color="primary"
                  @click="importEmployees()"
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
    action: access_users
    subject: access_users
</route>