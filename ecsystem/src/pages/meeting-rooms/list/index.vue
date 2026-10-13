<script setup>
import { watchServerTableFetch } from '@core/utils/tableFetch'
import { useRoute, useRouter } from 'vue-router';
import {roomsApi} from "@/plugins/apis/roomsReqest"
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import i18n from '@/plugins/i18n/index.js';
import { avatarText } from '@core/utils/formatters'
import { can } from '@layouts/plugins/casl'
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import {
    statusItems,
    meetingRoomsTypes,
    meetingRoomAudienceTypes,
} from '@core/utils/generalItems';

const roomsReqest = roomsApi()
const userListStore = useUserListStore()
const route = useRoute()
const router = useRouter()
const refVForm = ref()
const snackbarRef = ref(null);
const options = ref({
  page: -1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
})

const searchQuery = ref('')
const newRoom = ref('')
const newRoomType = ref('')
const audienceType = ref('general')
const selectedParentIds = ref([])
const rooms = ref([])
const roomDialog = ref(false);
const editingRoomId = ref(-1)
const saveLoading = ref(false)
const selectedStatus = ref('active')

const canManageRooms = computed(() => can('edit_meetings', 'edit_meetings') || can('admin_meetings', 'admin_meetings'))
const isParentSpecific = computed(() => audienceType.value === 'parents')

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

const audienceBadge = room => {
  if (room.audience_type === 'parents')
    return i18n.global.t('rooms.audience_badge_parents')

  return i18n.global.t('rooms.audience_badge_general')
}

const resetRoomForm = () => {
  newRoom.value = ''
  newRoomType.value = ''
  audienceType.value = 'general'
  selectedParentIds.value = []
  editingRoomId.value = -1
}

const fetchAllRooms = () => {
    roomsReqest.fetchAll({
      q: searchQuery.value,
      status: selectedStatus.value,
      options: options.value,
      page: options.value.page,

    }).then(response => {
        rooms.value = response.data.data
    })
}

const openCreateRoom = () => {
    resetRoomForm()
    roomDialog.value = true
}

const openEditRoom = (room, event) => {
  event?.stopPropagation?.()
  editingRoomId.value = Number(room.id)
  newRoom.value = room.title
  newRoomType.value = String(room.type)
  audienceType.value = room.audience_type === 'parents' ? 'parents' : 'general'
  selectedParentIds.value = Array.isArray(room.assigned_parent_ids)
    ? [...room.assigned_parent_ids]
    : []
  roomDialog.value = true
}

const saveRoom = async () => {
    const validation = await refVForm.value?.validate?.()
    if (validation && validation.valid === false)
      return

    if (!newRoom.value?.trim() || !newRoomType.value) {
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.missing_field'), 'error');
        return;
    }
    if (isParentSpecific.value && (!selectedParentIds.value || selectedParentIds.value.length === 0)) {
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('rooms.parents_required'), 'error');
        return;
    }

    saveLoading.value = true
    const payload = {
      title: newRoom.value.trim(),
      type: newRoomType.value,
      audience_type: audienceType.value,
      parent_ids: isParentSpecific.value ? selectedParentIds.value : [],
    }

    roomsReqest.put(payload, String(editingRoomId.value)).then(response => {
        if (response.status == 200) {
            snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success');
            roomDialog.value = false;
            resetRoomForm()
            fetchAllRooms()
        }
    }).catch(error => {
        const msg = error.response?.data?.errors?.parent_ids?.[0]
          || error.response?.data?.errors?.error?.[0]
        if (msg)
          snackbarRef.value.exposevisibleSnackbar(msg, 'error');
    }).finally(() => {
        saveLoading.value = false
    })
}


watchServerTableFetch(fetchAllRooms, {
  search: searchQuery,
  filters: () => [selectedStatus.value],
  options,
})

