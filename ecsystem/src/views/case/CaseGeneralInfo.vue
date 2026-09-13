<script setup>
import { casesApi } from "@/plugins/apis/casesReqest";
import { disabilityTypeApi } from "@/plugins/apis/disabilityTypeReqest";
import i18n from '@/plugins/i18n/index.js';
import { useUserListStore } from '@/views/apps/user/useUserListStore';
import cuntries from "@core/utils/cuntries";
import {
  periodItems,
  serviceitems
} from '@core/utils/generalItems';
import avatar from '@images/avatars/avatar-16.png';
import {
  lengthValidator,
  requiredValidator, requiredValidatorIf
} from '@validators';
import { useRoute, useRouter } from 'vue-router';

const disabilityTypesApi = disabilityTypeApi()
const rules = [fileList => !fileList || !fileList.length || fileList[0].size < 10000000 || 'Avatar size should be less than 10 MB!']
const route = useRoute()
const router = useRouter()
const refVForm = ref()
const imageValidMessage = ref('')


const casesReqest = casesApi()
const userListStore = useUserListStore()
const serviceitemsData = ref([])
const caseName = ref('')
const brithdate = ref('')
const phoneNumber = ref('')
const file = ref('')
const buildNumber = ref('')
const street = ref('')
const city = ref('')
const alhay = ref('')
const zip = ref('')
const zip2 = ref('')
const unitNumber = ref('')
const fileUpload = ref('')
const occupational = ref(null)
const occupationalItem = ref([])
const physiotherapist = ref(null)
const physiotherapistItems = ref([])
const parentsItems = ref([])
const parent = ref([])
const teacher = ref([])
const teacherItems = ref([])
const psychotherapist = ref(null)
const psychotherapistItem = ref([])
const pronunciationSpeechItem = ref([])
const pronunciationSpeech = ref(null)
const search = ref()
const idNumber = ref('')
const emergency_contact = ref('')

const bloodtypes = ref([]);
const nationality = ref([])
const insuranceNumber = ref('')
const insuranceVal = ref('')
const serviceVal = ref([])
const period = ref('')
const disability = ref([])


const chooseImage =  ref(avatar);
const currentImage =  ref();
const isDeleteDialogVisible = ref(false);
const typesDisability = ref([]);


const loading = ref({
  disabilities: true,
  services: true,
  users: true,
  parent: true,
})

onMounted(() => {
  disabilityTypesApi.fetch().then( response =>{
    typesDisability.value = response.data.data
    loading.value.disabilities = false
  });
  serviceitems().then(data => {
    serviceitemsData.value = data
    loading.value.services = false
  })
  loading.value.users = false
  loading.value.parent = false
});

const searchTeachers = params => userListStore.searchItems({ ...params, roles: ['teacher'] })
const searchParents = params => userListStore.searchItems({ ...params, roles: ['parent'] })
const searchPhysiotherapists = params => userListStore.searchItems({ ...params, roles: ['physiotherapist_specialist'] })
const searchOccupational = params => userListStore.searchItems({ ...params, roles: ['occupational_specialist'] })
const searchPsychotherapists = params => userListStore.searchItems({ ...params, roles: ['psychotherapist_specialist'] })
const searchPronunciation = params => userListStore.searchItems({ ...params, roles: ['pronunciation_speech_specialist'] })

const errors = ref({
  email: undefined,
  phone: undefined,
})


const bloodtypesItems = [
  "A+",
  "A-",
  "B+",
  "B-",
  "O+",
  "O-", 
  "AB+",
  "AB-",

];

const addParentTab = () => {window.open(router.resolve({ name: 'parents-add', query: {to : '/close-tab'} }).href, '_blank')}

const fileUploadfun = () => {
  fileUpload.value.click()
}

const onFileSelected = () => {
  if (file.value[0]) {
    const reader = new FileReader();
    reader.onload = () => {
      chooseImage.value = reader.result;
    };
    reader.readAsDataURL(file.value[0]);
    imageValidMessage.value = ''
  }else{
    chooseImage.value = avatar
  }
}

