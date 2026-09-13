<script setup>
import {
avatarText,
kFormatter,
} from '@core/utils/formatters'
import { can } from '@layouts/plugins/casl'
import {roomsApi} from "@/plugins/apis/roomsReqest"
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import {
    meetingRoomsTypes,
} from '@core/utils/generalItems';

const props = defineProps({
  roomData: {
    type: Object,
    required: true,
  },
})
const emit = defineEmits();

const route = useRoute()
const router = useRouter()
const roomsReqest = roomsApi()
const userListStore = useUserListStore()
const copyLinkText = ref('click_copy_link')
const isDialogVisibleDate = ref(false)
const date = ref(null)
const time = ref(null)

const today = new Date();
const yesterday = new Date(today);
yesterday.setDate(today.getDate() - 1);
const formattedYesterday= yesterday.toLocaleDateString('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
});

const deletedRoom = ref(props.roomData.deleted_at != null && props.roomData.deleted_at != '');


const resolveUserStatusVariant = stat => {
  if (stat === 'pending')
    return 'warning'
  if (stat === 'active')
    return 'success'
  if (stat === 'inactive')
    return 'secondary'
  
  return 'primary'
}


const deleteRoom = () => {
  roomsReqest.delete(Number(route.params.id)).then(response => {
    deletedRoom.value = true
  })
}

const restoreRoom = () => {
  roomsReqest.restore(Number(route.params.id)).then(response => {
    deletedRoom.value = false
  })
}
const openUrl = () => {
  window.open(props.roomData.meeting_url, '_blank').focus();
}

const startNow = () => {
  roomsReqest.startNow({
    message: props.roomData.meeting_url.split('?')[0],
    meeting_room_id: props.roomData.id,
  }).then(response => {
    openUrl()
    emit('fetch-messages')
  })
}

const startLater = () => {
  roomsReqest.startLater({
    message: JSON.stringify({url : props.roomData.meeting_url.split('?')[0], time : time.value, date: date.value}),
    meeting_room_id: props.roomData.id,
  }).then(response => {
    emit('fetch-messages')
    isDialogVisibleDate.value = false

  })
}

const laterMeeting = () =>{
  const today = new Date();
  const tomorrow = new Date(today);
  tomorrow.setDate(today.getDate() + 1);
  const formattedTomorrow = tomorrow.toLocaleDateString('en-CA', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
  });
  const hours = today.getHours();
  const minutes = today.getMinutes();
  const currentTimeInHiFormat = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
  const currentDateInYmdFormat = `${today.getFullYear()}-${(today.getMonth() + 1).toString().padStart(2, '0')}-${today.getDate().toString().padStart(2, '0')}`;
  time.value = currentTimeInHiFormat
  date.value = currentDateInYmdFormat
  isDialogVisibleDate.value = true
}


const copyLink = () => {
  navigator.clipboard.writeText(props.roomData.meeting_url.split('?')[0]);
  copyLinkText.value = 'copied';
  setTimeout(() => {copyLinkText.value = 'click_copy_link';}, 2000);
}

if (props.roomData.user && !props.roomData.user.picture) {
  userListStore.fetchImage(props.roomData.user.id).then(response => {
    if(response.data.data) {
      props.roomData.user.picture = response.data.data
    }
  })
}

</script>

