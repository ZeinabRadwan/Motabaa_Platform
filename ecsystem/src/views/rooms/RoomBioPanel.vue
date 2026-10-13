<script setup>
import {
avatarText,
} from '@core/utils/formatters'
import { can } from '@layouts/plugins/casl'
import {roomsApi} from "@/plugins/apis/roomsReqest"
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import i18n from '@/plugins/i18n/index.js';
import {
    meetingRoomsTypes,
    meetingRoomAudienceTypes,
} from '@core/utils/generalItems';

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
const editType = ref('')
const editAudienceType = ref('general')
const editParentIds = ref([])
const date = ref(null)
const time = ref(null)

const canManageRooms = computed(() => can('edit_meetings', 'edit_meetings') || can('admin_meetings', 'admin_meetings'))
const isParentSpecific = computed(() => editAudienceType.value === 'parents')
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
  if (!hasMeetingUrl.value)
    return
  window.open(props.roomData.meeting_url, '_blank').focus();
}

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
  editType.value = String(props.roomData.type)
  editAudienceType.value = props.roomData.audience_type === 'parents' ? 'parents' : 'general'
  editParentIds.value = Array.isArray(props.roomData.assigned_parent_ids)
    ? [...props.roomData.assigned_parent_ids]
    : []
  editDialog.value = true
}

const saveEdit = () => {
  if (!editTitle.value?.trim() || !editType.value) {
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
    type: editType.value,
    audience_type: editAudienceType.value,
    parent_ids: isParentSpecific.value ? editParentIds.value : [],
  }

  roomsReqest.put(payload, String(props.roomData.id)).then(() => {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success');
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
                  {{ $t('rooms.audience_type') }}:
                  <VChip
                    size="small"
                    label
                    class="ms-1"
                    :color="props.roomData.audience_type === 'parents' ? 'primary' : 'secondary'"
                  >
                    {{ props.roomData.audience_type_name || $t('rooms.audience_badge_general') }}
                  </VChip>
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
        <VCardText class="d-flex justify-center gap-2 flex-wrap">
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
        </VCardText>
        <VCardText
          v-if="hasMeetingUrl"
          class="d-flex justify-center gap-2 pointer-cursor"
        >
          <div class="mb-1" >
            <VIcon @click="copyLink" icon="tabler-clipboard" />
            <VTooltip
              activator="parent"
              location="bottom"
            >
              {{$t(copyLinkText)}}
            </VTooltip>
          </div>
          <span @click="openUrl" class="text-primary text-h6 font-weight-bold mt-1">{{ meetingUrlBase }}</span>
        </VCardText>
      </VCard>
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
      <VCard :title="$t('rooms.edit_meeting_room')">
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="editTitle"
                :label="$t('rooms.room_name')"
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="editType"
                :label="$t('rooms.room_type')"
                :items="meetingRoomsTypes()"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>
            <VCol cols="12">
              <div class="text-body-2 font-weight-medium mb-2">
                {{ $t('rooms.audience_type') }}
              </div>
              <VRadioGroup
                v-model="editAudienceType"
                hide-details
              >
                <VRadio
                  v-for="opt in meetingRoomAudienceTypes()"
                  :key="opt.value"
                  :label="opt.title"
                  :value="opt.value"
                />
              </VRadioGroup>
            </VCol>
            <VCol
              v-if="isParentSpecific"
              cols="12"
            >
              <AppAutocomplete
                v-model="editParentIds"
                :server-search="searchParents"
                :item-title="parentOptionTitle"
                item-value="id"
                :label="$t('rooms.target_parents')"
                :no-data-text="$t('autocomplete.no_results')"
                multiple
                chips
                closable-chips
                clearable
                :preload-limit="50"
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

    <SnackbarComponent ref="snackbarRef" />
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
