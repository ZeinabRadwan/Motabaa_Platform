<script setup>
import {
avatarText,
kFormatter,
} from '@core/utils/formatters'
import { can, canDoes } from '@layouts/plugins/casl'
import { useUserListStore } from '@/views/apps/user/useUserListStore'

const userListStore = useUserListStore()
const emit = defineEmits();
const props = defineProps(['errors', 'isMeeting', 'uploadPercentage']);

const { errors, isMeeting } = toRefs(props);

const userData = JSON.parse(localStorage.getItem('userData') || 'null')
const fileUpload = ref('')
const imageUpload = ref('')
const videoUpload = ref('')
const text = ref('')
const file = ref('')
const image = ref('')
const video = ref('')
const loading = ref(false)

const fileName = ref('file')
const imageName = ref('Image')
const videoName = ref('Video')
const refVForm = ref()

const parentsCanSee = ref(false)
const logWork = ref(true)
const showImageButton = ref(true)
const showVideoButton = ref(true)
const showFileButton = ref(true)
const showRemoveButton = ref(false)

const rules = [fileList => !fileList || !fileList.length || fileList[0].size < (100 * 1024 * 1024) || 'validtion.less_than_100MB']
// const rules = (fileList) => {
//     console.log(fileList)
//     return 'asd';
// }

const imageUploadfun = () => {
    imageUpload.value.click()
}

const fileUploadfun = () => {
  fileUpload.value.click()
}

const videoUploadfun = () => {
  videoUpload.value.click()
}

const onFileSelected = () => {
  if (file.value[0]) {
    fileName.value = file.value[0].name.substring(0, 70);
    showImageButton.value = false;
    showVideoButton.value = false;
    showRemoveButton.value = true;
  }else{
      fileName.value = 'file';
  }
  errors.value.file ? errors.value.file = undefined : null;
}
const onImageSelected = () => {
  if (image.value[0]) {
    imageName.value = image.value[0].name.substring(0, 70);
    showVideoButton.value = false;
    showFileButton.value = false;
    showRemoveButton.value = true;
  }else{
    imageName.value = 'Image';
  }
  errors.value.image ? errors.value.image = undefined : null;

}
const onVideoSelected = () => {
  if (video.value[0]) {
    videoName.value = video.value[0].name.substring(0, 70);
    showImageButton.value = false;
    showFileButton.value = false;
    showRemoveButton.value = true;
  }else{
    videoName.value = 'Video';
  }
  errors.value.video ? errors.value.video = undefined : null;

}

const send = () => {

  if(isMeeting.value){
    parentsCanSee.value = false;
    logWork.value = false;
  }
  
  if(canDoes('parent')){
    parentsCanSee.value = true;
    logWork.value = false;
  }

  let data = {
    content: text.value,
    parents_can_see: Number(parentsCanSee.value),
    log_work: Number(logWork.value),
    file: file.value[0] ?? null,
    image: image.value[0] ?? null,
    video: video.value[0] ?? null,
  }
  loading.value=true;
  emit('send-meesage', data, function () {
    loading.value = false;
    imageName.value = 'Image';
    fileName.value = 'file';
    videoName.value = 'Video';
    text.value = '';
    file.value = '';
    image.value = '';
    video.value = '';
    showImageButton.value = true;
    showVideoButton.value = true;
    showFileButton.value = true;
    showRemoveButton.value = false;
  });
}

const removeFile = () => {
    file.value = '';
    image.value = '';
    video.value = '';
    imageName.value = 'Image';
    fileName.value = 'file';
    videoName.value = 'Video';
    showImageButton.value = true;
    showVideoButton.value = true;
    showFileButton.value = true;
    showRemoveButton.value = false;
}

if (userData && !userData.picture) {
  userListStore.fetchImage(userData.id).then(response => {
    if(response.data.data) {
      userData.picture = response.data.data
    }
  })
}

</script>

