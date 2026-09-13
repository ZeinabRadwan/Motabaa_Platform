<script setup>
  import UserEdit from '@/views/user/UserEdit.vue';
</script>

<template>
  <div>
    <UserEdit :parent="true"></UserEdit>
  </div>

</template>
<route lang="yaml">
  meta:
    action: edit_parents
    subject: edit_parents
</route>