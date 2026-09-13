<template>
  <div class="h-100 d-flex align-center justify-space-between">
    <!-- 👉 Footer: left content -->
    <span class="d-flex align-center">
      {{ $t('copyright', { year: new Date().getFullYear()  })}}
    </span>
  </div>
</template>
