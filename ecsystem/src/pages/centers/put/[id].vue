<script setup>
import i18n from '@/plugins/i18n/index.js';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';

import { centersApi } from "@/plugins/apis/centersRequest";
import { useUserListStore } from '@/views/apps/user/useUserListStore';
import cuntries from "@core/utils/cuntries";
import logo from '@images/motabaah.png';
import { can } from '@layouts/plugins/casl';
import {
  betweenValidator,
  emailValidator,
  integerValidator,
  minimumValidator,
  requiredValidator
} from '@validators';
import { useRoute, useRouter } from 'vue-router';

const rules = [fileList => !fileList || !fileList.length || fileList[0].size < 10000000 || 'Logo size should be less than 10 MB!']

const centerListStore = centersApi()
const userListStore = useUserListStore()
const route = useRoute()
const router = useRouter()
const refVForm = ref()
const center = ref('')
const users = ref([])
const packages = ref([])
const search = ref()
const countrySearch = ref()
const chooseLogo =  ref(logo);
const currentLogo =  ref();
const fileUpload = ref('')
const file = ref('')
const title = ref('')
const nCases = ref('')
const country = ref('')
const address = ref('')
const centerPackage = ref('')
const phoneNumber = ref('')
const managers = ref([])
const commission = ref('')
const crNumber = ref('')
const vatNumber = ref('')
const email = ref('')
const url = ref('')
const managerName = ref('')
const managerPhone = ref('')
const managerEmail = ref('')
const managerPassword = ref('')
const isPasswordVisible = ref(false)
const snackbarRef = ref(null);
const isNew = ref(true);
const imageValidMessage = ref('')
const isDeleteDialogVisible = ref(false);
const canDelete = ref(false);

const loading = ref({
  users: false,
})

const searchManagers = params => userListStore.searchItems({
  ...params,
  center_id: Number(route.params.id),
})

centerListStore.fetchPackages().then(response => {
  packages.value = response.data.data
})

if(Number(route.params.id)>0) {
  isNew.value = false
  centerListStore.fetchCenter(Number(route.params.id)).then(response => {
    center.value = response.data.data
    chooseLogo.value = center.value['logo'] ? center.value['logo']['file_url'] : ''
    currentLogo.value = center.value['logo'] ? center.value['logo']['file_url'] : ''
    title.value = center.value['title'];
    nCases.value = center.value['number_of_cases'];
    country.value = center.value['country'];
    address.value = center.value['city'];
    phoneNumber.value = center.value['phone'];
    centerPackage.value = center.value['package_id'];
    managers.value = center.value['managers_id'];
    commission.value = center.value['commission'];
    crNumber.value = center.value['cr_number'];
    vatNumber.value = center.value['vat_number'];
    email.value = center.value['email'];
    url.value = center.value['url'];

    if(center.value['logo'] && center.value['logo']['file_name'] != 'motabaah')
      canDelete.value = true;
  })
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){

      const formData = new FormData();
      formData.append('id', Number(route.params.id));
      formData.append('title', title.value);
      formData.append('title_local', title.value);
      formData.append('number_of_cases', nCases.value);
      formData.append('country', country.value);
      formData.append('city', address.value);
      formData.append('phone', phoneNumber.value);
      formData.append('package_id', centerPackage.value);
      formData.append('commission', commission.value);
      formData.append('cr_number', crNumber.value);
      formData.append('vat_number', vatNumber.value);
      formData.append('email', email.value);
      formData.append('url', url.value);
      formData.append('status', 1);
      formData.append('manager_name', managerName.value);
      formData.append('manager_phone', managerPhone.value);
      formData.append('manager_email', managerEmail.value);
      formData.append('manager_password', managerPassword.value);
      
      if(file.value[0]) {
        formData.append('logo', file.value[0]);
        formData.append('current_logo', currentLogo.value);
      }

      if(managers.value.length>0)
        formData.append('managers', managers.value);
      
      centerListStore.putCenter(formData).then(response => {
        if(response.data['status']) {
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
          setTimeout(() => {router.push(route.query.to ? String(route.query.to) : '/centers/view/'+response.data.id)}, window.timeOutAfterSubmit);
        }
      }).catch((e=>{
      }))
    }
  })
}

