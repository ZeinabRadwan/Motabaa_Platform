<script setup>
import { useAppAbility } from '@/plugins/casl/useAppAbility'
import i18n from '@/plugins/i18n/index.js'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { BUNDLED_LOGIN_SIDE_IMAGE } from '@/utils/branding-login'

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
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)

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
<div>
  <VRow
    class="auth-wrapper bg-surface"
    no-gutters
  >
    <VCol
      cols="12"
      lg="4"
      class="auth-card-v2 d-flex align-center justify-center"
    >
      <VCard
        flat
        :max-width="500"
        class="mt-12 mt-sm-0 pa-4"
      >

        <VCardItem class="justify-center">
          <template #prepend>
            <div class="d-flex">
              <VNodeRenderer :nodes="themeConfig.app.login_logo" />
            </div>
          </template>
        </VCardItem>

        <VCardText>
          <h5 class="text-h4 mb-1">
            {{ $t('centers.register_center') }}
          </h5>
        </VCardText>

        <VCardText>
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

              <VCol
                cols="12"
                class="d-flex align-center"
              >
                <VDivider />
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
        </VCardText>
      </VCard>
    </VCol>

    <VCol
      lg="8"
      class="d-none d-lg-flex"
    >
      <div class="position-relative bg-background rounded-lg w-100 ma-8 me-0">
        <div class="d-flex align-center justify-center w-100 h-100">
          <VImg
            max-width="1000"
            :src="BUNDLED_LOGIN_SIDE_IMAGE"
            class="auth-illustration mt-16 mb-2"
          />
        </div>

        <VImg
          max-width="1000"
          :src="authThemeMask"
          class="auth-footer-mask"
        />
      </div>
    </VCol>
  </VRow>
  <SnackbarComponent ref="snackbarRef" />
</div>
</template>

<style lang="scss">
@use "@core/scss/template/pages/page-auth.scss";
</style>

<route lang="yaml">
meta:
  layout: blank
  action: read
  subject: Auth
  redirectIfLoggedIn: true
</route>
