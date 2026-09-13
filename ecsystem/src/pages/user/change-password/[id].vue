<script setup>
  import UserChangePassword from '@/views/user/UserChangePassword.vue';
</script>

<template>
  <div>
    <UserChangePassword></UserChangePassword>
  </div>

</template>
<route lang="yaml">
  meta:
    action: admin_users
    subject: admin_users
</route>