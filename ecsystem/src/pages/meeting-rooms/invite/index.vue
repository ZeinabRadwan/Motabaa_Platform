<script setup>
import { roomsApi } from '@/plugins/apis/roomsReqest'
import { useSessionStore } from '@/stores/useSessionStore'
import i18n from '@/plugins/i18n/index.js'

const route = useRoute()
const router = useRouter()
const sessionStore = useSessionStore()
const roomsReqest = roomsApi()

const token = computed(() => String(route.query.token || ''))
const loading = ref(true)
const errorMessage = ref('')

const redeem = async () => {
  if (! token.value) {
    errorMessage.value = i18n.global.t('rooms.invitation_invalid')
    loading.value = false
    return
  }

  try {
    if (! sessionStore.isLoggedIn) {
      const statusRes = await roomsReqest.invitationStatus(token.value)
      if (! statusRes.data?.data?.valid) {
        errorMessage.value = i18n.global.t('rooms.invitation_invalid')
        loading.value = false
        return
      }

      await router.replace({
        name: 'login',
        query: { to: route.fullPath },
      })
      return
    }

    const redeemRes = await roomsReqest.redeemInvitation(token.value)
    const roomId = redeemRes.data?.data?.room_id
    if (roomId) {
      await router.replace(`/meeting-rooms/view/${roomId}`)
      return
    }

    errorMessage.value = i18n.global.t('rooms.invitation_invalid')
  }
  catch (e) {
    const msg = e.response?.data?.errors?.error?.[0]
    errorMessage.value = msg || i18n.global.t('rooms.invitation_invalid')
  }
  finally {
    loading.value = false
  }
}

onMounted(() => {
  redeem()
})
</script>

<template>
  <VRow class="justify-center mt-16">
    <VCol
      cols="12"
      md="6"
      lg="5"
    >
      <VCard>
        <VCardText class="text-center py-10">
          <VProgressCircular
            v-if="loading"
            indeterminate
            color="primary"
            class="mb-4"
          />
          <p
            v-else-if="errorMessage"
            class="text-body-1 text-error mb-0"
          >
            {{ errorMessage }}
          </p>
          <p
            v-else
            class="text-body-1 mb-0"
          >
            {{ $t('rooms.invitation_accepting') }}
          </p>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<route lang="yaml">
meta:
  meetingRoomInvite: true
</route>
