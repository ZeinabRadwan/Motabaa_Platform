<script setup>
import i18n from '@/plugins/i18n/index.js'
import axios from '@axios'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'
import authV1BottomShape from '@images/svg/auth-v1-bottom-shape.svg?raw'
import authV1TopShape from '@images/svg/auth-v1-top-shape.svg?raw'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

import {
confirmedValidator,
passwordValidator,
requiredValidator
} from '@validators'
import { useRoute, useRouter } from 'vue-router'


const refVForm = ref()
const route = useRoute()
const router = useRouter()

const form = ref({
  newPassword: '',
  confirmPassword: '',
})

const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const snackbarRef = ref(null);

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){
      if(route.query.email != null && route.query.email != '' && route.query.token != null && route.query.token != ''){
        axios.post('/auth/password/reset/'+route.query.token, {
          email: route.query.email,
          token: route.query.token,
          password: form.value.newPassword,
          password_confirmation: form.value.confirmPassword,
        }).then(r => {
          if(r.data['status'] == true){
            snackbarRef.value.exposevisibleSnackbar(i18n.global.t('The password has been changed.'), 'success');
            router.replace(route.query.to ? String(route.query.to) : '/login')
          }
          
        }).catch(e => {
          if(e['response'].data['status'] == false){
            snackbarRef.value.exposevisibleSnackbar(i18n.global.t('expired_token'), 'error');
          }
        })
      }else{
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('The URL is broken.'), 'error');
      }

    }
  })
}
</script>

<template>
  <div class="auth-wrapper d-flex align-center justify-center pa-4">
    <div class="position-relative my-sm-16">
      <!-- 👉 Top shape -->
      <VNodeRenderer
        :nodes="h('div', { innerHTML: authV1TopShape })"
        class="text-primary auth-v1-top-shape d-none d-sm-block"
      />

      <!-- 👉 Bottom shape -->
      <VNodeRenderer
        :nodes="h('div', { innerHTML: authV1BottomShape })"
        class="text-primary auth-v1-bottom-shape d-none d-sm-block"
      />

      <!-- 👉 Auth Card -->
      <VCard
        class="auth-card pa-4"
        max-width="448"
      >
        <VCardItem class="justify-center">
          <template #prepend>
            <div class="d-flex">
              <VNodeRenderer :nodes="themeConfig.app.logo" />
            </div>
          </template>

          <VCardTitle class="font-weight-bold text-capitalize text-h5 py-1">
            {{ $t(themeConfig.app.title) }}
          </VCardTitle>
        </VCardItem>

        <VCardText class="pt-2">
          <h5 class="text-h5 mb-1">
            {{ $t('Reset Password') }}
          </h5>
          <p class="mb-0">
            {{ $t('reset_email_for') }} <span class="font-weight-bold">{{route.query.email}}</span>
          </p>
        </VCardText>

        <VCardText>
          <VForm 
            ref="refVForm"
            @submit.prevent="onSubmit" 
          >
            <VRow>
              <!-- password -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.newPassword"
                  autofocus
                  dir="ltr"
                  :label="$t('New Password')"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                  :rules="[requiredValidator, passwordValidator]"
                />
              </VCol>

              <!-- Confirm Password -->
              <VCol cols="12">
                <AppTextField
                  v-model="form.confirmPassword"
                  :label="$t('Confirm Password')"
                  dir="ltr"
                  :type="isConfirmPasswordVisible ? 'text' : 'password'"
                  :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                  :rules="[requiredValidator, confirmedValidator(form.newPassword, form.confirmPassword)]"
                />
              </VCol>

              <!-- reset password -->
              <VCol cols="12">
                <VBtn
                  block
                  type="submit"
                >
                  {{ $t('Set New Password') }}
                </VBtn>
              </VCol>

              <!-- back to login -->
              <VCol cols="12">
                <RouterLink
                  class="d-flex align-center justify-center"
                  :to="{ name: 'pages-authentication-login-v1' }"
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
    </div>
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