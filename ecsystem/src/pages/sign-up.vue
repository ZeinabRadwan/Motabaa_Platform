<script setup>
import { useAppAbility } from '@/plugins/casl/useAppAbility'
import i18n from '@/plugins/i18n/index.js'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

import { centersApi } from "@/plugins/apis/centersRequest"
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import cuntries from "@core/utils/cuntries"
import {
  minimumValidator,
  betweenValidator,
  emailValidator,
  integerValidator,
  requiredValidator
} from '@validators'
import { useRoute, useRouter } from 'vue-router'
import { VForm } from 'vuetify/components/VForm'

const errors = ref({
  email: undefined,
  password: undefined,
})

const refVForm = ref()
const route = useRoute()
const router = useRouter()
const ability = useAppAbility()
const centerListStore = centersApi()
const userListStore = useUserListStore()

const packages = ref([])
const center = ref('')
const centerName = ref('')
const centerCountry = ref('')
const centerCity = ref('')
const centerPackage = ref('')
const nCases = ref('')
const name = ref('')
const email = ref('')
const phone = ref('')
const password = ref('')
const isPasswordVisible = ref(false)

const snackbarRef = ref(null);

centerListStore.fetchPackages().then(response => {
  packages.value = response.data.data
})

const register = () => {

  const formData = new FormData();
  formData.append('id', null);
  formData.append('name', name.value);
  formData.append('email', email.value);
  formData.append('phone', phone.value);
  formData.append('password', password.value);
  formData.append('title', centerName.value);
  formData.append('title_local', centerName.value);
  formData.append('number_of_cases', nCases.value);
  formData.append('country', centerCountry.value);
  formData.append('city', centerCity.value);
  formData.append('package_id', centerPackage.value);

  userListStore.registerUser(formData).then(response => {

    if(response.data.status == 'success'){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success');
      setTimeout(() => {router.push('/activation_page')}, 2000);
    }
  }).catch((e=>{
  }))
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){
      register()
    }
  })
}

</script>

<template>
  <div class="motabaa-auth">
    <div class="motabaa-auth__card motabaa-auth__card--wide">
      <div class="motabaa-auth__logo">
        <VNodeRenderer :nodes="themeConfig.app.login_logo" />
      </div>
      <h1 class="motabaa-auth__title">
        {{ $t('centers.register_center') }}
      </h1>
      <p class="motabaa-auth__subtitle">
        {{ $t('register_subtitle') }}
      </p>
      <VForm
        ref="refVForm"
        @submit.prevent="onSubmit"
      >
            <VRow>

              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="centerName"
                  labelClasses="red-asterisk-label"
                  :label="$t('centers.name')"
                  :rules="[requiredValidator]"
                />
              </VCol>

              <VCol cols="12">
                <AppSelect
                  v-model="centerCountry"
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

              <VCol cols="12">
                <AppTextField
                  v-model="centerCity"
                  labelClasses="red-asterisk-label"
                  :label="$t('centers.address')"
                  :rules="[requiredValidator]"
                />
              </VCol>

              <VCol cols="12">
                <AppSelect
                  v-model="centerPackage"
                  labelClasses="red-asterisk-label"
                  :items="packages"
                  :item-title="'title'"
                  :item-value="'id'"
                  :label="$t('centers.package')"
                  :rules="[requiredValidator]"
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>

              <VCol cols="12">
                <AppTextField
                  v-model="nCases"
                  type="number"
                  labelClasses="red-asterisk-label"
                  :label="$t('centers.number_of_cases') + ' (' + $t('centers.minimum_number_is_50') + ')'"
                  :rules="[requiredValidator, minimumValidator(nCases, 50)]"
                />
              </VCol>

              <!-- name -->
              <VCol cols="12">
                <AppTextField
                  v-model="name"
                  :label="$t('name')"
                  labelClasses="red-asterisk-label"
                  dir="ltr"
                  autofocus
                  :rules="[requiredValidator]"
                />
              </VCol>

              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="phone"
                  :label="$t('phone')"
                  labelClasses="red-asterisk-label"
                  dir="ltr"
                  :rules="[requiredValidator, integerValidator, betweenValidator(phone, 12, 12)]"
                />
              </VCol>

              <!-- email -->
              <VCol cols="12">
                <AppTextField
                  v-model="email"
                  :label="$t('Email')"
                  labelClasses="red-asterisk-label"
                  type="email"
                  dir="ltr"
                  :rules="[requiredValidator, emailValidator]"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <AppTextField
                  v-model="password"
                  dir="ltr"
                  :label="$t('Password')"
                  labelClasses="red-asterisk-label"
                  :rules="[requiredValidator]"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :error-messages="errors.password"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />
              </VCol>
                
              <VCol cols="12">
                <VBtn
                  block
                  size="large"
                  type="submit"
                >
                  {{ $t('Register') }}
                </VBtn>
              </VCol>

              <!-- back to login -->
              <VCol cols="12">
                <RouterLink
                  class="d-flex align-center justify-center"
                  :to="{ name: 'login' }"
                >
                  <VIcon
                    icon="tabler-chevron-left"
                    class="flip-in-rtl"
                  />
                  <span>{{ $t('Back to login') }}</span>
                </RouterLink>
              </VCol>
            </VRow>
          </VForm>
    </div>
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>

<route lang="yaml">
meta:
  layout: blank
  action: read
  subject: Auth
  redirectIfLoggedIn: true
</route>
