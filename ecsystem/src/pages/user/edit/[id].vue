<script setup>
  import UserEdit from '@/views/user/UserEdit.vue';
</script>

<template>
  <div>
    <UserEdit></UserEdit>
  </div>

</template>
<route lang="yaml">
  meta:
    action: edit_users
    subject: edit_users
</route>