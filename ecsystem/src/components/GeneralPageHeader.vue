<script setup>
import { useGeneralPageHeader } from '@/composables/useGeneralPageHeader'

const { visible, pageTitle, pageSubtitle } = useGeneralPageHeader()
</script>

<template>
  <header
    v-if="visible"
    class="general-page-header"
  >
    <div class="general-page-header__row">
      <div class="general-page-header__text">
        <h1 class="general-page-header__title">
          {{ pageTitle }}
        </h1>
        <p
          v-if="pageSubtitle"
          class="general-page-header__subtitle"
        >
          {{ pageSubtitle }}
        </p>
      </div>
      <div
        id="general-page-header-actions"
        class="general-page-header__actions"
      />
    </div>
  </header>
</template>

<style lang="scss">
@import '@/styles/general-page-header.scss';
</style>
