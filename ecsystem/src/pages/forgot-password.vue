<script setup>
import i18n from '@/plugins/i18n/index.js'
import axios from '@axios'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { BUNDLED_LOGIN_SIDE_IMAGE } from '@/utils/branding-login'

import {
emailValidator,
requiredValidator,
} from '@validators'

const refVForm = ref()
const email = ref('')
const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)


const snackbarRef = ref(null);

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){

      axios.post('/auth/password/reset', {
        email: email.value,
      }).then(r => {
        if(r.data['status'] == true){
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t('reset_email_sent'), 'success');
        }
        
      }).catch(e => {
        if(e['response'].data['status'] == false){
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t('Failed to send reset link, Try again later.'), 'error');
        }
      })

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
          <h5 class="text-h5 mb-1">
            {{ $t('Forgot Password?') }}
          </h5>
          <p class="mb-0">
            {{$t('reset_email')}}
          </p>
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
                  v-model="email"
                  autofocus
                  dir="ltr"
                  :label="$t('Email')"
                  :rules="[requiredValidator, emailValidator]"
                  type="email"
                />
              </VCol>

              <!-- Reset link -->
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                >
                  {{ $t('Send Reset Link') }}
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
