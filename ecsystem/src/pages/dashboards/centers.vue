<script setup>
import { useRouter } from 'vue-router'
import { can } from '@layouts/plugins/casl'
import { useSessionStore } from '@/stores/useSessionStore'

const router = useRouter()
const sessionStore = useSessionStore()
const userData = computed(() => sessionStore.userData || {})

const currentCenter = computed(() => {
  const centers = userData.value.centers || []

  return centers.find(item => item.id == sessionStore.center) || centers[0] || null
})

const canPaySubscription = computed(() => can('edit_centers', 'edit_centers') && !!currentCenter.value?.package_id)
</script>

<template>
  <VRow>
    <VCol
      cols="12"
      md="12"
    >
      <VCard>
        <!-- SECTION Header -->
        <VCardText>
          <div style="text-align: center;">
            <h4
              v-if="currentCenter"
              class="font-weight-bold text-capitalize text-h4"
              color="primary"
            >
              <span v-if="currentCenter.status == 0">
                {{ $t("centers.Your center is not active yet") }}
              </span>
              <span v-else-if="currentCenter.status == 1 && !currentCenter.is_paid">
                {{ $t("centers.You did not pay your subscription") }}
                <VBtn
                  v-if="canPaySubscription"
                  color="secondary"
                  @click="()=> router.push('/centers/payments/put/'+currentCenter.id+'/'+currentCenter.package_id)"
                >
                  {{ $t('centers.pay_subscription') }}
                </VBtn>
              </span>
            </h4>
            <h4
              v-else
              class="font-weight-bold text-capitalize text-h4"
              color="primary"
            >
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
