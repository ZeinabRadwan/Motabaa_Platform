<script setup>
defineProps({
  title: {
    type: String,
    default: '',
  },
  subtitle: {
    type: String,
    default: '',
  },
  titleI18nKey: {
    type: String,
    default: '',
  },
  subtitleI18nKey: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <div class="parent-page">
    <header
      v-if="title || titleI18nKey || subtitle || subtitleI18nKey"
      class="parent-page__header"
    >
      <h1
        v-if="title || titleI18nKey"
        class="parent-page__title"
      >
        <slot name="title">
          {{ titleI18nKey ? $t(titleI18nKey) : title }}
        </slot>
      </h1>
      <p
        v-if="subtitle || subtitleI18nKey"
        class="parent-page__subtitle"
      >
        <slot name="subtitle">
          {{ subtitleI18nKey ? $t(subtitleI18nKey) : subtitle }}
        </slot>
      </p>
    </header>

    <slot name="beneficiary" />

    <div class="parent-page__body">
      <slot />
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import '@styles/parent-experience.scss';
</style>
