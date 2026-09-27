<script setup>
import { BUNDLED_LOGIN_LOGO } from '@/utils/branding'
import { BUNDLED_LOGIN_MOBILE_IMAGE, BUNDLED_LOGIN_SIDE_IMAGE } from '@/utils/branding-login'

const props = defineProps({
  dir: {
    type: String,
    default: 'rtl',
  },
  showLogo: {
    type: Boolean,
    default: true,
  },
  lockDesktopViewport: {
    type: Boolean,
    default: false,
  },
  mobileCardOffset: {
    type: Boolean,
    default: false,
  },
})

const DESKTOP_LOGIN_MQ = '(min-width: 960px)'

function preloadAuthBackgrounds() {
  const desktop = window.matchMedia('(min-width: 1280px)').matches
  const href = desktop ? BUNDLED_LOGIN_SIDE_IMAGE : BUNDLED_LOGIN_MOBILE_IMAGE
  const existing = document.querySelector(`link[data-auth-bg-preload="${href}"]`)

  if (existing)
    return

  const link = document.createElement('link')

  link.rel = 'preload'
  link.as = 'image'
  link.href = href
  link.dataset.authBgPreload = href
  document.head.appendChild(link)
}

function lockLoginViewport() {
  if (!props.lockDesktopViewport || !window.matchMedia(DESKTOP_LOGIN_MQ).matches)
    return

  document.documentElement.classList.add('login-page-viewport-lock')
  document.body.classList.add('login-page-viewport-lock')
}

function unlockLoginViewport() {
  document.documentElement.classList.remove('login-page-viewport-lock')
  document.body.classList.remove('login-page-viewport-lock')
}

onMounted(() => {
  preloadAuthBackgrounds()
  lockLoginViewport()
})

onUnmounted(unlockLoginViewport)
</script>

<template>
  <div
    class="auth-page"
    :class="{ 'auth-page--lock-desktop': lockDesktopViewport }"
  >
    <div
      class="auth-page__background auth-page__background--desktop"
      :style="{ backgroundImage: `url(${BUNDLED_LOGIN_SIDE_IMAGE})` }"
      aria-hidden="true"
    />
    <div
      class="auth-page__background auth-page__background--mobile"
      :style="{ backgroundImage: `url(${BUNDLED_LOGIN_MOBILE_IMAGE})` }"
      aria-hidden="true"
    />

    <div class="auth-page__shell">
      <div
        class="auth-page__card"
        :class="{ 'auth-page__card--offset-mobile': mobileCardOffset }"
        :dir="dir"
      >
        <img
          v-if="showLogo"
          :src="BUNDLED_LOGIN_LOGO"
          alt="Athar"
          class="auth-page__logo"
        >

        <slot />
      </div>
    </div>
  </div>
</template>

<style lang="scss">
@use '@/styles/auth-page.scss';

@media (min-width: 960px) {
  html.login-page-viewport-lock,
  body.login-page-viewport-lock {
    overflow: hidden !important;
    block-size: 100%;
    max-block-size: 100dvh;
    inline-size: 100%;
    max-inline-size: 100%;
  }

  body.login-page-viewport-lock .layout-wrapper.layout-blank {
    block-size: 100dvh;
    max-block-size: 100dvh;
    overflow: hidden;
  }

  body.login-page-viewport-lock .v-application {
    block-size: 100dvh;
    max-block-size: 100dvh;
    overflow: hidden;
  }
}
</style>
