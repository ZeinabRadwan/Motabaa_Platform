<script setup>
import { useRoute, useRouter } from 'vue-router';

const route = useRoute()
const router = useRouter()
const userData = JSON.parse(localStorage.getItem('userData') || '{}')
</script>

<template>
  <VRow>
    <VCol cols="12" md="12"><VCard>
        <!-- SECTION Header -->
        <VCardText>
          <div style="text-align: center;">
              <h4 v-if="userData.centers && userData.centers.length > 0" class="font-weight-bold text-capitalize text-h4" color="primary">
                <span v-if="userData.centers[0].status == 0">
                  {{ $t("centers.Your center is not active yet") }}
                </span>
                <span v-else-if="userData.centers[0].status == 1 && !userData.centers[0].is_paid">
                  {{ $t("centers.You did not pay your subscription") }}
                  <VBtn
                    @click="()=> router.push('/centers/payments/put/'+userData.centers[0].id+'/'+userData.centers[0].package_id)"
                    color="secondary"
                  >
                    {{ $t('centers.pay_subscription') }}
                  </VBtn>
                </span>
              </h4>
              <h4 v-else class="font-weight-bold text-capitalize text-h4" color="primary">
                {{ $t("centers.You do not belong to any center") }}
              </h4>
          </div>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>  
</template>
<route lang="yaml">
  meta:
    action: all-users
    subject: Auth
</route>