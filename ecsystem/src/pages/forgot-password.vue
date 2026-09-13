<script setup>
import i18n from '@/plugins/i18n/index.js'
import axios from '@axios'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

import {
  emailValidator,
  requiredValidator,
} from '@validators'

const refVForm = ref()
const email = ref('')
const snackbarRef = ref(null)

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (isValid) {
      axios.post('/auth/password/reset', {
        email: email.value,
      }).then(r => {
        if (r.data.status == true)
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t('reset_email_sent'), 'success')
      }).catch(e => {
        if (e.response.data.status == false)
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t('Failed to send reset link, Try again later.'), 'error')
      })
    }
  })
}
</script>

<template>
  <div class="motabaa-auth">
    <div class="motabaa-auth__card">
      <div class="motabaa-auth__logo">
        <VNodeRenderer :nodes="themeConfig.app.login_logo" />
      </div>
      <h1 class="motabaa-auth__title">
        {{ $t('Forgot Password?') }}
      </h1>
      <p class="motabaa-auth__subtitle">
        {{ $t('reset_email') }}
      </p>
      <VForm
        ref="refVForm"
        @submit.prevent="onSubmit"
      >
        <AppTextField
          v-model="email"
          class="mb-6"
          autofocus
          dir="ltr"
          :label="$t('Email')"
          :rules="[requiredValidator, emailValidator]"
          type="email"
        />
        <VBtn
          block
          size="large"
          type="submit"
          class="mb-4"
        >
          {{ $t('Send Reset Link') }}
        </VBtn>
        <RouterLink
          class="d-flex align-center justify-center text-primary"
          :to="{ name: 'login' }"
        >
          <VIcon
            icon="tabler-chevron-left"
            class="flip-in-rtl me-1"
          />
          <span>{{ $t('Back to login') }}</span>
        </RouterLink>
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
