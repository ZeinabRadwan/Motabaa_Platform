<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { paginationMeta } from '@/@fake-db/utils';
import { centersApi } from "@/plugins/apis/centersRequest";
import i18n from '@/plugins/i18n/index.js';
import { useRoleStore } from '@/views/apps/roles/useRoleStore';
import { useUserListStore } from '@/views/apps/user/useUserListStore';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import { avatarText } from '@core/utils/formatters';
import {
  applyUserSession,
  isImpersonating,
  saveImpersonatorSession,
} from '@core/utils/impersonation';
import { can } from '@layouts/plugins/casl';
import {
  confirmedValidator,
  passwordValidator,
  requiredValidator,
} from '@validators';
import { useRoute, useRouter } from 'vue-router';
import { VDataTableServer } from 'vuetify/labs/VDataTable';

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const snackbarRef = ref(null);
const searchQuery = ref('')
const centerListStore = centersApi()
const roleStore = useRoleStore()
const userListStore = useUserListStore()
const centers = ref([])
const roles = ref([])
const selectedCenter = ref()
const selectedRoles = ref()
const selectedStatus = ref('active')
const users = ref([])
const totalPage = ref(1)
const totalUsers = ref(0)
const isPasswordDialogVisible = ref(false)
const passwordSaving = ref(false)
const passwordUserId = ref(null)
const newPassword = ref('')
const confirmPassword = ref('')
const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const passwordForm = ref()
const currentUserData = JSON.parse(localStorage.getItem('userData') || '{}')
const isPlatformAdmin = currentUserData.role === 'admin' && !isImpersonating()
const isImpersonateDialogVisible = ref(false)
const impersonateSaving = ref(false)
const impersonateTarget = ref(null)

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
  centerListStore.items({
    status: 'active',
  }).then(response => {
    centers.value = Array.isArray(response.data.data) ? response.data.data : []
  });
});

