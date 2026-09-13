<script setup>
import { centersApi } from "@/plugins/apis/centersRequest";
import { useRoute, useRouter } from 'vue-router';

const route = useRoute()
const router = useRouter()
const centerListStore = centersApi()
const packages = ref([])
const center = ref()

centerListStore.fetchCenter(Number(route.params.center)).then(response => {
  center.value = response.data.data
})

centerListStore.fetchPackages().then(response => {
  packages.value = response.data.data
})
</script>

<template>
  <div v-if="center">
    <div class="pb-5" style="font-size: 25px; text-align: center">عرض الباقات</div>
    <VRow>
      <VCol v-for="(centerPackage, key) in packages" cols="3">
        <VCard :color="(centerPackage.id == center.package_id) ? 'primary' : ''" class="d-flex flex-column padding-30p" style="height: 100%;">
          <VCardText>
            <VRow>
              <VCol cols="12">
                <div style="font-size: 20px;">{{ centerPackage.title }}</div><br/>
                <div v-if="centerPackage.amount>0" style="font-size: 14px;">{{ centerPackage.yearly_amount }} سنويًا / {{ centerPackage.monthly_amount }} شهريًا</div>
                <div v-else style="font-size: 15px;">مجانا (فترة التجربة اسبوعين)</div><br/>
                <div v-for="data in centerPackage.data" style="font-size: 11px; padding-bottom: 10%">
                  <VIcon icon="tabler-check"/>
                  &nbsp; 
                  {{ data }}
                  <br/>
                </div>
                <VDivider/>
              </VCol>
              <VCol cols="12" :style="'font-size: 13px;'">سيكون هناك رسوم اضافية مع كل تسجيل حالة</VCol>
            </VRow>
          </VCardText>
          <VCardActions>
            <VRow>
              <VCol class="text-center">
                <VBtn
                  v-if="centerPackage.amount>0"
                  class="text-center"
                  variant="flat"
                  :to="{ name: 'centers-payments-put-center-package', params: { center: route.params.center, package: centerPackage.id } }"
                  color="secondary"
                >
                  &nbsp;&nbsp; {{ centerPackage.id == center.package_id ? $t('centers.pay_subscription') : $t('centers.change_package') }} &nbsp;&nbsp;
                </VBtn>
              </VCol>
            </VRow>
          </VCardActions>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>
<route lang="yaml">
  meta:
    action: all-users
    subject: Auth
</route>