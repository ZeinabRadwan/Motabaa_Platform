<script setup>
import i18n from '@/plugins/i18n/index.js';
import { cuntriesIndexed } from "@core/utils/cuntries";
import {
avatarText,
kFormatter,
} from '@core/utils/formatters'
import { can } from '@layouts/plugins/casl'
import { useUserListStore } from '@/views/apps/user/useUserListStore'

const props = defineProps({
  userData: {
    type: Object,
    required: true,
  },
})

const route = useRoute()
const router = useRouter()
const standardPlan = {
  plan: 'Standard',
  price: 99,
  benefits: [
    '10 Users',
    'Up to 10GB storage',
    'Basic Support',
  ],
}
const userListStore = useUserListStore()

const deletedUser = ref(props.userData.deleted_at != null && props.userData.deleted_at != '');


const resolveUserStatusVariant = stat => {
  if (stat === 'pending')
    return 'warning'
  if (stat === 'active')
    return 'success'
  if (stat === 'inactive')
    return 'secondary'
  
  return 'primary'
}


const deleteUser = () => {
  userListStore.deleteUser(Number(route.params.id)).then(response => {
    deletedUser.value = true
  })
}

const restoreUser = () => {
  userListStore.restoreUser(Number(route.params.id)).then(response => {
    deletedUser.value = false
  })
}

if (!props.userData.picture) {
  userListStore.fetchImage(Number(route.params.id)).then(response => {
    if(response.data.data) {
      props.userData.picture = response.data.data
    }
  })
}

const nationalityLabel = computed(() => {
  const code = props.userData.nationality
  if (!code) return ''

  const country = cuntriesIndexed[code]
  if (!country) return code

  return country[i18n.global.locale.value == 'ar' ? 'country_arNationality' : 'country_enNationality']
})
</script>

<template>
  <VRow>
    <!-- SECTION User Details -->
    <VCol cols="12">
      <VCard v-if="props.userData">
        <VCardText class="text-center pt-15">
          <!-- 👉 Avatar -->
          <VAvatar
            rounded
            :size="100"
            :color="!props.userData.avatar ? 'primary' : undefined"
            :variant="!props.userData.avatar ? 'tonal' : undefined"
          >
            <VImg
              v-if="props.userData.picture"
              :src="props.userData.picture.file_url"
            />
            <span
              v-else
              class="text-5xl font-weight-medium"
            >
              {{ avatarText(props.userData.name) }}
            </span>
          </VAvatar>


          <!-- 👉 Role chip -->
          <!-- <VChip
            label
            :color="resolveUserRoleVariant(props.userData.role).color"
            size="small"
            class="text-capitalize mt-3"
          >
            {{ props.userData.role }}
          </VChip> -->
        </VCardText>

        <VCardText>
          <!-- 👉 User Details list -->
          <VList class="card-list mt-2">
            <VListItem>
              <h6 class="text-h6">
                {{$t('Name')}}:
                <span class="text-body-1">
                  {{ props.userData.name }}
                </span>
              </h6>
            </VListItem>

            <VListItem>
              <h6 class="text-h6">
                {{$t('Email')}}:
                <span class="text-body-1">{{ props.userData.email }}</span>
              </h6>
            </VListItem>

            <VListItem v-if="nationalityLabel">
              <h6 class="text-h6">
                {{$t('nationality')}}:
                <span class="text-body-1">{{ nationalityLabel }}</span>
              </h6>
            </VListItem>

            <VListItem>
              <h6 class="text-h6">
                {{$t('Status')}}:
                <VChip
                  :color="resolveUserStatusVariant(deletedUser? 'inactive' : 'active' )"
                  size="small"
                  label
                  class="text-capitalize"
                >
                  {{ deletedUser? $t('Inactive') :$t('Active_user') }}
                </VChip>
              </h6>
            </VListItem>

            <VListItem>
                {{ $t('Roles') }}:
                <span class="text-capitalize text-body-1" v-html="props.userData.roles.map(obj => obj.name).toString().replaceAll(',', ', ')"></span>
            </VListItem>

          </VList>
        </VCardText>

        <!-- 👉 Edit and Suspend button -->
        <VCardText class="d-flex justify-center">
          <div class="demo-space-x">
            <VBtn
              v-if="can('edit_users','edit_users')"
              variant="elevated"
              class="me-4"
              :to="{ name: ((props.userData.from_view=='parent') ? 'parents-edit-id' : 'user-edit-id'), params:{ id:props.userData.id }, query:{ to:route.path} }"
            >
              {{ $t('Edit') }}
            </VBtn>

            <VBtn
              variant="tonal"
              class="me-4"
              color="error"
              @click="deleteUser"
              v-if="!deletedUser && can('admin_users','admin_users')"
            >
              {{ $t('delete') }}
            </VBtn>
            <VBtn
              variant="tonal"
              class="me-4"
              color="success"
              @click="restoreUser"
              v-if="deletedUser && can('admin_users','admin_users')"
            >
              {{ $t('Active') }}
            </VBtn>
            <VBtn
              variant="tonal"
              class="me-4"
              color="info"
              v-if="!props.userData.deleted_at && can('admin_users','admin_users')" 
              @click="()=> router.push('/user/change-password/'+props.userData.id + '?to='+route.path)"
            >
              {{ $t('Change Password') }}
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VCol>
    <!-- !SECTION -->

    <!-- !SECTION -->
  </VRow>

  <!-- 👉 Edit user info dialog -->
  <UserInfoEditDialog
    v-model:isDialogVisible="isUserInfoEditDialogVisible"
    :user-data="props.userData"
  />

  <!-- 👉 Upgrade plan dialog -->
  <UserUpgradePlanDialog v-model:isDialogVisible="isUpgradePlanDialogVisible" />
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 0.75rem;
}

.text-capitalize {
  text-transform: capitalize !important;
}
</style>
