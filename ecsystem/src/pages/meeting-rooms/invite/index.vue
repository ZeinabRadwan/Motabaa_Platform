<script setup>
import { roomsApi } from '@/plugins/apis/roomsReqest'
import { useSessionStore } from '@/stores/useSessionStore'
import { buildLoginLocation } from '@core/utils/loginRedirect'
import i18n from '@/plugins/i18n/index.js'

const route = useRoute()
const router = useRouter()
const sessionStore = useSessionStore()
const roomsReqest = roomsApi()

const token = computed(() => String(route.query.token || ''))
const loading = ref(true)
const errorMessage = ref('')
const needsLogin = ref(false)

const goToLogin = () => {
  router.push(buildLoginLocation(route.fullPath))
}

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

      needsLogin.value = true
      loading.value = false
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
    const status = e.response?.status
    if (status === 401) {
      needsLogin.value = true
      loading.value = false
      return
    }

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
  <div class="meeting-rooms-invite">
    <div class="meeting-rooms-invite__card">
      <div class="meeting-rooms-invite__hero">
        <span class="meeting-rooms-invite__icon">
          <VIcon
            icon="tabler-link"
            size="30"
          />
        </span>
        <h1 class="meeting-rooms-invite__title">
          {{ $t('rooms.invite_page_title') }}
        </h1>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ $t('rooms.invite_page_subtitle') }}
        </p>
      </div>
      <div class="meeting-rooms-invite__body">
        <VProgressCircular
          v-if="loading"
          indeterminate
          color="primary"
          size="44"
          class="mb-3"
        />
        <VAlert
          v-else-if="errorMessage"
          type="error"
          variant="tonal"
          class="text-start"
        >
          {{ errorMessage }}
        </VAlert>
        <div
          v-else-if="needsLogin"
          class="text-center"
        >
          <p class="text-body-1 mb-4">
            {{ $t('rooms.invite_sign_in_prompt') }}
          </p>
          <VBtn
            color="primary"
            @click="goToLogin"
          >
            {{ $t('Login') }}
          </VBtn>
        </div>
        <p
          v-else
          class="text-body-1 mb-0"
        >
          {{ $t('rooms.invitation_accepting') }}
        </p>
      </div>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  layout: blank
  meetingRoomInvite: true
</route>

<style lang="scss">
@import '@/styles/meeting-room-detail.scss';
</style>
