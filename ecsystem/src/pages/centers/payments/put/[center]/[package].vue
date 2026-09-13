<script setup>
import { centersApi } from "@/plugins/apis/centersRequest";
import i18n from '@/plugins/i18n/index.js';
import { useUserListStore } from '@/views/apps/user/useUserListStore';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import {
  betweenValidator,
  emailValidator,
  integerValidator,
  minimumValidator,
  requiredValidator
} from '@validators';
import { useRoute, useRouter } from 'vue-router';
import { useSessionStore } from '@/stores/useSessionStore';

const centerListStore = centersApi()
const userListStore = useUserListStore()
const route = useRoute()
const router = useRouter()
const sessionStore = useSessionStore()
const refVForm = ref()
const packages = ref([])
const center = ref()
const name = ref('')
const email = ref('')
const phoneNumber = ref('')
const selectedPackage = ref('')
const currentPackage = ref('')
const centerPackage = ref('')
const nCases = ref('')
const paymentType = ref('')
const paymentDuration = ref('')
const snackbarRef = ref(null);
const userData = JSON.parse(localStorage.getItem('userData') || 'null')
const userCenter = localStorage.getItem('center')

const fetchPackage = (selected_package) => {
  centerListStore.calculatCenterPayment(Number(route.params.center), {
    selected_package: selected_package, 
    number_of_cases: nCases?.value, 
    payment_type: paymentType?.value, 
    payment_duration: paymentDuration?.value
  })
  .then(response => {
    selectedPackage.value = response.data.data;
    currentPackage.value = selectedPackage.value.current_package;
    centerPackage.value = Number(selectedPackage.value.id);
    nCases.value = selectedPackage.value.number_of_cases;
    paymentType.value = Number(selectedPackage.value.payment_type);
    paymentDuration.value = selectedPackage.value.payment_duration;
  })
}

centerListStore.fetchPackages().then(response => {
  packages.value = response.data.data
})

if(userData) {
  name.value = userData.name;
  email.value = userData.email;
  phoneNumber.value = userData.phone;
}

const paymentTypes = () => {
  let items = [
    {
      title: 'centers.yearly',
      value: 1,
    },
    {
      title: 'centers.monthly',
      value: 2,
    },
  ]
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))

  return translatedItems;
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){

      const formData = new FormData();
      formData.append('center_id', Number(route.params.center));
      formData.append('name', name.value);
      formData.append('email', email.value);
      formData.append('phone_number', phoneNumber.value);
      formData.append('package_id', centerPackage.value);
      formData.append('number_of_cases', nCases.value);
      formData.append('payment_type', paymentType.value);
      formData.append('payment_duration', paymentDuration.value);
      formData.append('amount', selectedPackage.value.amount);
      
      centerListStore.putPayment(formData).then(response => {
        if(response.data['status']) {
          let message = 'centers.paid_successfully'
          if(selectedPackage.value.is_changed)
            message = 'centers.package_changed_successfully'

          if(userData.centers.find(item => item.id == userCenter).is_paid != response.data['center'].is_paid)
            userData.centers.find(item => item.id == userCenter).is_paid = response.data['center'].is_paid

          if(userData.centers.find(item => item.id == userCenter).package_id != response.data['center'].package_id)
            userData.centers.find(item => item.id == userCenter).package_id = response.data['center'].package_id

          sessionStore.patchUserData(userData)
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t(message), 'success');
          setTimeout(() => {router.push('/centers/view/'+Number(route.params.center))}, window.timeOutAfterSubmit);
        }
      }).catch((e=>{
      }))
    }
  })
}

watch(centerPackage, query => {
  query && fetchPackage(Number(query))
})

watch(paymentType, query => {
  fetchPackage(centerPackage.value)
})

watchEffect(fetchPackage(Number(route.params.package)))
</script>