<template>
  <div>
    <VForm ref="refVForm"> 
      <VFileInput v-show="false" v-model="file" :rules="rules" ref="fileUpload" @change="onFileSelected" />
      <VFileInput v-show="false" v-model="image" :rules="rules" accept="image/png, image/jpeg, image/bmp" @change="onImageSelected" ref="imageUpload" />
      <VFileInput v-show="false" v-model="video" :rules="rules" accept="video/mp4, video/mov, video/ogg, video/webm" @change="onVideoSelected" ref="videoUpload" />
    </VForm>
    <VCard>
      <VCardText>
        <VRow>
          <VCol
            cols="1"
          >
            <VAvatar
              size="34"
              :variant="!userData.picture ? 'tonal' : undefined"
              class="me-3"
              color="primary" 
            >
              <VImg
                v-if="userData.picture"
                :src="userData.picture.file_url"
              />
              <span v-else>
                {{ avatarText(userData.name) }}
              </span>
            </VAvatar>
          </VCol>
          <VCol
            cols="11"
          >
            <AppTextarea
              :disabled="loading"
              auto-grow
              v-model="text"
              :placeholder="$t('Write a comment')"
              rows="1"
            />
          </VCol>
          <VCol
            v-if="!canDoes('parent') && !isMeeting"
            cols="12"
          >
            <div class="demo-space-x">
              <VCheckbox
                :disabled="loading"
                v-model="logWork"
                :label="$t('messages.log work')"
              />
              <VCheckbox
                :disabled="loading"
                v-model="parentsCanSee"
                :label="$t('messages.parents can see')"
              />
            </div>
          </VCol>
        </VRow>
      </VCardText>
      <VDivider></VDivider>
      <VCardText>
        <VRow>
          <VCol
            v-if="showImageButton"
            class="pa-0 text-no-wrap"
            cols="6"
            :md="(showVideoButton && showFileButton) ? 3 : 9"
          >
            <VBtn 
              :disabled="loading"
              variant="text" 
              color="success" 
              @click="imageUploadfun"
            >
              <VIcon
                icon="tabler-photo"
                size="22"
              />
              <span style="color: rgba(var(--v-theme-on-background), var(--v-high-emphasis-opacity));">{{ $t(imageName) }}</span>
            </VBtn>
            <IconBtn 
              v-if="showRemoveButton && !loading"
              color="secondary" 
              @click="removeFile"
            >
              <VIcon icon="tabler-x" size="15"/>
            </IconBtn>
            <div>
                <span v-if="errors?.image" class="v-messages__message" style="color: rgb(var(--v-theme-error));">{{ errors?.image[0] }}</span>
                <span v-if="refVForm?.errors[0]" class="v-messages__message" style="color: rgb(var(--v-theme-error));">{{ $t(refVForm.errors[0]['errorMessages'][0]) }}</span>
            </div>
          </VCol>
          <VCol
            v-if="showVideoButton"
            class="pa-0 text-no-wrap"
            cols="6"
            :md="(showImageButton && showFileButton) ? 3 : 9"
          >
            <VBtn
              :disabled="loading"
              variant="text"
              @click="videoUploadfun"
            >
              <VIcon
                  icon="tabler-video"
                  color="info" 
                  size="22"
              />
              <span style="color: rgba(var(--v-theme-on-background), var(--v-high-emphasis-opacity));">{{$t(videoName)}}</span>
            </VBtn>
            <IconBtn 
              v-if="showRemoveButton && !loading"
              color="secondary" 
              @click="removeFile"
            >
              <VIcon icon="tabler-x" size="15"/>
            </IconBtn>
            <div>
                <span v-if="errors?.video" class="v-messages__message" style="color: rgb(var(--v-theme-error));">{{ errors?.video[0] }}</span>
                <span v-if="refVForm?.errors[0]" class="v-messages__message" style="color: rgb(var(--v-theme-error));">{{ $t(refVForm.errors[0]['errorMessages'][0]) }}</span>
            </div>
          </VCol>
          <VCol
            v-if="showFileButton"
            class="pa-0 text-no-wrap"
            cols="6"
            :md="(showImageButton && showVideoButton) ? 3 : 9"
          >
            <VBtn
              :disabled="loading"
              variant="text"
              @click="fileUploadfun"
            >
              <VIcon
                  icon="tabler-file"
                  size="22"
              />
              <span style="color: rgba(var(--v-theme-on-background), var(--v-high-emphasis-opacity));">{{$t(fileName)}}</span>
            </VBtn>
            <IconBtn 
              v-if="showRemoveButton && !loading"
              color="secondary" 
              @click="removeFile"
            >
              <VIcon icon="tabler-x" size="15"/>
            </IconBtn>
            <div>
                <span v-if="errors?.file" class="v-messages__message" style="color: rgb(var(--v-theme-error));">{{ errors?.file[0] }}</span>
                <span v-if="refVForm?.errors[0]" class="v-messages__message" style="color: rgb(var(--v-theme-error));">{{ $t(refVForm.errors[0]['errorMessages'][0]) }}</span>
            </div>
          </VCol>
          <VCol
            class="pa-0 justify-end d-flex"
            cols="12"
            md="3"
          >
            <VBtn color="primary" @click="send" :disabled="loading || text == '' || text == null || refVForm?.errors[0]">
              {{ $t('send') }}<span v-if="loading" >({{ props.uploadPercentage }}%)</span>
            </VBtn>
          </VCol>
        </VRow>

      </VCardText>
    </VCard>
  </div>
</template>