<template>
  <VRow>
    <!-- SECTION User Details --> 
    <VCol cols="12">
      <VCard v-if="props.roomData">
        <VCardText class="text-center pt-15">
          <!-- 👉 Avatar -->
          <VAvatar
            rounded
            :size="100"
            :color="!props.roomData.avatar ? 'primary' : undefined"
            :variant="!props.roomData.avatar ? 'tonal' : undefined"
          >
            <VImg
              v-if="props.roomData.user.picture"
              :src="props.roomData.user.picture.file_url"
            />
            <span
              v-else
              class="text-2xl font-weight-medium"
            >
              {{ avatarText(props.roomData.user.name) }}
            </span>
          </VAvatar>


          <!-- 👉 Role chip -->
          <!-- <VChip
            label
            :color="resolveUserRoleVariant(props.roomData.role).color"
            size="small"
            class="text-capitalize mt-3"
          >
            {{ props.roomData.role }}
          </VChip> -->
        </VCardText>

        <VCardText>
          <!-- 👉 User Details list -->
          <VList class="card-list mt-2">
            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6">
                  {{$t('Name')}}:
                  <span class="text-body-1">
                    {{ props.roomData.title }}
                  </span>
                </h6>
              </VListItemTitle>
            </VListItem>

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6">
                  {{$t('rooms.create_by')}}:
                  <span class="text-body-1">
                    {{ props.roomData.user.name }}
                  </span>
                </h6>
              </VListItemTitle>
            </VListItem>

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6">
                  {{$t('rooms.room_type')}}:
                  <span class="text-body-1">
                    {{ props.roomData.type_name }}
                  </span>
                </h6>
              </VListItemTitle>
            </VListItem>

            <VListItem>
              <VListItemTitle>
                <h6 class="text-h6">
                  {{$t('Status')}}:
                  <VChip
                    :color="resolveUserStatusVariant(deletedRoom? 'inactive' : 'active' )"
                    size="small"
                    label
                    class="text-capitalize"
                  >
                  {{ deletedRoom? $t('Inactive') :$t('Active_user') }}
                </VChip>
                </h6>
              </VListItemTitle>
            </VListItem>


          </VList>
        </VCardText>
        <VCardText class="d-flex justify-center gap-2">
          <VMenu v-if="!can('parent','parent')">
          <template #activator="{ props }">
              <VBtn
                v-bind="props"
              >
                {{ $t('rooms.start_online_conversation') }}
              </VBtn>
            </template>
            <VList> 
              <VListItem @click="startNow" >{{  $t('rooms.now') }}</VListItem>
              <VListItem @click="laterMeeting">{{  $t('rooms.later') }}</VListItem>
            </VList>
          </VMenu>
          <VBtn
            v-else
            @click="openUrl"
          >
            {{ $t('rooms.start_online_conversation') }}
          </VBtn>
          <VBtn
            variant="tonal"
            color="error"
            @click="deleteRoom"
            v-if="!deletedRoom && can('admin_meetings','admin_meetings')"
          >
            {{ $t('delete') }}
          </VBtn>
          <VBtn
            variant="tonal"
            color="success"
            @click="restoreRoom"
            v-if="deletedRoom && can('admin_meetings','admin_meetings')"
          >
            {{ $t('Active') }}
          </VBtn>
        </VCardText>
        <VCardText class="d-flex justify-center gap-2 pointer-cursor">
          <div class="mb-1" >
            <VIcon @click="copyLink" icon="tabler-clipboard" />
            <VTooltip
              activator="parent"
              location="bottom"
            >
              {{$t(copyLinkText)}}
            </VTooltip>
          </div>
          <span @click="openUrl" class="text-primary text-h6 font-weight-bold mt-1">{{ props.roomData.meeting_url.split('?')[0] }}</span>
        </VCardText>
      </VCard>
    </VCol>
    <!-- !SECTION -->

    <!-- !SECTION -->

    <VDialog
      v-model="isDialogVisibleDate"
      width="500"
      persistent
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isDialogVisibleDate = !isDialogVisibleDate" persistent />

      <!-- Dialog Content -->
      <VCard :title="$t('rooms.choose_time')">
        <VCardText>
          <VForm >
            <VRow>
              <VCol
                  cols="12"
                  sm="12"
                > 
                  <AppDateTimePicker
                    v-model="time"
                    label=""
                    :config="{ enableTime: true, minuteIncrement: 1, noCalendar: true, dateFormat: 'H:i' }"
                  />
                </VCol>
                <VCol
                  cols="12"
                  sm="12"
                > 
                  <AppDateTimePicker
                    v-model="date"
                    label=""
                    :config="{ enableTime: true, dateFormat: 'Y-m-d', disable: [{ from: `1900-12-30`, to: `${formattedYesterday}` }] }"
                  />
                </VCol>
              </VRow>
            </VForm>
          </VCardText>

        <VCardText class="d-flex justify-end">
          <VBtn @click="startLater">
            {{ $t('Save') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
    
  </VRow>

</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 0.75rem;
}

.text-capitalize {
  text-transform: capitalize !important;
}
</style>
