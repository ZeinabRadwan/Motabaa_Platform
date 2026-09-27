<script setup>
import SnackbarComponent from '@core/components/SnackbarCustom.vue'
import { useGenerateImageVariant } from '@core/composable/useGenerateImageVariant'
import authV2MaskDark from '@images/pages/misc-mask-dark.png'
import authV2MaskLight from '@images/pages/misc-mask-light.png'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { BUNDLED_LOGIN_SIDE_IMAGE } from '@/utils/branding-login'

const authThemeMask = useGenerateImageVariant(authV2MaskLight, authV2MaskDark)
</script>

<template>
<div>
  <VRow
    class="auth-wrapper bg-surface"
    no-gutters
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
          <h4 class="text-h4 mb-1">
            {{ $t('centers.Please click the activation link we sent to your email') }}
          </h4>
        </VCardText>

        <VCardText>
          <div class="pb-6" style="line-height: 2;">
            {{ $t("centers.An email has been sent to your email address containing an activation link") }}
            {{ $t("centers.Please click on the link to activate your account") }}
            <span style="color: #FF474c">
              {{ $t("centers.If you do not click the link your account will remain inactive and you will not be able to enter the platform") }}
            </span>
            {{ $t("centers.If you do not receive the email within a few minutes, please check your spam folder") }}
          </div>
          <RouterLink
            class="d-flex align-center justify-center"
            :to="{ name: 'login' }"
          >
            <VIcon
              icon="tabler-chevron-left"
              class="flip-in-rtl"
            />
            <span>{{ $t('Back to login') }}</span>
          </RouterLink>
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
  <SnackbarComponent ref="snackbarRef" />
</div>
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