const addItemDialog = ref(false);
const newItem = ref('');

function addItem() {
  if (newItem.value.trim() !== '') {
    disabilityTypesApi.add({name: newItem.value}).then( response =>{
      disability.value.push(response.data.data.value)
      if(!typesDisability.value.some(item => item.value === response.data.data.value))
        typesDisability.value.push(response.data.data);
    });
    newItem.value = '';
    addItemDialog.value = false;

  }
}

const openAddItemDialog = () =>{
    addItemDialog.value = true;
}


const clearSearch = () =>{
    search.value = '';
}

const insuranceItems = () => {
  let insurance = [
    {
      title: 'Beneficiary',
      value: '1',
    },
    {
      title: 'Not beneficiary',
      value: '0',
    },
  ]
  let translated = insurance.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }));

  return translated;
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){

    }
  })
}
const clearFiled = () => {
  insuranceVal.value == '0' ? insuranceNumber.value = null : null;
}


defineExpose({
      refVForm,
      imageValidMessage,
      disability,
      period,
      serviceVal,
      insuranceVal,
      insuranceNumber,
      caseName,
      file,
      teacher,
      physiotherapist,
      emergency_contact,
      occupational,
      psychotherapist,
      pronunciationSpeech,
      nationality,
      brithdate,
      parent,
      idNumber,
      phoneNumber,
      bloodtypes,
      buildNumber,
      chooseImage,
      currentImage,
      street,
      alhay,
      city,
      zip,
      zip2,
      unitNumber
})

const deleteImageDialog = () => {
  isDeleteDialogVisible.value = true;
}

const deleteImage = () => {
  if(Number(route.params.id)>0) {
    casesReqest.deleteImage(Number(route.params.id)).then(() =>{
      chooseImage.value =  null;
      currentImage.value =  null;
      isDeleteDialogVisible.value = false;
    })
  }
  else {
    chooseImage.value =  null;
    currentImage.value =  null;
    isDeleteDialogVisible.value = false;
  }
}

