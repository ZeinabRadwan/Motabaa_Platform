<script setup>
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import RoomBioPanel from '@/views/rooms/RoomBioPanel.vue'
import {roomsApi} from "@/plugins/apis/roomsReqest"
import MessageSend from '@/views/messages/MessageSend.vue';
import Message from '@/views/messages/Message.vue';
import {messagesApi} from "@/plugins/apis/messagesReqest"
import { can } from '@layouts/plugins/casl'
import i18n from '@/plugins/i18n/index.js';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import { isUser } from "@core/utils/helper";


const messagesReqest = messagesApi()
const roomsReqest = roomsApi()
const route = useRoute()
const snackbarRef = ref(null);
const roomData = ref()
const messages = ref([])
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

roomsReqest.show(Number(route.params.id)).then(response => {
  roomData.value = response.data.data
  fetchMessages()
})


const fetchMessages = () => {
  messagesReqest.fetchAll({
      meeting_room_id: route.params.id,
      options: options.value,
      page: options.value.page,
  }).then(response => {
    messages.value = response.data.data;
  }).catch(error => {
    console.error(error)
  })
}


const sendMeesage = (data, callback) => {
  data['meeting_room_id'] = route.params.id
  messagesReqest.put(data, (progress)=>{
      uploadPercentage.value = progress
  }).then(() =>{
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success');
    fetchMessages();
    callback()
  }).catch(error => {
    callback()
    errorsMessage.value = error.response.data.errors
  })
}


const deleteMessage = (id, callback) => {
  messagesReqest.delete(id).then(() =>{
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('Deleted successfully.'), 'success');
    fetchMessages();
    callback()
  }).catch(error => {
    callback()
    errorsMessage.value = error.response.data.errors
  })
}


</script>

<template>
  <VRow v-if="roomData">
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <RoomBioPanel :room-data="roomData" @fetch-messages="fetchMessages" />
    </VCol>

    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <MessageSend v-if="can('show_meetings','show_meetings')" :isMeeting="true" :errors="errorsMessage" @send-meesage="sendMeesage" :uploadPercentage="uploadPercentage" class="mb-4"></MessageSend>
      <div>
        <Message v-for="message in messages" :message="message" :key="message.id" :canDelete="can('admin_meetings','admin_meetings') || isUser(message.user_id)" @delete-message="deleteMessage" class="mb-4"></Message>
      </div>
    </VCol> 
    <SnackbarComponent ref="snackbarRef" />
  </VRow>
</template>
<route lang="yaml">
  meta:
    action: show_meetings
    subject: show_meetings
</route>