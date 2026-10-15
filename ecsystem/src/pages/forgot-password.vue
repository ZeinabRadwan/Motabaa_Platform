<script setup>
import AuthPageShell from '@/components/auth/AuthPageShell.vue'
import i18n from '@/plugins/i18n/index.js'
import axios from '@axios'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'
import {
  loginEmailOrPhoneValidator,
  loginIdentifierRequiredValidator,
} from '@validators'
import { useI18n } from 'vue-i18n'
import { VForm } from 'vuetify/components/VForm'

const brandPrimary = '#075db8'

const { locale, t } = useI18n()
const isRtl = computed(() => locale.value === 'ar')
const fieldDir = computed(() => (isRtl.value ? 'rtl' : 'ltr'))

const refVForm = ref()
const email = ref('')
const isSubmitting = ref(false)
const formErrorSummary = ref('')
const formSuccessSummary = ref('')
const snackbarRef = ref(null)

const showResetLinkSent = () => {
  const message = i18n.global.t('reset_email_sent')
  formSuccessSummary.value = message
  formErrorSummary.value = ''
  snackbarRef.value?.exposevisibleSnackbar(message, 'success', 6000)
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (!isValid)
      return

    isSubmitting.value = true
    formErrorSummary.value = ''
    formSuccessSummary.value = ''

    axios.post('/auth/password/reset', {
      email: email.value.trim(),
    }).then(r => {
      if (r.data?.status === true)
        showResetLinkSent()
    }).catch(e => {
      const failed = e.response?.data?.status === false
        || e.response?.status >= 500
      const validationMsg = e.response?.data?.errors?.email?.[0]
      if (validationMsg) {
        formErrorSummary.value = validationMsg
        snackbarRef.value?.exposevisibleSnackbar(validationMsg, 'error')
      }
      else if (failed) {
        formErrorSummary.value = t('Failed to send reset link, Try again later.')
        snackbarRef.value?.exposevisibleSnackbar(i18n.global.t('Failed to send reset link, Try again later.'), 'error')
      }
    }).finally(() => {
      isSubmitting.value = false
    })
  })
}
</script>

<template>
  <AuthPageShell :dir="fieldDir">
    <header class="auth-page__intro">
      <h1 class="auth-page__title">
        {{ $t('Forgot Password?') }}
      </h1>
      <p class="auth-page__subtitle">
        {{ $t('reset_email') }}
      </p>
    </header>

    <VForm
      ref="refVForm"
      class="auth-page__form"
      @submit.prevent="onSubmit"
    >
      <VAlert
        v-if="formSuccessSummary"
        type="success"
        variant="tonal"
        density="comfortable"
        class="mb-3"
        role="status"
        prominent
      >
        {{ formSuccessSummary }}
      </VAlert>

      <VAlert
        v-if="formErrorSummary"
        type="error"
        variant="tonal"
        density="compact"
        class="mb-1"
        role="alert"
      >
        {{ formErrorSummary }}
      </VAlert>

      <AppTextField
        v-model="email"
        class="auth-field"
        autofocus
        :dir="fieldDir"
        :label="$t('Email/Phone Number')"
        name="email"
        autocomplete="username"
        hide-details="auto"
        :rules="[loginIdentifierRequiredValidator, loginEmailOrPhoneValidator]"
        type="text"
        append-inner-icon="tabler-mail"
      />

      <div class="auth-page__meta auth-page__meta--single">
        <RouterLink
          class="auth-page__link"
          :to="{ name: 'login' }"
        >
          {{ $t('Back to login') }}
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
        {{ $t('Send Reset Link') }}
      </VBtn>
    </VForm>
  </AuthPageShell>

  <SnackbarComponent ref="snackbarRef" />
</template>

<route lang="yaml">
meta:
  layout: blank
  action: read
  subject: Auth
  redirectIfLoggedIn: true
</route>
