<script setup>

const { snackbarMessage, snackbarColor, snackbarTimeout } = defineProps(['snackbarMessage', 'snackbarColor', 'snackbarTimeout']);

const isVisible = ref(false)
let message = snackbarMessage ?? 'Hello'
let color = snackbarColor ?? 'error'
let timeout = snackbarTimeout ?? 2000


const visibleSnackbar = (newMessage = message, newColor = color, newTimeout = timeout) => {
  isVisible.value = true;
  message = newMessage;
  timeout = newTimeout;
  color = newColor;

};

defineExpose({
  exposevisibleSnackbar: visibleSnackbar,
});

</script>

<template>
  <VSnackbar v-model="isVisible" multi-line :timeout="timeout" :color="color">
    <div v-html="message"></div>
  </VSnackbar>
</template>

<style lang="scss">

</style>
