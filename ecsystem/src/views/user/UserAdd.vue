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
betweenValidator,
passwordValidator,
requiredValidator,
requiredValidatorIf,
passwordValidatorIf
} from '@validators';
import { useRoute, useRouter } from 'vue-router';
import cuntries from "@core/utils/cuntries";
import {
  genderTypes,
  departmentItems,
  contractTypeItems,
  workShiftItems,
  serializeWorkShift,
} from '@core/utils/generalItems';

const roleStore = useRoleStore()
const userListStore = useUserListStore()
const rules = [fileList => !fileList || !fileList.length || fileList[0].size < 10000000 || 'Avatar size should be less than 10 MB!']

const props = defineProps(['parent']);
const { parent } = toRefs(props);
const route = useRoute()
const router = useRouter()
const refVForm = ref()
const firstName = ref('')
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
const hasAccount = ref('hide')
const onCenterSponsorship = ref(0)
const qualification = ref('')
const specialization = ref('')
const preciseSpecialization = ref('')
const currentWork = ref('')
const nationality = ref('')
const jobTitle = ref('')
const department = ref('')
const workShifts = ref([])
const contractType = ref('')
const hireDate = ref('')
const contractEndDate = ref('')
const idExpiryDate = ref('')
const annualLeaveEntitlement = ref(21)
const birthdate = ref('')
const gender = ref('')
const idOrResidenceNumber = ref('')


const chooseImage =  ref(avatar);
const isDeleteDialogVisible = ref(false);
const snackbarRef = ref(null);


const errors = ref({
})

watch(hasAccount, query => {
  if(query == 'hide'){
    newPassword.value = ''
    confirmPassword.value = ''
  }
})

onMounted(() => {
  roleStore.fetchOnlyRoles().then( response =>{
    items.value  = response.data.data['roles']
    if(parent.value){
      selected.value =[response.data.data['roles'].find(item => item.default_name === "parent").id]
    }else{
      items.value = items.value.filter(item => item.default_name !== "parent")
    }
  });
  hasAccount.value = parent.value == true ? 'hide' : 'show'
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
  if(hasAccount.value == 'hide'){
    newPassword.value = ''
    confirmPassword.value = ''
  }
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){


      const formData = new FormData();
  
      // Append form field data to the FormData object
      formData.append('center_id', Number(localStorage.getItem('center')));
      formData.append('name', firstName.value);
      formData.append('email', email.value);
      formData.append('phone', phoneNumber.value);
      if(file.value[0]){
        formData.append('image', file.value[0]);
      }
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
      formData.append('can_login', hasAccount.value);
      formData.append('on_center_sponsorship', onCenterSponsorship.value);
      formData.append('qualification', qualification.value);
      formData.append('specialization', specialization.value);
      formData.append('precise_specialization', preciseSpecialization.value);
      formData.append('current_work', currentWork.value);
      formData.append('nationality', nationality.value);
      formData.append('job_title', jobTitle.value);
      formData.append('department', department.value);
      formData.append('work_shift', serializeWorkShift(workShifts.value));
      formData.append('contract_type', contractType.value);
      formData.append('hire_date', hireDate.value);
      formData.append('contract_end_date', contractEndDate.value);
      formData.append('id_expiry_date', idExpiryDate.value);
      formData.append('annual_leave_entitlement', annualLeaveEntitlement.value);
      formData.append('birthdate', birthdate.value);
      formData.append('id_or_residence_number', idOrResidenceNumber.value);
      formData.append('gender', gender.value);
      
      userListStore.addUser(formData).then(response => {
        if(response.data['status']){
          if(parent.value){
            snackbarRef.value.exposevisibleSnackbar(i18n.global.t('parent_created'), 'success');
          }else{
            snackbarRef.value.exposevisibleSnackbar(i18n.global.t('user_created'), 'success');
          }
          setTimeout(() => {router.push(route.query.to ? String(route.query.to) : '/user/list')}, window.timeOutAfterSubmit);
        }

      }).catch((e=>{
        const { errors: formErrors } = e.response.data
        errors.value = formErrors
      }))
    }else{
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.missing_field'), 'error');
    }
  })
}

