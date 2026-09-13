<script setup>
import { applyServerTableOptions, debounceRefAssign, watchServerTableFetch } from '@core/utils/tableFetch'
import { paginationMeta } from '@/@fake-db/utils'
import i18n from '@/plugins/i18n/index.js'
import AddNewUserDrawer from '@/views/apps/user/list/AddNewUserDrawer.vue'
import {casesApi} from "@/plugins/apis/casesReqest"
import {disabilityTypeApi} from "@/plugins/apis/disabilityTypeReqest"
import { avatarText } from '@core/utils/formatters'
import { useRoute, useRouter } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { can } from '@layouts/plugins/casl'
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import PreviewDataTable from '@/views/preview/DataTable.vue';

import {
  insuranceItems,
  serviceitems as serviceitemsFetch
} from '@core/utils/generalItems';

const rules = [fileList => !fileList || !fileList.length || fileList[0].size < (100 * 1024 * 1024) || 'validtion.less_than_100MB']


const route = useRoute()
const router = useRouter()
const disabilityTypesApi = disabilityTypeApi()
const casesReqest = casesApi()
const userListStore = useUserListStore()

const snackbarRef = ref(null);
const searchQuery = ref('')
const specialist = ref('')
const selectedServiceitems = ref()
const selectedInsurance = ref()
const selectedStatus = ref('active')
const totalPage = ref(1)
const totalUsers = ref(0)
const users = ref([])
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


const typesDisability = ref([]);
const disability = ref([])
const serviceitems = ref([])
const assignedStaff = ref([])

const loading = ref({
  cases:false,
  disabilities: true,
  services: true,
  staff: true,
})

const staffRoles = [
  'teacher',
  'physiotherapist_specialist',
  'occupational_specialist',
  'psychotherapist_specialist',
  'social_specialist',
  'pronunciation_speech_specialist',
  'mental_disability_specialist',
]

// 👉 Only staff actually assigned to cases the current user can see
const assignedStaffItems = computed(() => assignedStaff.value.map(staff => {
  const role = Array.isArray(staff.roles) && staff.roles.length
    ? (staff.roles[0].name || staff.roles[0].default_name)
    : staff.role_name
  const extra = staff.job_title || role

  return {
    value: staff.id,
    title: extra ? `${staff.name} — ${extra}` : staff.name,
  }
}))

// 👉 Fallback for APIs that don't expose /cases/assigned-staff yet
const loadStaffByRoles = () => userListStore.fetchRolesUsers({
  fetch: 'fetch_roles_users',
  roles: staffRoles,
}).then(response => {
  const grouped = response.data.data || {}
  const flat = []

  Object.keys(grouped).forEach(roleName => {
    (grouped[roleName] || []).forEach(staff => {
      if (!flat.some(item => item.id === staff.id))
        flat.push({ ...staff, role_name: roleName })
    })
  })

  assignedStaff.value = flat
}).catch(() => {
  assignedStaff.value = []
})

const loadAssignedStaff = () => casesReqest.fetchAssignedStaff().then(response => {
  const staff = response.data.data || []

  if (!staff.length)
    return loadStaffByRoles()

  assignedStaff.value = staff
}).catch(() => loadStaffByRoles()).finally(() => {
  loading.value.staff = false
})

onMounted(() => {
  disabilityTypesApi.fetch().then( response =>{
    typesDisability.value = response.data.data
    loading.value.disabilities = false
  });

  serviceitemsFetch().then(data => {
    serviceitems.value = data
    loading.value.services = false
  })

  loadAssignedStaff()
});

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

const onTableSearch = debounceRefAssign(searchQuery)


// 👉 Fetching users
const fetchCases = () => {
  loading.value.cases = true;
  casesReqest.fetchAll({
    q: searchQuery.value,
    status: selectedStatus.value,
    insurance: selectedInsurance.value,
    disability: disability.value,
    specialist_teacher_id: specialist.value,
    service: selectedServiceitems.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    users.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalUsers.value = response.data.total
    options.value.page = response.data.currentPage
    loading.value.cases = false;
    preview.value = false;
  }).catch(error => {
    console.error(error)
    loading.value.cases = false;
  })
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchCases, {
  search: searchQuery,
  filters: () => [
    specialist.value,
    selectedServiceitems.value,
    selectedStatus.value,
    disability.value,
    selectedInsurance.value,
  ],
  options,
})

