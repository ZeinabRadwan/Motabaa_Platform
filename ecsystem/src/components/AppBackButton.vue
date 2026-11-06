<script setup>
import { useBackNavigation } from '@/composables/useBackNavigation'

const { visible, goBack } = useBackNavigation()
const { t, locale } = useI18n()

const isRtl = computed(() => String(locale.value || '').startsWith('ar'))
</script>

<template>
  <VBtn
    v-if="visible"
    variant="text"
    color="primary"
    class="app-back-button"
    :class="{ 'app-back-button--rtl': isRtl }"
    prepend-icon="tabler-arrow-left"
    @click="goBack"
  >
    {{ t('navigation.back') }}
  </VBtn>
</template>

<style lang="scss" scoped>
.app-back-button {
  align-self: flex-start;
  margin: 0 0 0.35rem;
  padding-inline: 0.35rem !important;
  min-block-size: 2.25rem;
  font-size: 0.875rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  text-transform: none;
}

.app-back-button--rtl :deep(.v-btn__prepend .v-icon) {
  transform: scaleX(-1);
}
</style>