// 👉 Headers
const translatedHeaders = () => {
  const headers = [
    {
      title: 'centers.user_name',
      key: 'name',
      width: '30%',
    },
    {
      title: 'centers.center',
      key: 'centers',
      width: '20%',
    },
    {
      title: 'centers.roles',
      key: 'roles',
      width: '15%',
    },
    {
      title: 'Information',
      key: 'information',
      width: '20%',
    },
    {
      title: 'Active',
      key: 'active',
      sortable: false,
      width: '15%',
    },
    {
      title: 'Actions',
      key: 'actions',
      sortable: false,
      width: '10%',
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


// 👉 Fetching Users
const fetchUsers = () => {
  centerListStore.fetchUsers({
    q: searchQuery.value,
    center: selectedCenter.value,
    roles: selectedRoles.value,
    status: selectedStatus.value,
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

const deleteUser = id => {
  centerListStore.deleteUser(id).then(response => {
    if(response.data['status']){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      fetchUsers()
    }
  })
}

const resetPasswordForm = () => {
  newPassword.value = ''
  confirmPassword.value = ''
  isPasswordVisible.value = false
  isConfirmPasswordVisible.value = false
  passwordUserId.value = null
  passwordForm.value?.resetValidation?.()
}

const openPasswordDialog = user => {
  if (user.deleted_at)
    return

  passwordUserId.value = user.id
  newPassword.value = ''
  confirmPassword.value = ''
  isPasswordDialogVisible.value = true
}

const closePasswordDialog = () => {
  isPasswordDialogVisible.value = false
  resetPasswordForm()
}

const savePassword = async () => {
  if (!passwordUserId.value || passwordSaving.value)
    return

  if (passwordForm.value?.validate) {
    const result = await passwordForm.value.validate()
    const isValid = typeof result === 'object' ? result.valid : result
    if (!isValid)
      return
  }

  passwordSaving.value = true
  try {
    const response = await userListStore.changepassowrdUser({
      password: newPassword.value,
      password_confirmation: confirmPassword.value,
    }, passwordUserId.value)

    if (response.data['status']) {
      closePasswordDialog()
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('password_changed_successfully'), 'success')
    }
  } catch (error) {
    const apiErrors = error.response?.data?.errors
    const rawMessage = error.response?.data?.message
    const message = apiErrors
      ? Object.values(apiErrors).flat().join('<br />')
      : (typeof rawMessage === 'string' && rawMessage.length < 200
        ? rawMessage
        : i18n.global.t('Failed to send reset link, Try again later.'))
    snackbarRef.value.exposevisibleSnackbar(message, 'error')
  } finally {
    passwordSaving.value = false
  }
}

const canImpersonateUser = user => {
  if (!isPlatformAdmin || user?.deleted_at || user?.id === currentUserData.id)
    return false
  if (user?.can_login === false)
    return false
  if (user?.roles?.some(role => role.default_name === 'admin'))
    return false

  return true
}

const openImpersonateDialog = user => {
  if (!canImpersonateUser(user))
    return

  impersonateTarget.value = user
  isImpersonateDialogVisible.value = true
}

const closeImpersonateDialog = () => {
  isImpersonateDialogVisible.value = false
  impersonateTarget.value = null
}

const confirmImpersonate = async () => {
  if (!impersonateTarget.value?.id || impersonateSaving.value)
    return

  impersonateSaving.value = true
  try {
    const response = await userListStore.impersonateUser(impersonateTarget.value.id)
    if (!response.data?.token || !response.data?.user)
      throw new Error('invalid_impersonation_response')

    saveImpersonatorSession({ impersonatedUserName: impersonateTarget.value.name })
    applyUserSession({
      token: response.data.token,
      user: response.data.user,
    })
    await router.replace('/')
  }
  catch (error) {
    impersonateSaving.value = false
    const apiErrors = error.response?.data?.errors
    const rawMessage = error.response?.data?.message
    const message = apiErrors
      ? Object.values(apiErrors).flat().join('<br />')
      : (typeof rawMessage === 'string' && rawMessage.length < 200
        ? rawMessage
        : i18n.global.t('You cannot impersonate this user.'))
    snackbarRef.value.exposevisibleSnackbar(message, 'error')
  }
}

watch([selectedRoles], () => {
  options.value.page = 1
});

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchUsers, {
  search: searchQuery,
  filters: () => [selectedCenter.value, selectedRoles.value, selectedStatus.value],
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

              <!-- 👉 Select Center -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedCenter"
                  :label="$t('centers.center')"
                  :items="centers"
                  :item-title="'title'"
                  :item-value="'id'"
                  clearable
                  clear-icon="tabler-x"
                  class="pa-1"
                >
                </AppSelect>
              </VCol>

              <!-- 👉 Select Role -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedRoles"
                  :label="$t('centers.roles')"
                  :items="roles"
                  :item-title="'name'"
                  :item-value="'id'"
                  clearable
                  chips
                  multiple
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
            class="dataTable-hidescroller-y"
            @update:options="onTableOptions"
          >

            <template #item.name="{ item }">
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
                {{ item.raw.name }}
              </div>
            </template>

            <template #item.centers="{ item }">
              <span style="font-size: 13px;" v-for="center in item.raw.centers">{{ center.title }}<br/></span>
            </template>

            <template #item.roles="{ item }">
              <span style="font-size: 13px;" v-for="role in item.raw.roles">{{ role.name }}<br/></span>
            </template>

            <template #item.information="{ item }">
              <span style="font-size: 13px;"><span style="color: #7374d3;">{{ $t('email') }}: </span>{{ item.raw.email }}<br/></span>
              <span style="font-size: 13px;"><span style="color: #7374d3;">{{ $t('phone') }}: </span>{{ item.raw.phone }}</span>
            </template>
          
          <!-- Active -->
          <template #item.active="{ item }">
            <VChip
              :color="resolveUserStatusVariant(item.raw.deleted_at ? 'inactive' : 'active' )"
              size="small"
              label
              class="text-capitalize"
            >
              {{ item.raw.deleted_at ? $t('Inactive') : $t('Active_user') }}
            </VChip>
          </template>

            <!-- Actions -->
            <template #item.actions="{ item }">
              <VBtn
                v-if="!item.raw.deleted_at && can('access_centers', 'access_centers')"
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
                    <VListItem @click="openPasswordDialog(item.raw)">
                      <template #prepend>
                        <VIcon icon="tabler-password" />
                      </template>
                      <VListItemTitle>{{ $t('Change Password') }}</VListItemTitle>
                    </VListItem>
                    <VListItem
                      v-if="canImpersonateUser(item.raw)"
                      @click="openImpersonateDialog(item.raw)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-login" />
                      </template>
                      <VListItemTitle>{{ $t('Login as User') }}</VListItemTitle>
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
                  :length="Math.ceil(totalUsers / options.itemsPerPage)"
                  total-visible="5"
                  :total-visible="$vuetify.display.xs ? 1 : Math.ceil(totalUsers / options.itemsPerPage)"
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

    <VDialog
      v-model="isPasswordDialogVisible"
      persistent
      class="v-dialog-sm"
    >
      <DialogCloseBtn @click="closePasswordDialog" />

      <VCard :title="$t('Change Password')">
        <VForm
          ref="passwordForm"
          autocomplete="off"
        >
          <VCardText>
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="newPassword"
                  autofocus
                  dir="ltr"
                  :label="$t('New Password')"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :rules="[requiredValidator, passwordValidator]"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model="confirmPassword"
                  :disabled="!newPassword"
                  dir="ltr"
                  :label="$t('Confirm Password')"
                  :type="isConfirmPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  :rules="[requiredValidator, confirmedValidator(newPassword, confirmPassword)]"
                  @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                />
              </VCol>
            </VRow>
          </VCardText>

          <VCardText class="d-flex justify-end gap-3 flex-wrap">
            <VBtn
              color="secondary"
              variant="tonal"
              @click="closePasswordDialog"
            >
              {{ $t('Cancel') }}
            </VBtn>
            <VBtn
              :loading="passwordSaving"
              :disabled="passwordSaving"
              @click="savePassword"
            >
              {{ $t('Save') }}
            </VBtn>
          </VCardText>
        </VForm>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isImpersonateDialogVisible"
      persistent
      class="v-dialog-sm"
    >
      <DialogCloseBtn @click="closeImpersonateDialog" />

      <VCard :title="$t('Login as User')">
        <VCardText>
          {{ $t('impersonate_confirm_message', { name: impersonateTarget?.name || '' }) }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn
            color="secondary"
            variant="tonal"
            :disabled="impersonateSaving"
            @click="closeImpersonateDialog"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="warning"
            :loading="impersonateSaving"
            :disabled="impersonateSaving"
            @click="confirmImpersonate"
          >
            {{ $t('Login as User') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

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