const searchCaseNames = params => casesReqest.selectItems({
  ...params,
  status: 'all',
})

const translatedHeaders = () => {
  let headers = [
    {
      title: 'Name',
      key: 'user',
      sortable: false,
    },
    {
      title: 'parent',
      key: 'parent',
      sortable: false,
    },
    {
      title: 'Types of disability',
      key: 'disabilities',
      sortable: false,
    },
    {
      title: 'Services provided',
      key: 'service',
      sortable: false,
    },
    {
      title: 'case type',
      key: 'case_type',
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

const resolveUserRoleVariant = role => {
  return {
    color: 'primary',
    icon: 'tabler-user',
  }
}

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
  fetchCases()
}

const deleteCase = id => {
  casesReqest.delete(id).then(() =>{
    fetchCases()
  })
}

const restore = id => {
  casesReqest.restore(id).then(() =>{
    fetchCases()
  })
}

// 👉 Export Cases
const exportCases = () => {
  previewLoading.value = true;
  casesReqest.fetchAll({
    q: searchQuery.value,
    status: selectedStatus.value,
    insurance: selectedInsurance.value,
    disability: disability.value,
    specialist_teacher_id: specialist.value,
    service: selectedServiceitems.value,
    export: 'export_cases'
  }).then(response => {
    previewLoading.value = false;
    window.open(response.data.data.url, '_blank');
  })
  .catch(error => {
    previewLoading.value = false;
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
      formData.append('import', 'import_cases');
      formData.append('action', 'preview');
      formData.append('file', file.value[0]);

      casesReqest.import(formData)
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

const importCases = () => {
  importLoading.value = true;
  casesReqest.import({
    center_id: Number(localStorage.getItem('center')), 
    import: 'import_cases', 
    action: 'import', 
    data: previewData.value.records
  })
  .then(response => {
    if(response.status == 200){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      preview.value = false;
      importLoading.value = false;
      fetchCases()
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
                    v-model="specialist"
                    :items="assignedStaffItems"
                    :loading="loading.staff"
                    :label="$t('specialists_or_teachers')"
                    :no-data-text="$t('autocomplete.no_results')"
                    clear-icon="tabler-x"
                    clearable
                  >
                  </AppAutocomplete>
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                    v-model="disability"
                    clearable
                    multiple
                    chips
                    :loading="loading.disabilities"
                    :items="typesDisability"
                    :label="$t('Types of disability')"
                  >
                  </AppSelect>
              </VCol>
              <!-- 👉 Select Role -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedServiceitems"
                  :label="$t('Services provided')"
                  :items="serviceitems"
                  :loading="loading.services"
                  clearable
                  multiple
                  chips
                  clear-icon="tabler-x"
                />
              </VCol>
              <!-- 👉 Select Plan -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedInsurance"
                  :label="$t('case type')"
                  :items="insuranceItems()"
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>
              <!-- 👉 Select Status -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-if="can('admin_cases','admin_cases')"
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
                  :server-search="searchCaseNames"
                  item-title="name"
                  item-value="name"
                  :placeholder="$t('Search')"
                  density="compact"
                  clearable
                  @update:model-value="val => searchQuery = val ?? ''"
                  @update:search="onTableSearch"
                />
              </div>

              <VMenu v-if="can('edit_cases','edit_cases')">
                <template #activator="{ props }">
                <VBtn color="success" v-bind="props" :loading="previewLoading">
                    {{ $t('data') }}
                </VBtn>
                </template>
                <VList>
                <VListItem @click="exportCases()"><VIcon icon="tabler-cloud-download" /> {{ $t('export_data') }}</VListItem>
                <VListItem @click="fileUploadfun()"><VIcon icon="tabler-cloud-upload" /> {{ $t('import_data') }}</VListItem>
                </VList>
              </VMenu>
              <!-- 👉 Add user button -->
              <VBtn
                v-if="can('edit_cases','edit_cases')"
                prepend-icon="tabler-plus"
                @click="()=> router.push(route.query.to ? String(route.query.to) : '/cases/add')"
              >
                {{ $t('Add Case') }}
              </VBtn>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="users"
            :loading="loading.cases"
            :items-length="totalUsers"
            :headers="translatedHeaders()"
            class="text-no-wrap dataTable"
            @update:options="onTableOptions"
          >          
            <!-- User -->
            <template #item.user="{ item }">
              <div class="d-flex align-center" style="width: 170px; white-space: pre-wrap;">
                <VAvatar
                  size="34"
                  :variant="!item.raw.picture ? 'tonal' : undefined"
                  :color="!item.raw.picture ? resolveUserRoleVariant(item.raw.role).color : undefined"
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
                      :to="{ name: 'cases-view-tab-id', params: { id: item.raw.id, tab: 'info' } }"
                      class="font-weight-medium user-list-name"
                    >
                      {{ item.raw.name }}
                    </RouterLink>
                  </h6>
                </div>
              </div>
            </template>

            <template #item.parent="{ item }">
              <div class="d-flex align-center" style="width: 170px; white-space: pre-wrap;">
                <div class="d-flex flex-column">
                  <h6 v-for="parent in item.raw.parents" class="text-base">
                    <RouterLink
                      :to="{ name: 'user-view-id', params: { id: parent.id } }"
                      class="font-weight-medium user-list-name"
                    >
                      {{ parent.name }}
                    </RouterLink>
                  </h6>
                  <br>
                </div>
              </div>
            </template>

            <template #item.disabilities="{ item }">
              <span style="width: 170px; white-space: pre-wrap;">{{ item.raw.disability_type_ids.map(obj => obj.name).toString().replaceAll(',', '\n') }}</span>
            </template>

            <!--  service -->
            <template #item.service="{ item }">
              <span style="width: 170px; white-space: pre-wrap;">{{ item.raw.services.map(obj => obj.name).toString().replaceAll(',', '\n') }}</span>
            </template>

            <template #item.case_type="{ item }">
              <span>{{ item.raw.beneficiary_number != null && item.raw.beneficiary_number != '' ? $t('Beneficiary') : $t('Not beneficiary') }}</span>
            </template>

            <template #item.active="{ item }">
              <VChip
                :color="resolveUserStatusVariant(item.raw.deleted_at? 'inactive' : 'active' )"
                size="small"
                label
                class="text-capitalize"
              >
                {{ item.raw.deleted_at? $t('Inactive') :$t('Active_user') }}
              </VChip>
            </template>

            <!-- Actions -->
            <template #item.actions="{ item }">

              <IconBtn v-if="can('show_cases','show_cases')" :title="$t('View Case')" @click="()=> router.push('/cases/view/info/'+item.raw.id)">
                <VIcon icon="tabler-eye" />
              </IconBtn>

              <IconBtn v-if="!item.raw.deleted_at && can('edit_cases','edit_cases')" :title="$t('Edit')" @click="()=> router.push('/cases/edit/'+item.raw.id)">
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

                    <VListItem v-if="!item.raw.deleted_at && can('admin_cases','admin_cases')" @click="deleteCase(item.raw.id)">
                      <template #prepend>
                        <VIcon icon="tabler-trash" />
                      </template>
                      <VListItemTitle>{{ $t('delete_user') }}</VListItemTitle>
                    </VListItem>
                    <VListItem v-if="item.raw.deleted_at && can('admin_cases','admin_cases')" @click="restore(item.raw.id)">
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
                  :disabled="loading.cases"
                  total-visible="5"
                  :length="Math.ceil(totalUsers / options.itemsPerPage)"
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
              <span class="v-col v-col-6 d-flex gap-4 text-h4">{{ $t('cases_data') }}</span>
              <VCol
                cols="6"
                class="d-flex gap-4 justify-end"
              >
                <VBtn
                  v-if="can('edit_cases','edit_cases') && previewData.records && previewData.n_errors==0"
                  :loading="importLoading"
                  color="primary"
                  @click="importCases()"
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
<route lang="yaml">
  meta:
    action: access_cases
    subject: access_cases
</route>

<style lang="scss">

.text-capitalize {
  text-transform: capitalize;
}

.user-list-name:not(:hover) {
  color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
}

.dataTable > div{
  overflow-y: hidden !important;
}

</style>