const deleteImageDialog = () => {
  isDeleteDialogVisible.value = true;
}

const deleteImage = () => {
  chooseImage.value =  null;
  isDeleteDialogVisible.value = false;
}
</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <!-- 👉 Multiple Column -->
        <VForm 
              ref="refVForm"
              autocomplete="off"
              @submit.prevent="onSubmit"
            >
        <VCard class="padding-30p">    
        <VCard class="border-1p">
        <template v-slot:title>
          <VRow>
            <span class="v-col v-col-6 d-flex gap-4"> {{ parent ? $t('Add Parent') : $t('Add User') }}</span>

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
                  <VLabel
                        class="mb-1 text-body-2 text-high-emphasis d-block "
                        :text="$t('picture')"
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
                        <span class="d-none d-sm-block">{{$t('Add Photo')}}</span>
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
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="firstName"
                    labelClasses="red-asterisk-label"
                    :label="$t('Full Name')"
                    :placeholder="$t('Full Name')"
                    :rules="[requiredValidator, lengthValidator(firstName, 9)]"
                  />
                </VCol>

                <!-- 👉 Phone Number -->
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="phoneNumber"
                    dir="ltr"
                    labelClasses="red-asterisk-label"
                    :label="$t('Phone Number')"
                    placeholder="966xxxxxxxxx"
                    :rules="[requiredValidator, integerValidator, betweenValidator(phoneNumber, 12, 12)]"
                    persistent-placeholder
                    hint="EX: 966xxxxxxxxx"
                    persistent-hint
                  />
                </VCol>

                <!-- 👉 ID Or Residence Number -->
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="idOrResidenceNumber"
                    :labelClasses="hasAccount == 'hide' ? '' : 'red-asterisk-label'"
                    dir="ltr"
                    :label="$t('id_or_residence_number')"
                    :rules="[requiredValidatorIf(idOrResidenceNumber, hasAccount == 'hide'), integerValidator]"
                  />
                </VCol>

              <!-- 👉 Select Birth Date -->
              <VCol
                cols="12"
                sm="8"
              >
                <div class="pa-1">
                  {{ $t('Birth date') }} <span v-if="hasAccount != 'hide'" class="red-asterisk-label"></span>
                </div>
                <VRow>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppDateTimePicker
                      v-model="birthdate"
                      :placeholder="$t('Birth date')"
                      :rules="[requiredValidatorIf(birthdate, hasAccount == 'hide')]"
                    />
                  </VCol>
                  
                  <!-- 👉 Select End Date -->
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppHijriDate
                      v-model="birthdate"
                      :placeholder="$t('Birth date')"
                      :rules="[requiredValidatorIf(birthdate, hasAccount == 'hide')]"
                    />
                  </VCol>
                </VRow>
              </VCol>
                  
                <VCol
                  cols="6"
                  md="2"
                >
                  <AppSelect
                    v-model="gender"
                    :labelClasses="hasAccount == 'hide' ? '' : 'red-asterisk-label'"
                    :items="genderTypes()"
                    :label="$t('gender')"
                    :rules="[requiredValidatorIf(gender, hasAccount == 'hide')]"
                    clearable
                    clear-icon="tabler-x"
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
                    :labelClasses="hasAccount == 'hide' ? '' : 'red-asterisk-label'"
                    :label="$t('Email')"
                    placeholder="user@example.com"
                    :rules="[requiredValidatorIf(email, hasAccount == 'hide'), emailValidator]"
                  />
                </VCol>

                <VCol
                    cols="12"
                    md="12"
                  >
                  <VCheckbox
                    v-model="hasAccount"
                    true-value="show"
                    false-value="hide"
                    color="success"
                    :label="$t('has_user_account')"
                  />
                </VCol>
                <VCol
                    v-show="hasAccount == 'show'"
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="newPassword"
                      dir="ltr"
                      labelClasses="red-asterisk-label"
                      :label="$t('New Password')"
                      :type="isPasswordVisible ? 'text' : 'password'"
                      :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                      @click:append-inner="isPasswordVisible = !isPasswordVisible"
                      :rules="[requiredValidatorIf(newPassword, hasAccount == 'hide'), passwordValidatorIf(newPassword, hasAccount == 'hide')]"
                    />
                  </VCol>
                  <VCol
                    v-show="hasAccount == 'show'"
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="confirmPassword"
                      dir="ltr"
                      labelClasses="red-asterisk-label"
                      :label="$t('Confirm Password')"
                      :type="isConfirmPasswordVisible ? 'text' : 'password'"
                      :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                      @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                      :rules="[requiredValidatorIf(confirmPassword, hasAccount == 'hide'), confirmedValidator(newPassword, confirmPassword)]"
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
                  accept="image/png, image/jpg, image/jpeg, image/bmp"
                  placeholder="Pick an avatar"
                  prepend-icon=""
                  @change="onFileSelected"
                />
                </VCol>
                <VCol
                  v-show="!parent"
                  cols="12"
                  md="8"
                >
                  <AppSelect
                    v-model="selected"
                    :items="items"
                    labelClasses="red-asterisk-label"
                    :label="$t('Choose the rules')"
                    :item-title="'name'"
                    :item-value="'id'"
                    chips
                    :rules="[requiredValidatorIf(selected, hasAccount == 'hide')]"
                    multiple
                  />
                </VCol>
                  
                  <VCol
                    v-show="!parent"
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="qualification"
                      :label="$t('qualification')"
                    />
                  </VCol>
                  
                  <VCol
                    v-show="!parent"
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="specialization"
                      :label="$t('specialization')"
                    />
                  </VCol>
                  
                  <VCol
                    v-show="!parent"
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="preciseSpecialization"
                      :label="$t('precise_specialization')"
                    />
                  </VCol>
                  
                  <VCol
                    v-show="!parent"
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="currentWork"
                      :label="$t('current_work')"
                    />
                  </VCol>
                  
                  <VCol
                    v-show="!parent"
                    cols="12"
                    md="4"
                  >
                    <AppSelect
                      v-model="nationality"
                      :items="cuntries"
                      :label="$t('nationality')"
                      :item-title='i18n.global.locale.value == "ar" ? "country_arNationality" : "country_enNationality"'
                      item-value='country_code'
                      clearable
                      clear-icon="tabler-x"
                    />
                  </VCol>

                  <VCol
                    v-if="!parent && nationality != 'SA'"
                    cols="12"
                    md="12"
                  >
                    <VCheckbox
                      v-model="onCenterSponsorship"
                      :true-value="1"
                      :false-value="0"
                      color="success"
                      :label="$t('on_center_sponsorship')"
                    />
                  </VCol>

                  <template v-if="!parent">
                    <VCol cols="12">
                      <h4 class="text-h6">{{ $t('employee_affairs.employment_data') }}</h4>
                    </VCol>
                    <VCol cols="12" md="4">
                      <AppTextField
                        v-model="jobTitle"
                        :label="$t('employee_affairs.job_title')"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <AppSelect
                        v-model="department"
                        :items="departmentItems()"
                        :label="$t('Department')"
                        clearable
                        clear-icon="tabler-x"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <AppSelect
                        v-model="workShifts"
                        :items="workShiftItems()"
                        :label="$t('employee_affairs.work_shift')"
                        multiple
                        chips
                        closable-chips
                        clearable
                        clear-icon="tabler-x"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <AppSelect
                        v-model="contractType"
                        :items="contractTypeItems()"
                        :label="$t('employee_affairs.contract_type')"
                        clearable
                        clear-icon="tabler-x"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <AppDateTimePicker
                        v-model="hireDate"
                        :placeholder="$t('employee_affairs.hire_date')"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <AppDateTimePicker
                        v-model="contractEndDate"
                        :placeholder="$t('employee_affairs.contract_end_date')"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <AppDateTimePicker
                        v-model="idExpiryDate"
                        :placeholder="$t('employee_affairs.id_expiry_date')"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <AppTextField
                        v-model="annualLeaveEntitlement"
                        type="number"
                        :label="$t('employee_affairs.annual_leave_entitlement')"
                      />
                    </VCol>
                  </template>

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
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>