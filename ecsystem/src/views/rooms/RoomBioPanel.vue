<script setup>
import {
avatarText,
} from '@core/utils/formatters'
import { can } from '@layouts/plugins/casl'
import {roomsApi} from "@/plugins/apis/roomsReqest"
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import i18n from '@/plugins/i18n/index.js';
import MeetingRoomAccessFields from '@/components/meeting-rooms/MeetingRoomAccessFields.vue'
import { useMeetingRoomInvitationLink } from '@/composables/useMeetingRoomInvitationLink'
import MeetingRoomAttendancePanel from '@/components/meeting-rooms/MeetingRoomAttendancePanel.vue'
import {
  meetingAudienceBadgeClass,
  meetingAudienceBadgeLabel,
} from '@/utils/meetingRoomAudienceUi'

const props = defineProps({
  roomData: {
    type: Object,
    required: true,
  },
})
const emit = defineEmits(['fetch-messages', 'room-updated']);

const route = useRoute()
const router = useRouter()
const roomsReqest = roomsApi()
const userListStore = useUserListStore()
const snackbarRef = ref(null);
const copyLinkText = ref('click_copy_link')
const isDialogVisibleDate = ref(false)
const editDialog = ref(false)
const saveLoading = ref(false)
const editTitle = ref('')
const editAudienceType = ref('general')
const lastInvitationUrl = ref('')
const {
  invitationLinkLoading,
  copyInvitationLink,
  regenerateInvitationLink,
} = useMeetingRoomInvitationLink({
  fetchInvitationLink: (roomId, rotate) => roomsReqest.fetchInvitationLink(roomId, rotate),
  snackbarRef,
  getRoomId: () => props.roomData?.id ?? 0,
  getCachedUrl: () => lastInvitationUrl.value,
  setCachedUrl: url => { lastInvitationUrl.value = url },
})
const editParentIds = ref([])
const regenerateConfirmOpen = ref(false)
const date = ref(null)
const time = ref(null)

const canManageRooms = computed(() => can('edit_meetings', 'edit_meetings') || can('admin_meetings', 'admin_meetings'))
const isParentSpecific = computed(() => editAudienceType.value === 'parents')
const isLinkInvite = computed(() => editAudienceType.value === 'link_invite')
const isLinkInviteRoom = computed(() => props.roomData?.audience_type === 'link_invite')
const assignedParentsList = computed(() => {
  if (Array.isArray(props.roomData?.assigned_parents) && props.roomData.assigned_parents.length)
    return props.roomData.assigned_parents
  return []
})

const normalizeAudienceType = value => {
  if (value === 'parents' || value === 'link_invite')
    return value
  return 'general'
}
const hasMeetingUrl = computed(() => !!props.roomData?.meeting_url)
const meetingUrlBase = computed(() => {
  if (!props.roomData?.meeting_url)
    return ''
  return props.roomData.meeting_url.split('?')[0]
})

const searchParents = params => userListStore.searchItems({
  ...params,
  status: 'all',
  onlyParents: true,
})

const parentOptionTitle = item => {
  const u = item?.raw ?? item
  if (!u?.name)
    return ''
  const phone = u.phone ? ` (${u.phone})` : ''

  return `${u.name}${phone}`
}

const today = new Date();
const yesterday = new Date(today);
yesterday.setDate(today.getDate() - 1);
const formattedYesterday= yesterday.toLocaleDateString('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
});

const deletedRoom = ref(props.roomData.deleted_at != null && props.roomData.deleted_at != '');
const joinedMeetingSession = ref(false)
const attendancePanelRef = ref(null)


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
const recordMeetingJoin = async () => {
  try {
    await roomsReqest.attendanceJoin(Number(props.roomData.id))
    joinedMeetingSession.value = true
    attendancePanelRef.value?.refresh?.()
  }
  catch {
    // Jitsi can still open if attendance API is unavailable
  }
}

const recordMeetingLeave = () => {
  if (! joinedMeetingSession.value)
    return
  joinedMeetingSession.value = false
  roomsReqest.attendanceLeave(Number(props.roomData.id)).catch(() => {})
}

const openUrl = async () => {
  if (!hasMeetingUrl.value)
    return
  await recordMeetingJoin()
  window.open(props.roomData.meeting_url, '_blank').focus()
}

onBeforeUnmount(() => {
  recordMeetingLeave()
})

const startNow = () => {
  if (!hasMeetingUrl.value)
    return
  roomsReqest.startNow({
    message: meetingUrlBase.value,
    meeting_room_id: props.roomData.id,
  }).then(response => {
    openUrl()
    emit('fetch-messages')
  })
}

