<script setup>
import { BUNDLED_LOGIN_LOGO } from '@/utils/branding'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const attemptedPath = computed(() => route.fullPath)

const goBack = () => {
  if (window.history.length > 1)
    router.back()
  else
    router.push('/')
}
</script>

<template>
  <main
    class="misc-not-found"
    role="main"
  >
    <article class="misc-not-found__card">
      <img
        :src="BUNDLED_LOGIN_LOGO"
        class="misc-not-found__logo"
        alt=""
      >

      <p
        class="misc-not-found__code"
        aria-hidden="true"
      >
        404
      </p>

      <span
        class="misc-not-found__icon-wrap"
        aria-hidden="true"
      >
        <VIcon
          icon="tabler-map-search"
          size="28"
        />
      </span>

      <h1 class="misc-not-found__title">
        {{ t('misc_errors.not_found_title') }}
      </h1>
      <p class="misc-not-found__description">
        {{ t('misc_errors.not_found_description') }}
      </p>

      <div class="misc-not-found__actions">
        <VBtn
          to="/"
          class="general-page-header__btn-primary"
          prepend-icon="tabler-home"
        >
          {{ t('misc_errors.back_home') }}
        </VBtn>
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-arrow-right"
          @click="goBack"
        >
          {{ t('misc_errors.go_back') }}
        </VBtn>
      </div>

      <p
        v-if="attemptedPath && attemptedPath !== '/'"
        class="misc-not-found__path"
      >
        {{ attemptedPath }}
      </p>
    </article>
  </main>
</template>

<style lang="scss">
@import '@/styles/misc-not-found-page.scss';
</style>
