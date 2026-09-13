<script setup>
  import UserAdd from '@/views/user/UserAdd.vue';
</script>

<template>
  <div>
    <UserAdd :parent="true"></UserAdd>
  </div>

</template>
<route lang="yaml">
  meta:
    action: edit_parents
    subject: edit_parents
</route>