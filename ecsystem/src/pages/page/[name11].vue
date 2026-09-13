<script setup>
import { useRoute, useRouter } from 'vue-router';

const route = useRoute()
const router = useRouter()
const refVForm = ref()


console.log();


</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <VForm 
              ref="refVForm"
            >
        <VCard class="padding-30p">    
        <VCard class="border-1p">
        <template v-slot:title>
          <VRow>
            <span class="v-col v-col-6 d-flex gap-4"> {{ $t(route.params.name11) }}</span>

          </VRow>

        </template>
        <v-card-text>
            <br><br><br><br><br><br><br><br><br><br><br><br><br><br>
        </v-card-text>

        </VCard>
        </VCard>
      </VForm>
      </VCol>
    </VRow>
  </div>
</template>
<route lang="yaml">
  meta:
    action: all-users
    subject: Auth
</route>