<template>
  <div v-if="selectedPackage.amount_for_every_case>0">
    <VForm 
      ref="refVForm"
      @submit.prevent="onSubmit"
    >
      <VRow>

        <VCol cols="6">
          <VCard class="padding-30p">
            <VCardTitle class="text-lg">
              {{  $t('centers.current_package')  }}
              
            </VCardTitle>
            <VCardText>
              <!-- 👉 User Details list -->
              <VList class="card-list mt-2">
                <VListItem>
                  <h6 class="text-h6">
                    <span style="font-size: 13px;">
                      <span style="color: #7374d3;">{{$t('centers.package')}}</span> : 
                      {{ currentPackage.title }}
                    </span>
                  </h6>
                </VListItem>
                <VListItem>
                  <h6 class="text-h6">
                    <span style="font-size: 13px;">
                      <span style="color: #7374d3;">{{$t('centers.duration')}}</span> : 
                      {{ (currentPackage.date && currentPackage.expiry_date) ? $t('from')+' '+currentPackage.date+' '+$t('to')+' '+currentPackage.expiry_date : $t('centers.subscription_expired') }}
                    </span>
                  </h6>
                </VListItem>
                <VListItem>
                  <h6 class="text-h6">
                    <span style="font-size: 13px;">
                      <span style="color: #7374d3;">{{$t('centers.number_of_cases')}}</span> : 
                      {{ currentPackage.number_of_cases }}
                    </span>
                  </h6>
                </VListItem>
                <VListItem>
                  <h6 class="text-h6">
                    <span style="font-size: 13px;">
                      <span style="color: #7374d3;">{{$t('centers.payment_type')}} ({{$t('centers.payment_duration')}})</span> : 
                      {{ currentPackage.payment_type_name }} ({{ currentPackage.payment_duration }})
                    </span>
                  </h6>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
          <br/>
          <VCard class="padding-30p" :title="$t('centers.complete_your_information')">    
            <VCard class="border-1p">
              <VCardText>
                <VRow>

                  <VCol cols="12" md="12">
                    <AppTextField
                      v-model="name"
                      labelClasses="red-asterisk-label"
                      :label="$t('Name')"
                      :rules="[requiredValidator]"
                    />
                  </VCol>

                  <VCol cols="12" md="12">
                    <AppTextField
                      v-model="email"
                      labelClasses="red-asterisk-label"
                      :label="$t('email')"
                      :rules="[requiredValidator, emailValidator]"
                    />
                  </VCol>

                  <VCol cols="12" md="12">
                    <AppTextField
                      v-model="phoneNumber"
                      labelClasses="red-asterisk-label"
                      :label="$t('phone')"
                      :rules="[requiredValidator, integerValidator, betweenValidator(phoneNumber, 12, 12)]"
                    />
                  </VCol>

                </VRow>
              </VCardText>
            </VCard>
          </VCard>
        </VCol>

        <VCol cols="6">
          <VCard :title="selectedPackage.title" class="pb-5">
            <VCardText>
              <VRow>

                <VCol cols="12" md="12">
                  <span class="pb-5" style="font-size: 13px;">{{ $t('centers.there wil be extra fees for every case') }}</span>
                </VCol>

                <VCol 
                  cols="12"
                  md="12"
                >
                  <AppSelect
                    v-model="centerPackage"
                    labelClasses="red-asterisk-label"
                    :items="packages"
                    :item-title="'title'"
                    :item-value="'id'"
                    :label="$t('centers.subscription_type')"
                    :rules="[requiredValidator]"
                    clearable
                    clear-icon="tabler-x"
                  />
                </VCol>

                <VCol 
                  cols="12"
                  md="12"
                  :class="selectedPackage.is_changed ? 'pb-10' : ''"
                >
                  <AppTextField
                    v-model="nCases"
                    type="number"
                    labelClasses="red-asterisk-label"
                    :label="$t('centers.number_of_cases') + ' (' + $t('centers.minimum_number_is_50') + ')'"
                    :rules="[requiredValidator, minimumValidator(nCases, 50)]"
                    @update:model-value="fetchPackage(centerPackage)"
                  />
                </VCol>

                <VCol 
                  v-if="!selectedPackage.is_changed && currentPackage.expiry_date"
                  cols="12"
                  md="12"
                >
                  {{  $t('centers.The payment method will activate after the end of the current period') }}
                </VCol>

                <VCol 
                  v-if="!selectedPackage.is_changed"
                  cols="12"
                  md="12"
                >
                  <AppSelect
                    v-model="paymentType"
                    labelClasses="red-asterisk-label"
                    :items="paymentTypes()"
                    :label="$t('centers.payment_type')"
                    :rules="[requiredValidator]"
                  />
                </VCol>

                <VCol 
                  v-if="!selectedPackage.is_changed"
                  cols="12"
                  md="12"
                  class="pb-10"
                >
                  <AppTextField
                    v-model="paymentDuration"
                    type="number"
                    labelClasses="red-asterisk-label"
                    :label="$t('centers.payment_duration')"
                    :rules="[requiredValidator, minimumValidator(paymentDuration, 1)]"
                    @update:model-value="fetchPackage(centerPackage)"
                  />
                </VCol>

                <VCol cols="12" md="8" class="pb-6">
                  <span class="pb-5" style="font-size: 13px;">{{ $t('centers.for every case') }}</span>
                </VCol>

                <VCol cols="12" md="4" class="pb-6" style="text-align: left;">
                  <span class="pb-5" style="font-size: 13px;">
                    {{  selectedPackage.amount_for_every_case ? '$'+selectedPackage.amount_for_every_case : '$0' }}
                  </span>
                </VCol>

                <VDivider />

                <VCol cols="12" md="8" class="pb-6">
                  <span style="font-size: 15px;">{{ $t('centers.total') }}</span>
                  <span style="font-size: 12px;"> {{ ' ('+$t('centers.original_total')+')' }}</span>
                </VCol>

                <VCol cols="12" md="4" class="pb-11" style="text-align: left;">
                  <span style="font-size: 11px;">{{ (selectedPackage.original_amount>0 && Number(selectedPackage.original_amount) != Number(selectedPackage.amount)) ? ' ($'+selectedPackage.original_amount+') ' : '' }}</span>
                  <span style="font-size: 13px;">{{  selectedPackage.amount>0 ? '$'+selectedPackage.amount : '$0' }}</span>
                </VCol>

                <VCol cols="12" md="12" class="pb-4" style="text-align: center;">
                  <VBtn
                    type="submit"
                  >
                    {{ $t('centers.confirm_subscription') }}
                  </VBtn>
                </VCol>

              </VRow>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VForm>
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>
<route lang="yaml">
  meta:
    action: edit_centers
    subject: edit_centers
</route>