</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <VForm 
              ref="refVForm"
            >
        <VCard class="padding-30p">    
            <VCard class="border-1p">
                <template v-slot:title>
                    <VRow>
                        <span class="v-col v-col-12 justify-lg-space-between d-flex align-center gap-4"> 
                            <VSpacer />
                            <div  class="d-flex gap-4">
                                <AppTextField
                                style="inline-size: 15rem;"
                                    v-model="searchQuery"
                                    :placeholder="$t('Search')"
                                    density="compact"
                                />
                                <AppSelect
                                    v-if="can('admin_meetings','admin_meetings')"
                                    v-model="selectedStatus"
                                    :items="statusItems()"
                                    clearable
                                    clear-icon="tabler-x"
                                >
                                </AppSelect>
                                <VBtn @click="openCreateRoom" v-if="canManageRooms">
                                    {{ $t('rooms.add_meeting_room') }}
                                </VBtn>
                            </div>
                        </span>
                    </VRow>
                </template>
                <VDivider></VDivider>
                <v-card-text>
                    <VRow>
                        <VCol
                            cols="12"
                            md="4"
                            v-for="room in rooms"
                            :key="room.id"
                        >
                            <VCard class="cursor-pointer" @click="()=> router.push('/meeting-rooms/view/'+room.id)">
                                <VCardText>
                                    <VRow>
                                        <VCol cols="12" class="d-flex align-center justify-space-between flex-wrap gap-2">
                                            <div class="d-flex align-center">
                                            <VAvatar
                                                rounded
                                                :color="!room.deleted_at ? 'success' : 'error'"
                                                size="40"
                                                variant="tonal"
                                                class="me-3"
                                                icon="tabler-messages"
                                            />
                                            <div class="d-flex flex-column">
                                                <h6 class="text-base" :class="!room.deleted_at ? 'text-success' : 'text-error'">
                                                    {{ room.title }} ({{ room.type_name ? room.type_name : '' }})
                                                </h6>
                                            </div>
                                            </div>
                                            <VChip
                                              size="small"
                                              label
                                              :color="room.audience_type === 'parents' ? 'primary' : 'secondary'"
                                            >
                                              {{ audienceBadge(room) }}
                                            </VChip>
                                        </VCol>
                                        <VCol cols="12"><span class="text-body-1 font-weight-bold">{{$t('rooms.create_by')}}</span></VCol>
                                        
                                        <VCol cols="12">
                                            <div class="d-flex align-center justify-space-between flex-wrap gap-2">
                                            <div class="d-flex align-center">
                                                <VAvatar
                                                    size="40"
                                                    :variant="'tonal'"
                                                    class="me-3"
                                                >
                                                    <VImg
                                                        v-if="room.user.picture"
                                                        :src="room.user.picture.file_url"
                                                    />
                                                    <span v-else>{{ avatarText(room.user.name) }}</span>
                                                </VAvatar>
                                                <div class="d-flex flex-column">
                                                    <h6 class="text-base">
                                                        {{room.user.name}}
                                                    </h6>
                                                </div>
                                            </div>
                                            <VBtn
                                              v-if="canManageRooms"
                                              size="small"
                                              variant="tonal"
                                              @click="openEditRoom(room, $event)"
                                            >
                                              {{ $t('Edit') }}
                                            </VBtn>
                                            </div>
                                        </VCol>
                                    </VRow>
                                </VCardText>
                            </VCard>
                        </VCol>
                        
                    </VRow>

                </v-card-text>
            </VCard>
        </VCard>
      </VForm>
      </VCol>
    </VRow>
    <VDialog
        v-model="roomDialog"
        persistent
        class="v-dialog-sm"
        max-width="560"
    >
        <VCard :title="editingRoomId > 0 ? $t('rooms.edit_meeting_room') : $t('rooms.add_meeting_room')">
        <VCardText>
            <VRow>
                <VCol cols="12">
                    <AppTextField
                        v-model="newRoom"
                        :label="$t('rooms.room_name')"
                    />
                </VCol>
                <VCol cols="12">
                    <AppSelect
                        v-model="newRoomType"
                        :label="$t('rooms.room_type')"
                        :items="meetingRoomsTypes()"
                        clearable
                        clear-icon="tabler-x"
                    >
                    </AppSelect>
                </VCol>
                <VCol cols="12">
                  <div class="text-body-2 font-weight-medium mb-2">
                    {{ $t('rooms.audience_type') }}
                  </div>
                  <VRadioGroup
                    v-model="audienceType"
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
                    v-model="selectedParentIds"
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
            @click="roomDialog = false"
            >
                {{ $t('Close') }}
            </VBtn>
            <VBtn
              color="success"
              :loading="saveLoading"
              @click="saveRoom"
            >
                {{ $t('Save') }}
            </VBtn>
        </VCardText>
        </VCard>
    </VDialog>
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>
<route lang="yaml">
  meta:
    action: access_meetings
    subject: access_meetings
    meetingRoomAccess: true
</route>
