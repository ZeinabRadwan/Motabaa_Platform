<script setup>
import { centersApi } from "@/plugins/apis/centersRequest"
import { useSessionStore } from '@/stores/useSessionStore'
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import {
  clearImpersonatorSession,
  returnToAdminAccount,
} from '@core/utils/impersonation'
import i18n from '@/plugins/i18n/index.js'
import { PerfectScrollbar } from 'vue3-perfect-scrollbar'

const router = useRouter()
const sessionStore = useSessionStore()
const userListStore = useUserListStore()
const centerListStore = centersApi()
const userData = computed(() => sessionStore.userData)
const centers = computed(() => sessionStore.userCenters)

watch(() => sessionStore.userData?.id, userId => {
  if (!userId || sessionStore.userCenters)
    return

  centerListStore.fetchCenters({ user_id: userId }).then(response => {
    sessionStore.setUserCenters(response.data.data)
  }).catch(() => {
  })
}, { immediate: true })

const logout = async () => {
  if (sessionStore.impersonating) {
    try {
      await userListStore.stopImpersonation()
    }
    catch (e) {
    }
    clearImpersonatorSession()
  }

  sessionStore.clearSession()
  router.push('/login')
}

const changeCenter = selectedCenter => {
  if (sessionStore.center != selectedCenter.id)
    sessionStore.setCenter(selectedCenter)

  if (userData.value?.permissions?.find(item => item.name === 'show_centers'))
    router.push('/centers/view/' + Number(selectedCenter.id))
  else
    router.push('/')
}

const returnToAdmin = async () => {
  const redirectTo = await returnToAdminAccount(() => userListStore.stopImpersonation())
  if (redirectTo)
    await router.replace(redirectTo)
}

const userProfileList = computed(() => [
  ...(sessionStore.impersonating
    ? [{
        type: 'navItem',
        icon: 'tabler-arrow-back-up',
        title: i18n.global.t('Return to Admin Account'),
        onClick: returnToAdmin,
      }]
    : []),
  {
    type: 'navItem',
    icon: 'tabler-logout',
    title: 'Logout',
    onClick: logout,
  },
])
</script>

<template>
  <VAvatar
    v-if="userData"
    class="cursor-pointer"
    :color="!userData.avatar ? 'primary' : undefined"
    :variant="!userData.avatar ? 'tonal' : undefined"
  >
    <VImg
      v-if="userData.picture"
      :src="userData.picture.file_url"
    />
    <VIcon
      v-else
      icon="tabler-user"
    />

    <!-- SECTION Menu -->
    <VMenu
      activator="parent"
      width="230"
      location="bottom end"
      offset="14px"
    >
      <VList>
        <VListItem>
          <template #prepend>
            <VListItemAction start>
              <VAvatar
                :color="!userData.avatar ? 'primary' : undefined"
                :variant="!userData.avatar ? 'tonal' : undefined"
              >
                <VImg
                  v-if="userData.picture"
                  :src="userData.picture.file_url"
                />
                <VIcon
                  v-else
                  icon="tabler-user"
                />
              </VAvatar>
            </VListItemAction>
          </template>

          <VListItemTitle class="font-weight-medium">
            <RouterLink
              :to="{ name: 'user-view-id', params: { id: userData.id } }"
              class="name-route"
            >
              {{ userData.fullName || userData.username }}
            </RouterLink>
          </VListItemTitle>
          <VListItemSubtitle>{{ userData.role }}</VListItemSubtitle>
        </VListItem>

        <VListItem
          v-for="center in centers || []"
          :key="center.id"
        >
          <template #prepend>
            <VListItemAction start>
              <VAvatar>
                <VImg
                  v-if="center && center.logo"
                  :src="center.logo.file_url"
                />
              </VAvatar>
            </VListItemAction>
          </template>

          <VListItemTitle
            class="font-weight-medium cursor-pointer"
            style="font-size: 13px;text-wrap: wrap;"
            @click="changeCenter(center)"
          >
            {{ center.title || '' }}
          </VListItemTitle>
        </VListItem>

        <PerfectScrollbar :options="{ wheelPropagation: false }">
          <template
            v-for="item in userProfileList"
            :key="item.title"
          >
            <VListItem
              v-if="item.type === 'navItem'"
              :to="item.to"
              @click="item.onClick && item.onClick()"
            >
              <template #prepend>
                <VIcon
                  class="me-2"
                  :icon="item.icon"
                  size="22"
                />
              </template>

              <VListItemTitle>{{ item.title }}</VListItemTitle>

              <template
                v-if="item.badgeProps"
                #append
              >
                <VBadge v-bind="item.badgeProps" />
              </template>
            </VListItem>

            <VDivider
              v-else
              class="my-2"
            />
          </template>
        </PerfectScrollbar>
      </VList>
    </VMenu>
    <!-- !SECTION -->
  </VAvatar>
</template>
