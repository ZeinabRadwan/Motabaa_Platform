<script setup>
import i18n from '@/plugins/i18n/index.js';
import { useUserListStore } from '@/views/apps/user/useUserListStore';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import avatar from '@images/avatars/avatar-16.png';
import { useRoleStore } from '@/views/apps/roles/useRoleStore';
import {
confirmedValidator,
emailValidator,
integerValidator,
lengthValidator,
passwordValidator,
requiredValidator
} from '@validators';
import { useRoute, useRouter } from 'vue-router';

const roleStore = useRoleStore()
const userListStore = useUserListStore()
const rules = [fileList => !fileList || !fileList.length || fileList[0].size < 10000000 || 'Avatar size should be less than 10 MB!']
const route = useRoute()
const router = useRouter()
const refVForm = ref()
const firstName = ref('')
const brithdate = ref('')
const email = ref('')
const phoneNumber = ref('')
const items = ref([])
const selected = ref([])
const file = ref('')
const buildNumber = ref('')
const street = ref('')
const city = ref('')
const alhay = ref('')
const zip = ref('')
const zip2 = ref('')
const unitNumber = ref('')
const fileUpload = ref('')
const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const newPassword = ref('')
const confirmPassword = ref('')


const chooseImage =  ref(avatar);
const snackbarRef = ref(null);


const errors = ref({
  email: undefined,
  phone: undefined,
})


onMounted(() => {
  roleStore.fetchOnlyRoles().then( response =>{
    items.value  = response.data.data['roles']
  });

});


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
  }
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){


      const formData = new FormData();
  
      // Append form field data to the FormData object
      formData.append('name', firstName.value);
      formData.append('birthdate', brithdate.value);
      formData.append('email', email.value);
      formData.append('phone', phoneNumber.value);
      formData.append('image', file.value[0]);
      formData.append('address_building', buildNumber.value);
      formData.append('address_street', street.value);
      formData.append('address_city', city.value);
      formData.append('address_area', alhay.value);
      formData.append('address_zipcode', zip.value);
      formData.append('address_number', zip2.value);
      formData.append('address_unit', unitNumber.value);
      formData.append('password', newPassword.value);
      formData.append('password_confirmation', confirmPassword.value);
      formData.append('roles', selected.value);
      
      userListStore.addUser(formData).then(response => {
        if(response.data['status']){
           snackbarRef.value.exposevisibleSnackbar(i18n.global.t('user_created'), 'success');
           setTimeout(() => {router.push(route.query.to ? String(route.query.to) : '/user/list')}, window.timeOutAfterSubmit);
        }

      }).catch((e=>{
        const { errors: formErrors } = e.response.data
        errors.value = formErrors
      }))
    }else if(!file.value[0]){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.image_missing'), 'error');
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
        <VCard class="padding-30p">    
        <VCard class="border-1p">
        <template v-slot:title>
          <VRow>
            <span class="v-col v-col-6 d-flex gap-4"> {{ $t('Add User') }}</span>

            <VCol
              cols="6"
              class="d-flex gap-4 justify-end"
            >
              <VBtn type="submit">
                {{ $t('Save') }}
              </VBtn>
            </VCol>
          </VRow>

        </template>
          <VCardText>

              <VRow>
                <!-- 👉 First Name -->
                <VCol cols="12" md="12">
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
                        <span class="d-none d-sm-block">{{$t('Add Photo')}}</span>
                      </VBtn>
                    </div>
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="firstName"
                    :label="$t('Full Name')"
                    :placeholder="$t('Full Name')"
                    :rules="[requiredValidator, lengthValidator(firstName, 9)]"
                  />
                </VCol>

                <!-- 👉 Last Name -->
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppDateTimePicker
                    v-model="brithdate"
                    :placeholder="$t('Birth date')"
                    :rules="[requiredValidator]"
                    :label="'Birth date'"
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
                    :rules="[requiredValidator, integerValidator, lengthValidator(phoneNumber, 9)]"
                    persistent-placeholder
                  />
                </VCol>

                <!-- 👉 Email -->
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="email"
                    dir="ltr"
                    :label="$t('Email')"
                    placeholder="user@example.com"
                    :error-messages="errors.email"
                    :rules="[requiredValidator, emailValidator]"
                  />
                </VCol>

                <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="newPassword"
                      dir="ltr"
                      :label="$t('New Password')"
                      :type="isPasswordVisible ? 'text' : 'password'"
                      :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                      @click:append-inner="isPasswordVisible = !isPasswordVisible"
                      :rules="[requiredValidator, passwordValidator]"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="confirmPassword"
                      dir="ltr"
                      :label="$t('Confirm Password')"
                      :type="isConfirmPasswordVisible ? 'text' : 'password'"
                      :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                      @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                      :rules="[requiredValidator, confirmedValidator(newPassword, confirmPassword)]"
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
                  :rules="[requiredValidator, rules]"
                  :label="$t('Add Picture')"
                  accept="image/png, image/jpeg, image/bmp"
                  placeholder="Pick an avatar"
                  prepend-icon=""
                  @change="onFileSelected"
                />
                </VCol>
                <VCol
                  cols="12"
                  md="8"
                >
                  <AppSelect
                    v-model="selected"
                    :items="items"
                    :label="$t('Choose the rules')"
                    :item-title="'name'"
                    :item-value="'id'"
                    chips
                    :rules="[requiredValidator]"
                    multiple
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
        </VCard>
        </VCard>
      </VForm>
      </VCol>
    </VRow>
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>
<route lang="yaml">
  meta:
    action: edit_users
    subject: edit_users
</route>