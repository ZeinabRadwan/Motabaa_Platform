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
                <AppSelect
                  v-model="selectedPlan"
                  :label="$t('Case')"
                  :items="plans"
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
                  { value: 100, title: 'All' },
                ]"
                style="width: 6.25rem;"
                @update:model-value="options.itemsPerPage = parseInt($event, 10)"
              />
            </div>
            <VSpacer />

            <div class="app-user-search-filter justify-end d-flex align-center flex-wrap gap-4">
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
                v-if="can('edit_users','edit_users')"
                prepend-icon="tabler-plus"
                @click="()=> router.replace(route.query.to ? String(route.query.to) : '/user/add')"
              >
                {{ $t('Add User') }}
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
            class="text-no-wrap"
            @update:options="onTableOptions"
          >
            <!-- User -->
            <template #item.user="{ item }">
              <div class="d-flex align-center">
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
                      :to="{ name: 'apps-user-view-id', params: { id: item.raw.id } }"
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
              <div class="align-center">

                {{ item.raw.scaseParent.map(obj => obj.name).toString() }}
              </div>
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

              <IconBtn v-if="can('show_users','show_users')"  @click="()=> router.replace('/user/view/'+item.raw.id)">
                <VIcon icon="tabler-eye" />
              </IconBtn>

              <IconBtn v-if="!item.raw.deleted_at && can('edit_users','edit_users')" @click="()=> router.replace('/user/edit/'+item.raw.id)">
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

                    <VListItem v-if="!item.raw.deleted_at && can('admin_users','admin_users')" @click="()=> router.replace('/user/change-password/'+item.raw.id)">
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
<!--              <VDivider />-->
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalUsers) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalUsers / options.itemsPerPage)"
                  :total-visible="$vuetify.display.xs ? 1 : Math.ceil(totalUsers / options.itemsPerPage)"
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
    </vrow>
  </section>
</template>


<script setup>
  import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
  import i18n from '@/plugins/i18n/index.js'
  import { useUserListStore } from '@/views/apps/user/useUserListStore'
  import { useRoute, useRouter } from 'vue-router'


  const route = useRoute()
  const router = useRouter()

  const userListStore = useUserListStore()
  const searchQuery = ref('')
  const selectedPlan = ref()
  const selectedStatus = ref()
  const totalPage = ref(1)
  const totalUsers = ref(0)
  const users = ref([])

  const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [],
    groupBy: [],
    search: undefined,
  })



  // 👉 Fetching users
  const fetchUsers = () => {
    userListStore.fetchUsers({
      q: searchQuery.value,
      status: selectedStatus.value,
      plan: selectedPlan.value,
      onlyParents: true,
      options: options.value,
      page: options.value.page,

    }).then(response => {
      users.value = response.data.data
      totalPage.value = Math.ceil(response.data.total / response.data.perPage)
      totalUsers.value = response.data.total
      options.value.page = response.data.currentPage
    }).catch(error => {
      console.error(error)
    })
  }

  const onTableOptions = incoming => applyServerTableOptions(options, incoming)

  watchServerTableFetch(fetchUsers, {
    search: searchQuery,
    filters: () => [selectedStatus.value, selectedPlan.value],
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
</script>

<style lang="scss">
  .app-user-search-filter {
    inline-size: 31.6rem;
  }

  .text-capitalize {
    text-transform: capitalize;
  }

  .user-list-name:not(:hover) {
    color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
  }
</style>