if (!chooseImage.value) {
  casesReqest.fetchImage(Number(route.params.id)).then(response => {
    if(response.data.data) {
      chooseImage.value = response.data.data.file_url;
      currentImage.value =  response.data.data.file_url;
    }
  })
}
</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <!-- 👉 Multiple Column -->
        <VForm 
              ref="refVForm"
              @submit.prevent="onSubmit"
            >
            <VCardText>
              <VRow>
                <!-- 👉 First Name -->

                <VCol cols="12" md="12">

                    <VLabel
                        class="mb-1 text-body-2 text-high-emphasis d-block "
                        :text="$t('Case Image')"
                    />
                  <VAvatar
                    style="cursor: pointer;"
                    @click="fileUploadfun"
                    rounded="lg"
                    :size="130"
                    color="primary"
                    :variant="'tonal'"
                  >
                    <VImg
                      :src="chooseImage ?? avatar" 
                    />
                  </VAvatar>
                  <div class="d-inline-flex flex-wrap gap-2 pa-4">
                    <VBtn
                      color="primary"
                      @click="fileUploadfun"
                    >
                      <VIcon
                        icon="tabler-cloud-upload"
                        class="d-sm-none"
                      />
                      <span class="d-none d-sm-block">{{ (chooseImage && chooseImage != '/src/assets/images/avatars/avatar-16.png') ? $t('Edit Photo') : $t('Add Photo') }}</span>
                      <template v-slot:append>

                      </template>
                    </VBtn>
                    <VBtn
                      v-if="chooseImage && chooseImage != '/src/assets/images/avatars/avatar-16.png'"
                      color="error"
                      @click="deleteImageDialog"
                    >
                      <VIcon
                        icon="tabler-trash"
                        class="d-sm-none"
                      />
                      <span class="d-none d-sm-block">{{$t('Delete Photo')}}</span>
                    </VBtn>
                  </div>

                  <div class="pt-4">
                      <div class="v-messages__message" style="color: rgb(var(--v-theme-error));" >{{ imageValidMessage }}</div>
                  </div>
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="caseName"
                    labelClasses="red-asterisk-label"
                    :label="$t('Name')"
                    :placeholder="$t('Name')"
                    :rules="[requiredValidator, lengthValidator(caseName, 9)]"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                <AppSelect
                    :items="insuranceItems()"
                    labelClasses="red-asterisk-label"
                    v-model="insuranceVal"
                    @update:modelValue="clearFiled"
                    :label="$t('Registration type')"
                    :rules="[requiredValidator]"
                />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="insuranceNumber"
                    labelClasses="red-asterisk-label"
                    :disabled="insuranceVal != '1'"
                    :label="$t('Insurance Number')"
                    :placeholder="$t('Insurance Number')"
                    :rules="[requiredValidatorIf(insuranceNumber,insuranceVal != '1'), lengthValidator(insuranceNumber, 5)]"
                  />
                </VCol>


                <VCol
                  cols="12"
                  md="4"
                >
                <AppSelect
                    :items="periodItems()"
                    labelClasses="red-asterisk-label"
                    v-model="period"
                    :label="$t('Period')"
                    :rules="[requiredValidator]"
                />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppSelect
                    v-model="disability"
                    labelClasses="red-asterisk-label"
                    :items="typesDisability"
                    :label="$t('Types of disability')"
                    chips
                    :loading="loading.disabilities"
                    closable-chips
                    :rules="[requiredValidator]"
                    multiple
                    append-item
                  >
                    <!-- <template v-slot:append-item>
                        <v-list-item @click="openAddItemDialog">
                            <v-list-item-content>
                            <v-list-item-title style="color: green;">{{ $t('Add disability') }}</v-list-item-title>
                            </v-list-item-content>
                        </v-list-item>
                    </template> -->
                  </AppSelect>
                </VCol>
                
                <VCol
                  cols="12"
                  md="4"
                >
                <AppSelect
                    v-model="serviceVal"
                    labelClasses="red-asterisk-label"
                    :items="serviceitemsData"
                    :label="$t('Services provided')"
                    chips
                    :loading="loading.services"
                    closable-chips
                    :rules="[requiredValidator]"
                    multiple
                  >
                  </AppSelect>
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                <AppAutocomplete
                    :server-search="searchTeachers"
                    labelClasses="red-asterisk-label"
                    v-model="teacher"
                    :item-title="'name'"
                    :item-value="'id'"
                    :label="$t('teacher')"
                    :rules="[requiredValidator]"
                />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                <AppAutocomplete
                    :server-search="searchPhysiotherapists"
                    v-model="physiotherapist"
                    clearable
                    :item-title="'name'"
                    :item-value="'id'"
                    :label="$t('Physiotherapist')"
                />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                <AppAutocomplete
                    :server-search="searchOccupational"
                    v-model="occupational"
                    clearable
                    :item-title="'name'"
                    :item-value="'id'"
                    :label="$t('Occupational therapist')"
                />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                <AppAutocomplete
                    :server-search="searchPsychotherapists"
                    v-model="psychotherapist"
                    clearable
                    :item-title="'name'"
                    :item-value="'id'"
                    :label="$t('Psychotherapist')"
                />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                <AppAutocomplete
                    :server-search="searchPronunciation"
                    v-model="pronunciationSpeech"
                    clearable
                    :item-title="'name'"
                    :item-value="'id'"
                    :label="$t('pronunciation_speech_specialist')"
                />
                </VCol>
                <VCol
                  v-show="false"
                  cols="12"
                  md="4"
                  class="mt-1"
                >
                <v-label> {{ $t('User Picture') }}</v-label>
                <VFileInput
                  ref="fileUpload"
                  v-model="file"
                  :label="$t('Add Picture')"
                  accept="image/png, image/jpeg, image/bmp"
                  placeholder="Pick an avatar"
                  prepend-icon=""
                  @change="onFileSelected"
                />
                </VCol>

              </VRow>
              <!-- <h3 class="v-col v-col-6 d-flex gap-4"> {{ $t('Personal Data') }}</h3> -->
            </VCardText>
            <VCardText>
                <h4 class="text-h4"> {{ $t('Personal Data') }}</h4>
            </VCardText>
            <VDivider />
            <VCardText>
              <VRow class="pt-2">

                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="idNumber"
                    labelClasses="red-asterisk-label"
                    :label="$t('idNumber')"
                    :placeholder="$t('idNumber')"
                    :rules="[requiredValidator, lengthValidator(idNumber, 9)]"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                <AppAutocomplete
                    :items="cuntries"
                    v-model="nationality"
                    labelClasses="red-asterisk-label"
                    :item-title='i18n.global.locale.value == "ar" ? "country_arNationality" : "country_enNationality"'
                    item-value='country_code'
                    :search="true"
                    :rules="[requiredValidator]"
                    :label="$t('Nationality')"
                />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppHijriDate
                    v-model="brithdate"
                    :placeholder="$t('Birth date')"
                    labelClasses="red-asterisk-label"
                    :rules="[requiredValidator]"
                    :label="'Birth date'"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                <AppAutocomplete
                    :server-search="searchParents"
                    @update:modelValue="clearSearch"
                    labelClasses="red-asterisk-label"
                    v-model="parent"
                    chips
                    :addLebel="$t('Add Parent')"
                    @addLebelFun="addParentTab"
                    closable-chips
                    :item-title="'name'"
                    :item-value="'id'"
                    multiple
                    :rules="[requiredValidator]"
                    :label="$t('Parent')"
                />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="emergency_contact"
                    :label="$t('Contact in case of emergency')"
                    :placeholder="$t('Contact in case of emergency')"
                    :rules="[lengthValidator(emergency_contact, 9)]"
                  />
                </VCol>

                <!-- 👉 Email -->
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="phoneNumber"
                    dir="ltr"
                    :label="$t('Phone Number')"
                    placeholder="+966xxxxxxxxxx"
                    :error-messages="errors.phone"
                    persistent-placeholder
                  />
                </VCol>

                <!-- 👉 Email -->
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppSelect
                      :items="bloodtypesItems"
                      v-model="bloodtypes"
                      :label="$t('Blood Type')"
                  />
                </VCol>

              </VRow>
            </VCardText>

            <VCardText>
                <h4 class="text-h4"> {{ $t('Address') }}</h4>
            </VCardText>

            <VDivider />
            <VCardText>
              <VRow>
                <!-- 👉 City -->
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="buildNumber"
                    :label="$t('Building number')"
                    :placeholder="$t('Building number')"
                  />
                </VCol>
                
                <!-- 👉 Country -->
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="street"
                    :label="$t('Street name')"
                    :placeholder="$t('Street name')"
                  />
                </VCol>

                <!-- 👉 Company -->
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="alhay"
                    :label="$t('El Hay')"
                    :placeholder="$t('El Hay')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="city"
                    :label="$t('City')"
                    :placeholder="$t('City')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="zip"
                    :label="$t('Zip')"
                    :placeholder="$t('Zip')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="zip2"
                    :label="$t('Second Zip')"
                    :placeholder="$t('Second Zip')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="unitNumber"
                    :label="$t('Unit number')"
                    :placeholder="$t('Unit number')"
                  />
                </VCol>
              </VRow>
            </VCardText>
      </VForm>
      </VCol>
    </VRow>

    <v-dialog v-model="addItemDialog" max-width="500px">
      <v-card>
        <v-card-title>{{ $t('Add disability')}}</v-card-title>
        <VCardText>
          <v-text-field v-model="newItem" :label="$t('Disability')"></v-text-field>
        </VCardText>
        <v-card-actions>
          <v-btn color="primary" @click="addItem">{{ $t('Save') }}</v-btn>
          <v-btn @click="addItemDialog = false">{{ $t('Cancel') }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <VDialog
      v-model="isDeleteDialogVisible"
      persistent
      class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isDeleteDialogVisible = !isDeleteDialogVisible" />

      <!-- Dialog Content -->
      <VCard>
        <VCardText>
          {{ $t('Are you sure you want to delete this image?') }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn @click="deleteImage">
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
  </div>
</template>
