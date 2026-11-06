<script setup>
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  action: { type: String, default: 'delete' },
  message: { type: String, default: '' },
  confirmLabel: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'confirm'])

const isRestore = computed(() => props.action === 'restore')
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="440"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard>
      <VCardText class="pt-6">
        {{ message || $t(isRestore ? 'hr.common.confirm_restore' : 'hr.common.confirm_delete') }}
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Cancel') }}
        </VBtn>
        <VBtn
          :color="isRestore ? 'primary' : 'error'"
          @click="emit('confirm')"
        >
          {{ confirmLabel || $t(isRestore ? 'restore' : 'delete') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
