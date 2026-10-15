<script setup>
import { BUNDLED_LOGIN_LOGO } from '@/utils/branding'
import { BUNDLED_LOGIN_MOBILE_IMAGE, BUNDLED_LOGIN_SIDE_IMAGE } from '@/utils/branding-login'

defineProps({
  dir: {
    type: String,
    default: 'rtl',
  },
  showLogo: {
    type: Boolean,
    default: true,
  },
})

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

onMounted(() => {
  preloadAuthBackgrounds()
})
</script>

<template>
  <div class="auth-page">
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
        :dir="dir"
      >
        <div
          v-if="showLogo"
          class="auth-page__logo-wrap"
        >
          <img
            :src="BUNDLED_LOGIN_LOGO"
            alt="Athar"
            class="auth-page__logo"
            decoding="async"
          >
        </div>

        <slot />
      </div>
    </div>
  </div>
</template>

<style lang="scss">
@use '@/styles/auth-page.scss';
</style>
