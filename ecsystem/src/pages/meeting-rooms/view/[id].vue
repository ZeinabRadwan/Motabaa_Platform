<script setup>
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import RoomBioPanel from '@/views/rooms/RoomBioPanel.vue'
import {roomsApi} from "@/plugins/apis/roomsReqest"
import MessageSend from '@/views/messages/MessageSend.vue';
import Message from '@/views/messages/Message.vue';
import {messagesApi} from "@/plugins/apis/messagesReqest"
import { useMessageSendHandler } from '@/composables/useMessageSendHandler'
import { usePaginatedSessionMessages } from '@/composables/usePaginatedSessionMessages'
import { can } from '@layouts/plugins/casl'
import { canParticipateInMeetingRoomChat } from '@core/utils/meetingRoomAccess'
import i18n from '@/plugins/i18n/index.js';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import { isUser } from "@core/utils/helper";


const messagesReqest = messagesApi()
const roomsReqest = roomsApi()
const route = useRoute()
const snackbarRef = ref(null);
const roomData = ref()
const accessDenied = ref(false)
const uploadPercentage = ref(0);

const options = ref({
  page: -1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
})
const errorsMessage = ref({
  file: undefined,
  image: undefined,
})

const loadRoom = () => {
  roomsReqest.show(Number(route.params.id)).then(response => {
    roomData.value = response.data.data
    fetchMessages()
  }).catch(error => {
    if (error.response?.status === 403)
      accessDenied.value = true
  })
}

loadRoom()

const onRoomUpdated = updated => {
  roomData.value = updated
}


const {
  messages,
  fetchMessages,
  loadOlderMessages,
  hasOlderMessages,
  loadingMore: messagesLoadingMore,
} = usePaginatedSessionMessages({
  fetchAll: params => messagesReqest.fetchAll(params),
  getParams: () => ({ meeting_room_id: route.params.id }),
  searchQuery: ref(''),
})


const sendMeesage = useMessageSendHandler({
  putFn: (data, onProgress) => messagesReqest.put(data, onProgress),
  applyPayload: data => { data.meeting_room_id = route.params.id },
  fetchMessages: () => fetchMessages(true),
  snackbarRef,
  errorsMessage,
  uploadPercentage,
})


const deleteMessage = (id, callback) => {
  messagesReqest.delete(id).then(() =>{
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('Deleted successfully.'), 'success');
    fetchMessages();
    callback()
  }).catch(error => {
    callback()
    errorsMessage.value = error.response?.data?.errors || {}
  })
}


</script>

<template>
  <VRow v-if="accessDenied">
    <VCol cols="12">
      <VAlert
        type="error"
        variant="tonal"
      >
        {{ $t('rooms.access_denied') }}
      </VAlert>
    </VCol>
  </VRow>
  <VRow v-else-if="roomData">
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <RoomBioPanel
        :room-data="roomData"
        @fetch-messages="fetchMessages"
        @room-updated="onRoomUpdated"
      />
    </VCol>

    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <MessageSend v-if="canParticipateInMeetingRoomChat()" :isMeeting="true" :errors="errorsMessage" @send-meesage="sendMeesage" :uploadPercentage="uploadPercentage" class="mb-4"></MessageSend>
      <div>
        <Message v-for="message in messages" :message="message" :key="message.id" :canDelete="can('admin_meetings','admin_meetings') || isUser(message.user_id)" @delete-message="deleteMessage" class="mb-4"></Message>
        <div
          v-if="hasOlderMessages"
          class="d-flex justify-center mb-4"
        >
          <VBtn
            variant="tonal"
            :loading="messagesLoadingMore"
            @click="loadOlderMessages"
          >
            {{ $t('messages.load_older_messages') }}
          </VBtn>
        </div>
      </div>
    </VCol> 
    <SnackbarComponent ref="snackbarRef" />
  </VRow>
</template>
<route lang="yaml">
  meta:
    meetingRoomAccess: true
</route>