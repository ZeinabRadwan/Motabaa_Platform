<script setup>
import { paginationMeta } from '@/@fake-db/utils'
import { safeItemsPerPage, watchServerTableFetch } from '@core/utils/tableFetch'
import { useRouter } from 'vue-router';
import {roomsApi} from "@/plugins/apis/roomsReqest"
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import i18n from '@/plugins/i18n/index.js';
import { avatarText } from '@core/utils/formatters'
import { can } from '@layouts/plugins/casl'
import ability from '@/plugins/casl/ability'
import { isParentMeetingParticipant } from '@core/utils/meetingRoomAccess'
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import MeetingRoomAccessFields from '@/components/meeting-rooms/MeetingRoomAccessFields.vue'
import {
  meetingAudienceBadgeClass,
  meetingAudienceBadgeLabel,
  meetingAudienceFilterItems,
  meetingRoomStatusFilterItems,
} from '@/utils/meetingRoomAudienceUi'

const roomsReqest = roomsApi()
const userListStore = useUserListStore()
const router = useRouter()
const refVForm = ref()
const snackbarRef = ref(null);
const options = ref({
  page: 1,
  itemsPerPage: 12,
  sortBy: [],
  groupBy: [],
})

const totalRooms = ref(0)
const lastPage = ref(1)

const searchQuery = ref('')
const newRoom = ref('')
const audienceType = ref('general')
const lastInvitationUrl = ref('')
const invitationLinkLoading = ref(false)
const selectedParentIds = ref([])
const rooms = ref([])
const roomsLoading = ref(false)
const roomDialog = ref(false);
const editingRoomId = ref(-1)
const saveLoading = ref(false)
const selectedStatus = ref('all')
const selectedAudienceFilter = ref('all')

const canManageRooms = computed(() => (
  ability.can('edit_meetings', 'edit_meetings')
  || ability.can('admin_meetings', 'admin_meetings')
))
const isParentListView = computed(() => (
  isParentMeetingParticipant()
  && ! canManageRooms.value
  && ! ability.can('admin_meetings', 'admin_meetings')
))
const isParentSpecific = computed(() => audienceType.value === 'parents')
const isLinkInvite = computed(() => audienceType.value === 'link_invite')
const listPerPage = computed(() => safeItemsPerPage(options.value.itemsPerPage, 12))
const paginationLength = computed(() => Math.max(1, Math.ceil(totalRooms.value / listPerPage.value)))
const showPagination = computed(() => totalRooms.value > listPerPage.value)

const normalizeAudienceType = value => {
  if (value === 'parents' || value === 'link_invite')
    return value
  return 'general'
}

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

const resetRoomForm = () => {
  newRoom.value = ''
  audienceType.value = 'general'
  selectedParentIds.value = []
  editingRoomId.value = -1
  lastInvitationUrl.value = ''
}

const fetchAllRooms = () => {
    roomsLoading.value = true
    const page = Number(options.value.page) > 0 ? Number(options.value.page) : 1
    const tableOptions = {
      ...options.value,
      page,
      itemsPerPage: listPerPage.value,
    }
    const params = {
      q: searchQuery.value,
      options: tableOptions,
      page,
    }
    if (! isParentListView.value) {
      params.status = selectedStatus.value
      params.audience_type = selectedAudienceFilter.value
    }
    roomsReqest.fetchAll(params).then(response => {
        rooms.value = response.data.data ?? []
        totalRooms.value = Number(response.data.total) || 0
        lastPage.value = Number(response.data.lastPage) || 1
        const currentPage = Number(response.data.currentPage) || page
        if (rooms.value.length === 0 && currentPage > 1 && totalRooms.value > 0) {
          options.value.page = lastPage.value
        }
        else if (options.value.page !== currentPage) {
          options.value.page = currentPage
        }
    }).finally(() => {
      roomsLoading.value = false
    })
}

const openRoom = roomId => {
  router.push(`/meeting-rooms/view/${roomId}`)
}

const onRoomCardKeydown = (event, roomId) => {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    openRoom(roomId)
  }
}

const openCreateRoom = () => {
    resetRoomForm()
    roomDialog.value = true
}

const openEditRoom = (room, event) => {
  event?.stopPropagation?.()
  editingRoomId.value = Number(room.id)
  newRoom.value = room.title
  audienceType.value = normalizeAudienceType(room.audience_type)
  lastInvitationUrl.value = ''
  selectedParentIds.value = Array.isArray(room.assigned_parent_ids)
    ? [...room.assigned_parent_ids]
    : []
  roomDialog.value = true
}

