<script setup>
  import UserAdd from '@/views/user/UserAdd.vue';
</script>

<template>
  <div>
    <UserAdd></UserAdd>
  </div>

</template>
<route lang="yaml">
  meta:
    action: edit_users
    subject: edit_users
</route>