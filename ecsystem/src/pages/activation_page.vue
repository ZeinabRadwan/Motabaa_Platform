<script setup>
import AuthPageShell from '@/components/auth/AuthPageShell.vue'
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()
const fieldDir = computed(() => (locale.value === 'ar' ? 'rtl' : 'ltr'))
</script>

<template>
  <AuthPageShell
    :dir="fieldDir"
    mobile-card-offset
  >
    <header class="auth-page__intro">
      <h1 class="auth-page__title">
        {{ $t('centers.Please click the activation link we sent to your email') }}
      </h1>
    </header>

    <div class="auth-page__body-text">
      <p>
        {{ $t("centers.An email has been sent to your email address containing an activation link") }}
        {{ $t("centers.Please click on the link to activate your account") }}
      </p>
      <p class="auth-page__body-text-warn">
        {{ $t("centers.If you do not click the link your account will remain inactive and you will not be able to enter the platform") }}
      </p>
      <p>
        {{ $t("centers.If you do not receive the email within a few minutes, please check your spam folder") }}
      </p>
    </div>

    <RouterLink
      class="auth-page__back"
      :to="{ name: 'login' }"
    >
      <VIcon
        icon="tabler-chevron-left"
        class="flip-in-rtl"
        size="18"
      />
      <span>{{ $t('Back to login') }}</span>
    </RouterLink>

    <p class="auth-page__footer">
      {{ $t('Auth platform footer') }}
    </p>
  </AuthPageShell>
</template>

<style lang="scss" scoped>
.auth-page__body-text {
  color: #64748b;
  font-size: 0.9375rem;
  line-height: 1.75;

  p {
    margin: 0 0 1rem;
  }

}

.auth-page__body-text-warn {
  color: #dc2626;
}
</style>

<route lang="yaml">
meta:
  layout: blank
  action: read
  subject: Auth
  redirectIfLoggedIn: true
</route>
