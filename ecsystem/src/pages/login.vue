<script setup>
import { applyUserSession } from '@core/utils/impersonation'
import { useSessionStore } from '@/stores/useSessionStore'
import axios from '@axios'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
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
  <div class="motabaa-auth">
    <div class="motabaa-auth__card">
      <div class="motabaa-auth__logo">
        <VNodeRenderer :nodes="themeConfig.app.login_logo" />
      </div>
      <h1 class="motabaa-auth__title">
        {{ $t('Welcome Back') }}
      </h1>
      <p class="motabaa-auth__subtitle">
        {{ $t('login_subtitle') }}
      </p>
      <VForm
        ref="refVForm"
        @submit.prevent="onSubmit"
      >
        <AppTextField
          v-model="email"
          class="mb-4"
          :label="$t('Email/Phone Number')"
          type="email"
          dir="ltr"
          autofocus
          :rules="[requiredValidator]"
          :error-messages="errors.email"
        />
        <AppTextField
          v-model="password"
          class="mb-2"
          dir="ltr"
          :label="$t('Password')"
          :rules="[requiredValidator]"
          :type="isPasswordVisible ? 'text' : 'password'"
          :error-messages="errors.password"
          :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
          @click:append-inner="isPasswordVisible = !isPasswordVisible"
        />
        <div class="d-flex align-center flex-wrap justify-space-between mb-6">
          <VCheckbox
            v-model="rememberMe"
            :label="$t('Remember me')"
          />
          <RouterLink
            class="text-primary"
            :to="{ name: 'forgot-password' }"
          >
            {{ $t('Forgot Password?') }}
          </RouterLink>
        </div>
        <VBtn
          block
          size="large"
          type="submit"
        >
          {{ $t('Login') }}
        </VBtn>
      </VForm>
      <p class="motabaa-auth__footer">
        {{ $t('copyright', { year: new Date().getFullYear() }) }}
      </p>
    </div>
  </div>
</template>

<route lang="yaml">
meta:
  layout: blank
  action: read
  subject: Auth
  redirectIfLoggedIn: true
</route>