const startLater = () => {
  if (!hasMeetingUrl.value)
    return
  roomsReqest.startLater({
    message: JSON.stringify({url : meetingUrlBase.value, time : time.value, date: date.value}),
    meeting_room_id: props.roomData.id,
  }).then(response => {
    emit('fetch-messages')
    isDialogVisibleDate.value = false

  })
}

const laterMeeting = () =>{
  if (!hasMeetingUrl.value)
    return
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
  if (!hasMeetingUrl.value)
    return
  navigator.clipboard.writeText(meetingUrlBase.value);
  copyLinkText.value = 'copied';
  setTimeout(() => {copyLinkText.value = 'click_copy_link';}, 2000);
}

const openEditDialog = () => {
  editTitle.value = props.roomData.title
  editAudienceType.value = normalizeAudienceType(props.roomData.audience_type)
  editParentIds.value = Array.isArray(props.roomData.assigned_parent_ids)
    ? [...props.roomData.assigned_parent_ids]
    : []
  lastInvitationUrl.value = ''
  editDialog.value = true
}

const saveEdit = () => {
  if (!editTitle.value?.trim()) {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.missing_field'), 'error');
    return
  }
  if (isParentSpecific.value && (!editParentIds.value || editParentIds.value.length === 0)) {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('rooms.parents_required'), 'error');
    return
  }

  saveLoading.value = true
  const payload = {
    title: editTitle.value.trim(),
    audience_type: editAudienceType.value,
    parent_ids: isParentSpecific.value ? editParentIds.value : [],
  }

  roomsReqest.put(payload, String(props.roomData.id)).then(response => {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success');
    const invitationUrl = response.data?.data?.invitation_url
    if (invitationUrl)
      lastInvitationUrl.value = invitationUrl
    if (! isLinkInvite.value)
      editDialog.value = false
    return roomsReqest.show(Number(props.roomData.id))
  }).then(response => {
    if (response?.data?.data)
      emit('room-updated', response.data.data)
  }).catch(error => {
    const msg = error.response?.data?.errors?.parent_ids?.[0]
      || error.response?.data?.errors?.error?.[0]
    if (msg)
      snackbarRef.value.exposevisibleSnackbar(msg, 'error');
  }).finally(() => {
    saveLoading.value = false
  })
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
      <VCard
        v-if="props.roomData"
        class="meeting-room-detail"
        elevation="0"
      >
        <div class="meeting-room-detail__hero">
          <span class="meeting-room-detail__hero-icon">
            <VIcon
              icon="tabler-video"
              size="34"
            />
          </span>
          <h2 class="meeting-room-detail__title">
            {{ props.roomData.title }}
          </h2>
          <div class="meeting-room-detail__chips">
            <VChip
              size="small"
              label
              :color="resolveUserStatusVariant(deletedRoom? 'inactive' : 'active' )"
            >
              {{ deletedRoom? $t('Inactive') :$t('Active_user') }}
            </VChip>
            <VChip
              size="small"
              label
              variant="tonal"
            >
              {{ meetingAudienceBadgeLabel(props.roomData.audience_type) }}
            </VChip>
          </div>
        </div>

        <div class="meeting-room-detail__meta">
          <div class="meeting-room-detail__meta-row">
            <span class="meeting-room-detail__meta-label">{{ $t('rooms.create_by') }}</span>
            <div class="d-flex align-center gap-2 meeting-room-detail__meta-value">
              <VAvatar
                size="32"
                variant="tonal"
              >
                <VImg
                  v-if="props.roomData.user.picture"
                  :src="props.roomData.user.picture.file_url"
                />
                <span v-else>{{ avatarText(props.roomData.user.name) }}</span>
              </VAvatar>
              {{ props.roomData.user.name }}
            </div>
          </div>
          <div class="meeting-room-detail__meta-row">
            <span class="meeting-room-detail__meta-label">{{ $t('rooms.audience_type') }}</span>
            <span
              class="meeting-room-card__badge meeting-room-detail__meta-value"
              :class="meetingAudienceBadgeClass(props.roomData.audience_type)"
            >
              {{ props.roomData.audience_type_name || meetingAudienceBadgeLabel(props.roomData.audience_type) }}
            </span>
          </div>
          <div
            v-if="props.roomData.audience_type === 'parents' && assignedParentsList.length"
            class="meeting-room-detail__meta-row meeting-room-detail__meta-row--stack"
          >
            <span class="meeting-room-detail__meta-label">{{ $t('rooms.target_parents') }}</span>
            <div class="d-flex flex-wrap gap-2 meeting-room-detail__meta-value">
              <VChip
                v-for="parent in assignedParentsList"
                :key="parent.id"
                size="small"
                label
                variant="tonal"
              >
                {{ parent.name }}
              </VChip>
            </div>
          </div>
        </div>

        <VAlert
          v-if="isLinkInviteRoom && canManageRooms"
          type="warning"
          variant="tonal"
          density="compact"
          class="meeting-room-detail__invite-banner"
        >
          {{ $t('rooms.audience_hint_link_invite') }}
        </VAlert>

        <div
          v-if="isLinkInviteRoom && canManageRooms"
          class="meeting-access-fields__invite-actions meeting-room-detail__invite-banner d-flex flex-wrap gap-2"
        >
          <VBtn
            size="small"
            variant="tonal"
            prepend-icon="tabler-copy"
            :loading="invitationLinkLoading"
            @click="copyInvitationLink"
          >
            {{ $t('rooms.copy_invitation_link') }}
          </VBtn>
          <VBtn
            size="small"
            variant="outlined"
            prepend-icon="tabler-refresh"
            :loading="invitationLinkLoading"
            @click="regenerateConfirmOpen = true"
          >
            {{ $t('rooms.regenerate_invitation_link') }}
          </VBtn>
        </div>

        <div class="meeting-room-detail__actions">
          <VBtn
            v-if="canManageRooms"
            variant="tonal"
            @click="openEditDialog"
          >
            {{ $t('rooms.edit_meeting_room') }}
          </VBtn>
          <VMenu v-if="!can('parent','parent') && hasMeetingUrl">
          <template #activator="{ props: menuProps }">
              <VBtn
                v-bind="menuProps"
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
            v-else-if="can('parent','parent') && hasMeetingUrl"
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
        </div>
        <div
          v-if="hasMeetingUrl"
          class="meeting-room-detail__link-card"
        >
          <div class="meeting-room-detail__link-head">
            <span class="meeting-room-detail__link-label">{{ $t('rooms.meeting_link_label') }}</span>
            <VBtn
              size="x-small"
              variant="text"
              icon="tabler-copy"
              @click="copyLink"
            />
          </div>
          <span
            class="meeting-room-detail__link-url"
            @click="openUrl"
          >{{ meetingUrlBase }}</span>
        </div>
      </VCard>
      <MeetingRoomAttendancePanel
        ref="attendancePanelRef"
        :room-id="Number(props.roomData.id)"
      />
    </VCol>
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

    <VDialog
      v-model="editDialog"
      persistent
      max-width="560"
    >
      <VCard
        v-if="editDialog"
        :title="$t('rooms.edit_meeting_room')"
      >
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="editTitle"
                :label="$t('rooms.room_name')"
              />
            </VCol>
            <VCol cols="12">
              <MeetingRoomAccessFields
                v-model:audience-type="editAudienceType"
                v-model:selected-parent-ids="editParentIds"
                :search-parents="searchParents"
                :parent-option-title="parentOptionTitle"
                :show-invitation-actions="isLinkInvite && canManageRooms"
                :invitation-link-loading="invitationLinkLoading"
                @copy-invitation-link="copyInvitationLink"
                @regenerate-invitation-link="regenerateInvitationLink"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn
            color="secondary"
            variant="tonal"
            :disabled="saveLoading"
            @click="editDialog = false"
          >
            {{ $t('Close') }}
          </VBtn>
          <VBtn
            color="success"
            :loading="saveLoading"
            @click="saveEdit"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <VDialog
      v-model="regenerateConfirmOpen"
      max-width="480"
    >
      <VCard :title="$t('rooms.regenerate_invitation_link')">
        <VCardText>
          {{ $t('rooms.regenerate_invitation_confirm') }}
        </VCardText>
        <VCardActions class="justify-end gap-2 pa-4 pt-0">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="regenerateConfirmOpen = false"
          >
            {{ $t('Close') }}
          </VBtn>
          <VBtn
            color="warning"
            @click="regenerateConfirmOpen = false; regenerateInvitationLink()"
          >
            {{ $t('rooms.regenerate_invitation_link') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <SnackbarComponent ref="snackbarRef" />
  </VRow>

</template>

<style lang="scss">
@import '@/styles/meeting-rooms-list.scss';
@import '@/styles/meeting-room-detail.scss';
</style>
