<script setup>
import GoalPut from '@/views/plans/goal/put/GoalPut.vue';
import { useRoute, useRouter } from 'vue-router';
const route = useRoute()
const router = useRouter()
</script>

<template>
  <div>
    <GoalPut/>
  </div>

</template>
<route lang="yaml">
  meta:
    action: edit_operation-plans
    subject: edit_operation-plans
</route>