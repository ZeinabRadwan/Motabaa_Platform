<script setup>
import AuthPageShell from '@/components/auth/AuthPageShell.vue'
import { applyUserSession } from '@core/utils/impersonation'
import { useSessionStore } from '@/stores/useSessionStore'
import axios from '@axios'
import {
  getRememberedEmail,
  isRememberMeEnabled,
} from '@core/utils/authStorage'

import { useUserListStore } from '@/views/apps/user/useUserListStore'
import {
  requiredValidator
} from '@validators'
import { useI18n } from 'vue-i18n'
import { VForm } from 'vuetify/components/VForm'

const brandPrimary = '#075db8'

const { t, locale } = useI18n()
const isRtl = computed(() => locale.value === 'ar')
const fieldDir = computed(() => (isRtl.value ? 'rtl' : 'ltr'))

const isPasswordVisible = ref(false)
const isSubmitting = ref(false)
const loginErrorSummary = ref('')

const passwordEyeIcon = computed(() => (isPasswordVisible.value ? 'tabler-eye-off' : 'tabler-eye'))
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

const togglePasswordVisibility = () => {
  isPasswordVisible.value = !isPasswordVisible.value
}

const login = () => {
  isSubmitting.value = true
  loginErrorSummary.value = ''

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
    loginErrorSummary.value = t('Login credentials invalid')
    errors.value = e.response?.data?.errors ?? {}
  }).finally(() => {
    isSubmitting.value = false
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
  <AuthPageShell
    :dir="fieldDir"
    lock-desktop-viewport
    mobile-card-offset
  >
    <header class="auth-page__intro">
      <h1 class="auth-page__title">
        {{ $t('Welcome Back') }}
      </h1>
      <p class="auth-page__subtitle">
        {{ $t('Login page subtitle') }}
      </p>
    </header>

    <VForm
      ref="refVForm"
      class="auth-page__form"
      @submit.prevent="onSubmit"
    >
      <VAlert
        v-if="loginErrorSummary"
        type="error"
        variant="tonal"
        density="compact"
        class="mb-1"
        role="alert"
      >
        {{ loginErrorSummary }}
      </VAlert>

      <AppTextField
        v-model="email"
        class="auth-field"
        :label="$t('Email/Phone Number')"
        type="text"
        :dir="fieldDir"
        name="username"
        autocomplete="username"
        autofocus
        hide-details="auto"
        :rules="[requiredValidator]"
        append-inner-icon="tabler-mail"
      />

      <AppTextField
        v-model="password"
        class="auth-field"
        :dir="fieldDir"
        :label="$t('Password')"
        name="password"
        autocomplete="current-password"
        hide-details="auto"
        :rules="[requiredValidator]"
        :type="isPasswordVisible ? 'text' : 'password'"
        :error-messages="errors.password"
        append-inner-icon="tabler-lock"
      >
        <template #prepend-inner>
          <button
            type="button"
            class="auth-page__icon-btn"
            :aria-label="$t('Toggle password visibility')"
            @click="togglePasswordVisibility"
          >
            <VIcon
              :icon="passwordEyeIcon"
              size="20"
            />
          </button>
        </template>
      </AppTextField>

      <div class="auth-page__meta">
        <VCheckbox
          v-model="rememberMe"
          class="auth-page__remember"
          :color="brandPrimary"
          hide-details
          density="compact"
          :label="$t('Remember me')"
        />
        <RouterLink
          class="auth-page__link"
          :to="{ name: 'forgot-password' }"
        >
          {{ $t('Forgot Password?') }}
        </RouterLink>
      </div>

      <VBtn
        block
        type="submit"
        variant="flat"
        class="auth-page__submit text-none"
        :color="brandPrimary"
        :loading="isSubmitting"
        :disabled="isSubmitting"
      >
        {{ $t('Login') }}
      </VBtn>

      <p class="auth-page__footer">
        {{ $t('Auth platform footer') }}
      </p>
    </VForm>
  </AuthPageShell>
</template>

<route lang="yaml">
meta:
  layout: blank
  action: read
  subject: Auth
  redirectIfLoggedIn: true
</route>
