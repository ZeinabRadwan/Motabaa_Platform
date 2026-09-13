<script setup>
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
</script>

<template>
  <div class="motabaa-auth">
    <div class="motabaa-auth__card">
      <div class="motabaa-auth__logo">
        <VNodeRenderer :nodes="themeConfig.app.login_logo" />
      </div>
      <h1 class="motabaa-auth__title">
        {{ $t('centers.Please click the activation link we sent to your email') }}
      </h1>
      <p class="motabaa-auth__subtitle">
        {{ $t("centers.An email has been sent to your email address containing an activation link") }}
        {{ $t("centers.Please click on the link to activate your account") }}
      </p>
      <p class="motabaa-auth__subtitle">
        <span class="text-error">
          {{ $t("centers.If you do not click the link your account will remain inactive and you will not be able to enter the platform") }}
        </span>
      </p>
      <p class="motabaa-auth__subtitle">
        {{ $t("centers.If you do not receive the email within a few minutes, please check your spam folder") }}
      </p>
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