const saveRoom = async () => {
    const validation = await refVForm.value?.validate?.()
    if (validation && validation.valid === false)
      return

    if (!newRoom.value?.trim()) {
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
      audience_type: audienceType.value,
      parent_ids: isParentSpecific.value ? selectedParentIds.value : [],
    }

    roomsReqest.put(payload, String(editingRoomId.value)).then(response => {
        if (response.status == 200) {
            snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success');
            const invitationUrl = response.data?.data?.invitation_url
            if (invitationUrl)
              lastInvitationUrl.value = invitationUrl
            if (! isLinkInvite.value) {
              roomDialog.value = false
              resetRoomForm()
            }
            else if (response.data?.data?.room_id) {
              editingRoomId.value = Number(response.data.data.room_id)
            }
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

const copyInvitationLink = async (rotate = false) => {
  if (editingRoomId.value <= 0 && ! lastInvitationUrl.value)
    return

  invitationLinkLoading.value = true
  try {
    let url = lastInvitationUrl.value
    if (editingRoomId.value > 0) {
      const response = await roomsReqest.fetchInvitationLink(editingRoomId.value, rotate)
      url = response.data?.data?.invitation_url
    }
    if (url) {
      lastInvitationUrl.value = url
      await navigator.clipboard.writeText(url)
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('rooms.invitation_link_copied'), 'success')
    }
  }
  catch (error) {
    if (error.response?.data?.requires_rotate && editingRoomId.value > 0) {
      await copyInvitationLink(true)
      return
    }
    const msg = error.response?.data?.errors?.error?.[0]
    if (msg)
      snackbarRef.value.exposevisibleSnackbar(msg, 'error')
  }
  finally {
    invitationLinkLoading.value = false
  }
}


watchServerTableFetch(fetchAllRooms, {
  search: searchQuery,
  filters: () => (
    isParentListView.value
      ? []
      : [selectedStatus.value, selectedAudienceFilter.value]
  ),
  options,
})

</script>

<template>
  <div class="meeting-rooms-page">
    <Teleport
      v-if="canManageRooms"
      to="#general-page-header-actions"
    >
      <VBtn
        color="primary"
        prepend-icon="tabler-plus"
        class="general-page-header__btn-primary"
        @click="openCreateRoom"
      >
        {{ $t('rooms.add_meeting_room') }}
      </VBtn>
    </Teleport>

    <VForm ref="refVForm">
      <div class="athar-page-toolbar meeting-rooms-toolbar">
        <AppTextField
          v-model="searchQuery"
          class="athar-page-toolbar__field meeting-rooms-toolbar__search"
          :placeholder="$t('Search')"
          density="compact"
          prepend-inner-icon="tabler-search"
          hide-details
        />
        <div class="athar-page-toolbar__actions meeting-rooms-toolbar__filters">
          <AppSelect
            v-if="!isParentListView"
            v-model="selectedAudienceFilter"
            class="meeting-rooms-toolbar__select"
            :items="meetingAudienceFilterItems()"
            density="compact"
            hide-details
          />
          <AppSelect
            v-if="!isParentListView && can('admin_meetings','admin_meetings')"
            v-model="selectedStatus"
            class="meeting-rooms-toolbar__select"
            :items="meetingRoomStatusFilterItems()"
            density="compact"
            hide-details
          />
        </div>
      </div>

      <div
        v-if="roomsLoading"
        class="meeting-rooms-loading"
        role="status"
      >
        <VProgressCircular
          indeterminate
          color="primary"
          size="44"
        />
      </div>

      <div
        v-else-if="!rooms.length"
        class="meeting-rooms-empty"
      >
        <span class="meeting-rooms-empty__icon">
          <VIcon
            icon="tabler-video"
            size="28"
          />
        </span>
        <h2 class="meeting-rooms-empty__title">
          {{ $t('rooms.empty_list_title') }}
        </h2>
        <p class="meeting-rooms-empty__hint">
          {{ $t('rooms.empty_list_hint') }}
        </p>
        <VBtn
          v-if="canManageRooms"
          color="primary"
          prepend-icon="tabler-plus"
          class="mt-2"
          @click="openCreateRoom"
        >
          {{ $t('rooms.add_meeting_room') }}
        </VBtn>
      </div>

      <div
        v-else
        class="meeting-rooms-grid"
      >
        <article
          v-for="room in rooms"
          :key="room.id"
          class="meeting-room-card"
          tabindex="0"
          role="button"
          @click="openRoom(room.id)"
          @keydown="onRoomCardKeydown($event, room.id)"
        >
          <div class="meeting-room-card__head">
            <span class="meeting-room-card__icon">
              <VIcon
                icon="tabler-video"
                size="22"
              />
            </span>
            <div class="meeting-room-card__title-wrap">
              <h3 class="meeting-room-card__title">
                {{ room.title }}
              </h3>
              <div
                v-if="!isParentListView"
                class="meeting-room-card__badges"
              >
                <span
                  class="meeting-room-card__badge"
                  :class="room.deleted_at ? 'meeting-room-card__badge--inactive' : 'meeting-room-card__badge--active'"
                >
                  {{ room.deleted_at ? $t('Inactive') : $t('Active_user') }}
                </span>
                <span
                  class="meeting-room-card__badge"
                  :class="meetingAudienceBadgeClass(room.audience_type)"
                >
                  {{ meetingAudienceBadgeLabel(room.audience_type) }}
                </span>
              </div>
            </div>
          </div>

          <div
            v-if="room.audience_type === 'parents' && canManageRooms && room.assigned_parents_count != null"
            class="meeting-room-card__meta meeting-room-card__meta--assigned-count"
          >
            <p class="meeting-room-card__assigned-count">
              <VIcon
                icon="tabler-user-check"
                size="16"
                class="meeting-room-card__assigned-count-icon"
              />
              {{ $t('rooms.assigned_parents_count', { count: room.assigned_parents_count }) }}
            </p>
          </div>

          <div class="meeting-room-card__meta">
            <p class="meeting-room-card__meta-label">
              {{ $t('rooms.create_by') }}
            </p>
            <div class="meeting-room-card__creator">
              <VAvatar
                size="36"
                variant="tonal"
              >
                <VImg
                  v-if="room.user?.picture"
                  :src="room.user.picture.file_url"
                />
                <span v-else>{{ avatarText(room.user?.name) }}</span>
              </VAvatar>
              <p class="meeting-room-card__creator-name">
                {{ room.user?.name }}
              </p>
            </div>
          </div>

          <div class="meeting-room-card__footer">
            <VBtn
              v-if="canManageRooms"
              size="small"
              variant="text"
              @click="openEditRoom(room, $event)"
            >
              {{ $t('Edit') }}
            </VBtn>
            <span
              class="meeting-room-card__cta"
              :aria-label="$t('rooms.open_room')"
            >
              <VIcon
                icon="tabler-chevron-left"
                size="18"
              />
            </span>
          </div>
        </article>
      </div>

      <div
        v-if="showPagination && !roomsLoading"
        class="meeting-rooms-pagination"
      >
        <p class="meeting-rooms-pagination__meta text-sm text-disabled mb-0">
          {{ paginationMeta(options, totalRooms) }}
        </p>
        <VPagination
          v-model="options.page"
          :length="paginationLength"
          :total-visible="$vuetify.display.xs ? 1 : 5"
        />
      </div>
    </VForm>

    <VDialog
      v-model="roomDialog"
      persistent
      max-width="640"
      scrollable
    >
      <VCard v-if="roomDialog">
        <VCardItem>
          <template #prepend>
            <span class="meeting-rooms-dialog__title-icon">
              <VIcon icon="tabler-door-enter" />
            </span>
          </template>
          <VCardTitle class="pa-0">
            {{ editingRoomId > 0 ? $t('rooms.edit_meeting_room') : $t('rooms.add_meeting_room') }}
          </VCardTitle>
        </VCardItem>
        <VDivider />
        <VCardText class="meeting-rooms-dialog__body">
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="newRoom"
                :label="$t('rooms.room_name')"
                prepend-inner-icon="tabler-edit"
              />
            </VCol>
            <VCol cols="12">
              <MeetingRoomAccessFields
                v-model:audience-type="audienceType"
                v-model:selected-parent-ids="selectedParentIds"
                :search-parents="searchParents"
                :parent-option-title="parentOptionTitle"
                :show-invitation-actions="isLinkInvite && (lastInvitationUrl || editingRoomId > 0)"
                :invitation-link-loading="invitationLinkLoading"
                @copy-invitation-link="copyInvitationLink(false)"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardText class="meeting-rooms-dialog__actions">
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

<style lang="scss">
@import '@/styles/meeting-rooms-list.scss';
</style>
<route lang="yaml">
  meta:
    action: access_meetings
    subject: access_meetings
    meetingRoomAccess: true
</route>
