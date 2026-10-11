<script setup>
import { applyUserSession } from '@core/utils/impersonation'
import { useSessionStore } from '@/stores/useSessionStore'
import axios from '@axios'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { BUNDLED_LOGIN_SIDE_IMAGE } from '@/utils/branding-login'
import {
  getRememberedEmail,
  isRememberMeEnabled,
} from '@core/utils/authStorage'

import { useUserListStore } from '@/views/apps/user/useUserListStore'
import { themeConfig } from '@themeConfig'
import {
  requiredValidator
} from '@validators'
import { VForm } from 'vuetify/components/VForm'

const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)
const isPasswordVisible = ref(false)
const route = useRoute()
const router = useRouter()
const sessionStore = useSessionStore()
const userListStore = useUserListStore()

const errors = ref({
  email: undefined,
  password: undefined,
})

const refVForm = ref()
const email = ref(getRememberedEmail())
const password = ref('')
const rememberMe = ref(isRememberMeEnabled() || !!email.value)

const login = () => {
  axios.post('/auth/login', {
    email: email.value,
    password: password.value,
  }).then(r => {
    const { token, user } = r.data

    applyUserSession({
      token,
      user,
      remember: rememberMe.value,
      email: email.value,
    })

    const redirectTo = route.query.to ? String(route.query.to) : '/'
    const go = () => router.replace(redirectTo)

    if (sessionStore.center) {
      const centerTitle = sessionStore.centerData?.title || user.centers?.[0]?.title || ''

      userListStore.fetchImageWhileWaiting(sessionStore.center, { image: 'center_logo' }).then(response => {
        if (response.data.data)
          sessionStore.setCenterData({ title: centerTitle, image_path: response.data.data.file_url })
        go()
      }).catch(go)
    }
    else {
      go()
    }
  }).catch(e => {
    const { errors: formErrors } = e.response.data
    errors.value = formErrors
  })
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid)
      login()
  })
}
</script>

<template>
  <VRow
    no-gutters
    class="auth-wrapper bg-surface"
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
            {{ $t('Welcome Back') }}
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
                  v-model="email"
                  :label="$t('Email/Phone Number')"
                  type="email"
                  dir="ltr"
                  autofocus
                  :rules="[requiredValidator]"
                />
              </VCol>

              <!-- password -->
              <VCol cols="12">
                <AppTextField
                  v-model="password"
                  dir="ltr"
                  :label="$t('Password')"
                  :rules="[requiredValidator]"
                  :type="isPasswordVisible ? 'text' : 'password'"
                  :error-messages="errors.password"
                  :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                  @click:append-inner="isPasswordVisible = !isPasswordVisible"
                />

                <div class="d-flex align-center flex-wrap justify-space-between mt-2 mb-1">
                  <VCheckbox
                    v-model="rememberMe"
                    :label="$t('Remember me')"
                  />
                  <RouterLink
                    class="text-primary ms-2 mb-1"
                    :to="{ name: 'forgot-password' }"
                  >
                    {{ $t('Forgot Password?') }}
                  </RouterLink>
                </div>

                <VCol
                  cols="12"
                  class="d-flex align-center mb-4"
                >
                  <VDivider />
                </VCol>
                
                <VBtn
                  block
                  type="submit"
                >
                  {{ $t('Login') }}
                </VBtn>
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