const fileUploadfun = () => {
  fileUpload.value.click()
}

const onFileSelected = () => {
  if (file.value[0]) {
    const reader = new FileReader();
    reader.onload = () => {
      chooseLogo.value = reader.result;
    };
    reader.readAsDataURL(file.value[0]);
    imageValidMessage.value = '';
    canDelete.value = true;
  } else {
    chooseLogo.value = logo;
    canDelete.value = false;
  }
}

const clearSearch = () =>{
    search.value = '';
}

const deleteLogoDialog = () => {
  isDeleteDialogVisible.value = true;
}

const deleteLogo = () => {
  if(Number(route.params.id)>0) {
    centerListStore.deleteLogo(Number(route.params.id)).then(() =>{
      chooseLogo.value =  null;
      currentLogo.value =  null;
      isDeleteDialogVisible.value = false;
      canDelete.value = false;
    })
  }
  else {
    chooseLogo.value =  null;
    currentLogo.value =  null;
    isDeleteDialogVisible.value = false;
    canDelete.value = false;
  }
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
            <template v-slot:title>
              <VRow>
                <span class="v-col v-col-6 d-flex gap-4"> {{ isNew ? $t('centers.add_center') : $t('centers.edit_center') }}</span>

                <VCol
                  cols="6"
                  class="d-flex gap-4 justify-end"
                >
                  <VBtn type="submit">
                    {{ isNew ? $t('Save') : $t('Update') }}
                  </VBtn>
                </VCol>
              </VRow>

            </template>   
            <VCard class="border-1p">
              <VCardText>
                  <VRow>

                    <VCol
                      v-show="false"
                      cols="12"
                      md="4"
                      class="mt-1"
                    >
                    <v-label> {{ $t('centers.logo') }}</v-label>
                    <VFileInput
                      ref="fileUpload"
                      v-model="file"
                      :label="$t('centers.add_logo')"
                      accept="image/png, image/jpeg, image/jpg, image/bmp"
                      placeholder="centers.choose_logo"
                      prepend-icon=""
                      @change="onFileSelected"
                      :rules="[rules]"
                    />
                    </VCol>

                    <VCol cols="12" md="12">

                      <VLabel
                          class="mb-1 text-body-2 text-high-emphasis d-block "
                          :text="$t('centers.logo')"
                      />
                      <VAvatar
                        style="cursor: pointer;"
                        @click="fileUploadfun"
                        rounded="lg"
                        :size="130"
                        color="primary"
                        :variant="'tonal'"
                      >
                        <VImg width="130px"
                          :src="chooseLogo ?? logo" 
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
                          <span class="d-none d-sm-block">{{ canDelete ? $t('Edit Photo') : $t('Add Photo') }}</span>
                          <template v-slot:append>

                          </template>
                        </VBtn>
                        <VBtn
                          v-if="canDelete"
                          color="error"
                          @click="deleteLogoDialog"
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

                    <VCol cols="12" md="4">
                      <AppTextField
                        v-model="title"
                        labelClasses="red-asterisk-label"
                        :label="$t('Name')"
                        :rules="[requiredValidator]"
                      />
                    </VCol>

                    <VCol
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="phoneNumber"
                        type="number"
                        dir="ltr"
                        labelClasses="red-asterisk-label"
                        :label="$t('Phone Number')"
                        :rules="[requiredValidator, integerValidator, betweenValidator(phoneNumber, 12, 12)]"
                      />
                    </VCol>

                    <VCol 
                      v-if="isNew"
                      cols="12"
                      md="4"
                    >
                      <AppSelect
                        v-model="centerPackage"
                        labelClasses="red-asterisk-label"
                        :items="packages"
                        :item-title="'title'"
                        :item-value="'id'"
                        :label="$t('centers.subscription_type')"
                        :rules="[requiredValidator]"
                        clearable
                        clear-icon="tabler-x"
                      />
                    </VCol>

                    <VCol 
                      v-if="isNew"
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="nCases"
                        type="number"
                        labelClasses="red-asterisk-label"
                        :label="$t('centers.number_of_cases') + ' (' + $t('centers.minimum_number_is_50') + ')'"
                        :rules="[requiredValidator, minimumValidator(nCases, 50)]"
                      />
                    </VCol>

                    <VCol 
                      cols="12"
                      md="4"
                    >
                      <AppSelect
                        v-model="country"
                        v-model:search="countrySearch"
                        labelClasses="red-asterisk-label"
                        :items="cuntries"
                        :item-title='i18n.global.locale.value == "ar" ? "country_arName" : "country_enName"'
                        item-value='country_code'
                        :label="$t('centers.country')"
                        :rules="[requiredValidator]"
                        clearable
                        clear-icon="tabler-x"
                      />
                    </VCol>

                    <VCol 
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="address"
                        labelClasses="red-asterisk-label"
                        :label="$t('centers.address')"
                        :rules="[requiredValidator]"
                      />
                    </VCol>

                    <VCol 
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="commission"
                        :label="$t('centers.commission')"
                      />
                    </VCol>

                    <VCol 
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="crNumber"
                        :label="$t('centers.cr_number')"
                        dir="ltr"
                      />
                    </VCol>

                    <VCol 
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="vatNumber"
                        :label="$t('centers.vat_number')"
                        dir="ltr"
                      />
                    </VCol>

                    <VCol 
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="email"
                        :label="$t('centers.email')"
                        dir="ltr"
                      />
                    </VCol>

                    <VCol 
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="url"
                        :label="$t('centers.url')"
                        dir="ltr"
                      />
                    </VCol>

                    <VCol
                      v-if="!isNew && can('admin', 'admin')"
                      cols="6"
                      md="8"
                    >
                    <AppAutocomplete
                        v-model="managers"
                        :server-search="searchManagers"
                        :item-title="'name'"
                        :item-value="'id'"
                        :label="$t('centers.managers')"
                        closable-chips
                        clearable
                        multiple
                        chips
                    />
                    </VCol>

                    <VDivider v-if="isNew" class="mt-3 mb-3"/>

                    <!-- name -->
                    <VCol v-if="isNew" cols="12">
                      <h5 class="text-h5"> {{ $t('centers.manager_data') }}</h5>
                    </VCol>

                    <!-- name -->
                    <VCol 
                      v-if="isNew"
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="managerName"
                        :label="$t('name')"
                        labelClasses="red-asterisk-label"
                        autofocus
                        :rules="[requiredValidator]"
                      />
                    </VCol>

                    <!-- email -->
                    <VCol 
                      v-if="isNew"
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="managerPhone"
                        :label="$t('phone')"
                        labelClasses="red-asterisk-label"
                        :rules="[requiredValidator, integerValidator, betweenValidator(managerPhone, 12, 12)]"
                        dir="ltr"
                      />
                    </VCol>

                    <!-- email -->
                    <VCol 
                      v-if="isNew"
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="managerEmail"
                        :label="$t('Email')"
                        labelClasses="red-asterisk-label"
                        type="email"
                        :rules="[requiredValidator, emailValidator]"
                        dir="ltr"
                      />
                    </VCol>

                    <!-- password -->
                    <VCol 
                      v-if="isNew"
                      cols="12"
                      md="4"
                    >
                      <AppTextField
                        v-model="managerPassword"
                        :label="$t('Password')"
                        labelClasses="red-asterisk-label"
                        :rules="[requiredValidator]"
                        :type="isPasswordVisible ? 'text' : 'password'"
                        :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                        @click:append-inner="isPasswordVisible = !isPasswordVisible"
                        dir="ltr"
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
          {{ $t('centers.Are you sure you want to delete this logo?') }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn @click="deleteLogo">
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
<route lang="yaml">
  meta:
    action: edit_centers
    subject: edit_centers
</route>