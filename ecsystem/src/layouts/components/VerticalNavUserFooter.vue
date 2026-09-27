<script setup>
import { useSessionStore } from '@/stores/useSessionStore'
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import { clearImpersonatorSession, returnToAdminAccount } from '@core/utils/impersonation'

const router = useRouter()
const sessionStore = useSessionStore()
const userListStore = useUserListStore()
const userData = computed(() => sessionStore.userData)

const displayName = computed(() => userData.value?.fullName || userData.value?.username || '')
const roleLabel = computed(() => userData.value?.role || '')

const logout = async () => {
  if (sessionStore.impersonating) {
    try {
      await userListStore.stopImpersonation()
    }
    catch (e) { /* ignore */ }
    clearImpersonatorSession()
  }

  sessionStore.clearSession()
  router.push('/login')
}

const returnToAdmin = async () => {
  const redirectTo = await returnToAdminAccount(() => userListStore.stopImpersonation())
  if (redirectTo)
    await router.replace(redirectTo)
}
</script>

<template>
  <div
    v-if="userData"
    class="athar-nav-user"
  >
    <VMenu
      location="top"
      offset="8"
      width="240"
    >
      <template #activator="{ props: menuProps }">
        <button
          type="button"
          class="athar-nav-user__trigger"
          v-bind="menuProps"
          @click.stop
        >
          <VAvatar
            size="36"
            :color="!userData.picture ? 'primary' : undefined"
            :variant="!userData.picture ? 'tonal' : undefined"
          >
            <VImg
              v-if="userData.picture"
              :src="userData.picture.file_url"
            />
            <VIcon
              v-else
              icon="tabler-user"
              size="20"
            />
          </VAvatar>
          <span class="athar-nav-user__text">
            <span class="athar-nav-user__name">{{ displayName }}</span>
            <span class="athar-nav-user__role">{{ roleLabel }}</span>
          </span>
          <VIcon
            icon="tabler-chevron-up"
            size="18"
            class="athar-nav-user__chevron"
          />
        </button>
      </template>

      <VList density="compact">
        <VListItem
          prepend-icon="tabler-user-circle"
          :title="$t('account.profile_title')"
          :to="{ name: 'account-profile' }"
        />
        <VListItem
          v-if="sessionStore.impersonating"
          prepend-icon="tabler-arrow-back-up"
          :title="$t('Return to Admin Account')"
          @click="returnToAdmin"
        />
        <VListItem
          prepend-icon="tabler-logout"
          :title="$t('Logout')"
          @click="logout"
        />
      </VList>
    </VMenu>
  </div>
</template>

<style lang="scss" scoped>
.athar-nav-user__trigger {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  inline-size: 100%;
  padding: 0.5rem 0.65rem;
  border: none;
  border-radius: 12px;
  background: transparent;
  cursor: pointer;
  text-align: start;
  transition: background 0.2s ease;

}

.athar-nav-user__text {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 0.1rem;
  min-inline-size: 0;
}

.athar-nav-user__name {
  overflow: hidden;
  font-size: 0.8125rem;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.athar-nav-user__role {
  overflow: hidden;
  font-size: 0.6875rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.athar-nav-user__chevron {
  flex-shrink: 0;
}

</style>
