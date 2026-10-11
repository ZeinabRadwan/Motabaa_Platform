<script setup>
import UserLeaves from '@/views/user/UserLeaves.vue';
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

<template>
  <section>
    <VRow>
      <VCol cols="12">
        <UserLeaves
          :initial-status="route.query.status"
          :current-only="route.query.current == '1'"
        />
      </VCol>
    </VRow>
  </section>
</template>

<route lang="yaml">
  meta:
    action: access_users
    subject: access_users
</route>
