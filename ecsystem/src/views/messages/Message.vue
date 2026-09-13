<script setup>
import i18n from '@/plugins/i18n/index.js'
import {
avatarText,
kFormatter,
} from '@core/utils/formatters'
import moment from '@/plugins/moment'
import {messagesApi} from "@/plugins/apis/messagesReqest"
import { useUserListStore } from '@/views/apps/user/useUserListStore'

const messagesReqest = messagesApi()
const userListStore = useUserListStore()
const emit = defineEmits();
const props = defineProps(['message', 'canDelete']);
var isDeleteDialogVisible = ref(false)

const { message, canDelete } = toRefs(props);

const openNewTab = (url) => {
  window.open(url, '_blank');
}

const sysMessges = (message) => {
  switch (message.type) {
    case 'start_now_meeting':
      return i18n.global.t('rooms.started_now') + ` <a href="${message.content}" target="_blank">${message.content} </a>`
    case 'start_later_meeting':
      try {
        const content = JSON.parse(message.content)
        return i18n.global.t('rooms.start_later', {time: content.time, day: moment(content.date).locale(i18n.global.locale.value).format("dddd") , date: content.date}).replace(/\n/g, '<br>') + ` <a href="${content.url}" target="_blank">${content.url} </a>`
      } catch (error) {}
      break;

  }
}

const deleteMessage = (id) => {
  if(canDelete) {
    emit('delete-message', id);
    isDeleteDialogVisible.value = false
  }
}

const sysMessageTypes = ['started_session', 'ended_session', 'start_now_meeting', 'start_later_meeting', 'SYS']
const hasAttachedMedia = !!(message.value.file || message.value.image || message.value.video)
const attachmentsResolved = Object.prototype.hasOwnProperty.call(message.value, 'file')
  || Object.prototype.hasOwnProperty.call(message.value, 'image')
  || Object.prototype.hasOwnProperty.call(message.value, 'video')

if (!sysMessageTypes.includes(message.value.type) && !hasAttachedMedia && !attachmentsResolved) {
  messagesReqest.fetchFile(Number(message.value.id)).then(response => {

    if(response.data.data) {

      let file = response.data.data.file
      message.value.is_file_video = response.data.data.is_file_video
      if(file.file_name == "message_image") {
        message.value.image = response.data.data.file
      }
      else if(file.file_name == "message_file") {
        message.value.file = response.data.data.file
      }
      else if(file.file_name == "message_video") {
        message.value.video = response.data.data.file
      }
    }
  })
}

if (message.value.user && message.value.user.picture === undefined) {
  userListStore.fetchImage(message.value.user.id).then(response => {
    if(response.data.data) {
      message.value.user.picture = response.data.data
    }
  })
}

</script>

<template>
  <div>
    <VCard>
      <VCardText>
        <VRow>
          <VCol cols="6">
            <div class="d-flex align-center">
              <VAvatar
                size="34"
                :variant="!message.user.picture ? 'tonal' : undefined"
                class="me-3"
                color="primary" 
              >
                <VImg
                  v-if="message.user.picture"
                  :src="message.user.picture.file_url"
                />
                <span v-else>
                  {{ avatarText(message.user.name) }}
                </span>
              </VAvatar>
              <div class="d-flex flex-column">
                <h6 class="text-h6">
                  <RouterLink
                    :to="{ name: 'user-view-tab-id', params: { id: message.user.id, tab: 'info' } }"
                    class="font-weight-medium user-list-name"
                  >
                    {{message.user.name}}
                  </RouterLink>
                </h6>
                <p class="mb-0 text-sm">
                  {{message.user.roles.map(item => item.name).join(', ')}} <span :style="{color: (message.parents_can_see==1) ? 'green' : '#9CA0BB'}">{{ (message.log_work==1) ? '(فعاليات الجلسة)' : '(تعليق)' }}</span>
                </p>
              </div>
            </div>
          </VCol>
          <VCol cols="6" class="justify-end d-flex">
            <p class="mb-0 text-sm">
              {{moment(message.created_at).locale(i18n.global.locale.value).fromNow()}}
              <VDialog
                v-model="isDeleteDialogVisible"
                persistent
                class="v-dialog-sm"
              >
                <!-- Dialog Activator -->
                <template #activator="{ props }">
                  <IconBtn 
                    v-bind="props"
                    v-if="canDelete"
                    :title="$t('delete')" 
                    color="error"
                  >
                    <VIcon icon="tabler-trash" />
                  </IconBtn>
                </template>

                <!-- Dialog close btn -->
                <DialogCloseBtn @click="isDeleteDialogVisible = !isDeleteDialogVisible" />

                <!-- Dialog Content -->
                <VCard>
                  <VCardText>
                    {{ $t('Are you sure you want to delete this message?') }}
                  </VCardText>

                  <VCardText class="d-flex justify-end gap-3 flex-wrap">
                    <VBtn @click="deleteMessage(message.id)">
                      {{ $t('delete') }}
                    </VBtn>
                    <VBtn
                      color="secondary"
                      variant="tonal"
                      @click="isDeleteDialogVisible = false"
                    >
                      {{ $t('Cancel') }}
                    </VBtn>
                  </VCardText>
                </VCard>
              </VDialog>
            </p>
          </VCol>
        </VRow>
        <VDivider v-if="message.goal" class="mb-2"></VDivider>
        <span v-if="message.goal">{{ $t('goals.session') }}: {{ message.goal.title }} </span>
        <VDivider v-if="message.goal" class="mt-2"></VDivider>
        <h6 class="text-h6 mt-4 line-heigh font-weight-bold text-error mb-2" v-if="message.type == 'ended_session'">
          {{$t('goals.'+message.content)}}
        </h6>
        <h6 class="text-h6 mt-4 line-heigh font-weight-bold text-success mb-2" v-else-if="message.type == 'started_session'">
          {{$t('goals.'+message.content)}}
        </h6>
        <h6 class="text-h6 mt-4 line-heigh font-weight-bold text-success " v-else-if="message.type != '' && message.type != null" v-html="sysMessges(message)"></h6>
        <h6 class="text-h6 mt-4 line-heigh font-weight-medium mb-2" v-else>
          {{message.content}}
        </h6>
        <VCol v-if="message.video && message.video.file_url">
          <video width="400" height="250" :src="message.video.file_url" controls></video>
        </VCol>
        <VCol v-else-if="message.is_file_video">
          <video width="400" height="250" :src="message.file.file_url" controls></video>
        </VCol>
        <VRow>
          <VCol 
            v-if="message.video && message.video.file_url"
            cols="3"
          >
            <VBtn variant="text" @click="openNewTab(message.video.file_url)" color="info" class="pa-0 mt-2">
              <VIcon
                icon="tabler-download"
                size="22"
              />
              <span style="color: rgba(var(--v-theme-on-background), var(--v-high-emphasis-opacity));">{{ $t('download_video') }}</span>
            </VBtn>
          </VCol>
          <VCol 
            v-else-if="message.file && message.file.file_url"
            cols="3"
          >
            <VBtn variant="text" @click="openNewTab(message.file.file_url)" color="success" class="pa-0 mt-2">
              <VIcon
                icon="tabler-download"
                size="22"
              />
              <span style="color: rgba(var(--v-theme-on-background), var(--v-high-emphasis-opacity));">{{ $t('download file') }}</span>
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
      <VImg
          v-if="message.image && message.image.file_url"
          :src="message.image.file_url"
          cover
        />
    </VCard>
  </div>
</template>
<style scoped>
  .line-heigh{
    line-height: 1.7;
  }
  .user-list-name:not(:hover) {
    color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
  }
</style>
