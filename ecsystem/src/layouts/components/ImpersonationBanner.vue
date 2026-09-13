<script setup>
import {
  getImpersonatorSession,
  returnToAdminAccount,
} from '@core/utils/impersonation'
import { useSessionStore } from '@/stores/useSessionStore'
import { useUserListStore } from '@/views/apps/user/useUserListStore'

const sessionStore = useSessionStore()
const userListStore = useUserListStore()
const router = useRouter()
const returning = ref(false)
const visible = computed(() => sessionStore.impersonating || !!getImpersonatorSession())
const impersonatedName = computed(() => {
  const impersonatedUser = sessionStore.userData

  return impersonatedUser?.fullName || impersonatedUser?.name || impersonatedUser?.username || ''
})

const returnToAdmin = async () => {
  if (returning.value)
    return

  returning.value = true
  const redirectTo = await returnToAdminAccount(() => userListStore.stopImpersonation())
  if (redirectTo)
    await router.replace(redirectTo)
  returning.value = false
}
</script>

<template>
  <div
    v-if="visible"
    class="d-flex align-center flex-wrap gap-2 me-3"
  >
    <VChip
      color="warning"
      variant="elevated"
      size="small"
      class="font-weight-medium"
    >
      {{ $t('Viewing as') }}: {{ impersonatedName }}
    </VChip>
    <VBtn
      color="warning"
      size="small"
      :loading="returning"
      :disabled="returning"
      prepend-icon="tabler-arrow-back-up"
      @click="returnToAdmin"
    >
      {{ $t('Return to Admin Account') }}
    </VBtn>
  </div>
</template>
