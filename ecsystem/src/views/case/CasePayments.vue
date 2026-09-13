<script setup>
import { useRoute, useRouter } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { feesApi } from "@/plugins/apis/feesRequest";
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { can } from '@layouts/plugins/casl'
import { casesApi } from "@/plugins/apis/casesReqest";
import { termsApi } from "@/plugins/apis/termsRequest";
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import {
requiredValidator
} from '@validators';
import { search } from "@core/utils/helper";
import {
  departmentsItems
} from '@core/utils/generalItems';
import CasePayments from '@/pages/payments/list/index.vue'

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const feeListStore = feesApi()
const caseListStore = casesApi()
const termListStore = termsApi()
const props = defineProps({
  caseData: {
    type: Object,
    required: true,
  },
})
const searchQuery = ref('')
const selectedTerm = ref()
const selectedCase = ref()
const totalPage = ref(1)
const totalFees = ref(0)
const fees = ref([])
const terms = ref([])
const snackbarRef = ref(null);
const showPayments = ref(false);
const paymentsTerm = ref();
const feeData = ref();
const loadingFees = ref(false)

const loading = ref({
  cases: false,
  put_cases: false,
})

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

watch([selectedTerm], () => {
  options.value.page = 1
});

watch(selectedTerm, query => {
  showPayments.value = false
  paymentsTerm.value = null
  feeData.value = null
  fetchFees()
})

// 👉 Fetch Terms
termListStore.items().then(response => {
  terms.value = response.data.data
}).catch(() => {
})

// 👉 Fetch Terms
termListStore.currentTerm().then(response => {
  if(response.data.data) {
    selectedTerm.value = response.data.data
  }
}).catch(() => {
})

// 👉 Fetching Fees
const fetchFees = () => {
  feeListStore.fetchFees({
    q: searchQuery.value,
    term_id: selectedTerm.value,
    case_id: props.caseData.id,
    options: options.value,
    page: options.value.page,
  }).then(response => {
    fees.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalFees.value = response.data.total
    options.value.page = response.data.currentPage
  }).catch(error => {
    console.error(error)
  })
}

const printFees = (pdfType) => {
  loadingFees.value = true;
  feeListStore.fetchFees({
    q: searchQuery.value,
    pdf: pdfType,
    term_id: selectedTerm.value,
    case_id: props.caseData.id,
    options: options.value,
    page: options.value.page,
  }).then(response => {
    loadingFees.value = false;
    window.open(response.data.data.url, '_blank');
  }).catch(error => {
    loadingFees.value = false;
    console.error(error)
  })
}

const resolveFeeStatusVariant = stat => {
  const statLowerCase = stat.toLowerCase()
  if (statLowerCase === 'pending')
    return 'warning'
  if (statLowerCase === 'active')
    return 'success'
  if (statLowerCase === 'inactive')
    return 'secondary'
  
  return 'primary'
}

const resolvePaidPaymentStatusVariant = stat => {
  if (stat === 'Unpaid')
    return 'error'
  if (stat === 'Partially Paid')
    return 'primary'
  if (stat === 'Paid')
    return 'success'
  
  return 'secondary'
}

const showPaymentsAction = (fee, term) =>{

  showPayments.value = true;
  paymentsTerm.value = terms.value.find(item => item.id === term);
  feeData.value = fee;
}
</script>

<template>
  <section>
    <VRow>
      <VCol cols="12">
        <VCard>
          <!-- 👉 Filters -->
          <VCardText>
            <VRow>
              <!-- 👉 Select Term -->
              <VCol
                cols="12"
                sm="8"
              >
                <AppSelect
                  v-model="selectedTerm"
                  :items="terms"
                  :item-title="item => item.title"
                  :item-value="item => item.id"
                  class="pa-1"
                >
                </AppSelect>
              </VCol>
              <VCol
                cols="12"
                sm="4"
                class="text-left"
                >
                <VBtn
                  v-if="can('edit_study-fees', 'edit_study-fees')"
                  color="success" 
                  @click="printFees('case_fees')" 
                  :loading="loading.loadingFees"
                  class="ma-1"
                >
                  {{ $t('payments.Print Fees') }}
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>
          <!-- SECTION -->
        </VCard>
      </VCol>
    </VRow>

    <VRow>
      <VCol>
        <VCard>
          <!-- SECTION datatable -->
          <VTable class="dataTable-hidescroller-y">
            <tbody>
              <tr
                v-for="item in fees"
                :key="item.id"
              >
                <td>
                  <div class="gap-3 mt-3 mb-3">
                    <span style="font-size: 15px;"> <span style="color: #7374d3;">{{ $t('payments.Amount') }}:</span>{{ item.amount }}</span><br>
                    <span style="font-size: 15px;"> <span style="color: #7374d3;">{{ $t('payments.Paid Payment') }}:</span>{{ item.paid_amount }}</span><br>
                    <span style="font-size: 15px;"> <span style="color: #7374d3;">{{ $t('Created By') }}:</span> {{ item.created_by_name }}</span><br>
                    <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('services') }}:</span><span v-for="feeService in item.services"> {{ feeService.name }}, </span></span>
                  </div>
                </td>

                <td>
                  <div class="d-flex gap-3 align-center mb-1">
                    <VChip
                      :color="resolveFeeStatusVariant(item.deleted_at ? 'inactive' : 'active' )"
                      size="small"
                      label
                      class="text-capitalize"
                    >
                      {{ item.deleted_at ? $t('Inactive') : $t('Active_user') }}
                    </VChip>
                    <VChip
                      :color="resolvePaidPaymentStatusVariant(item.status)"
                      size="small"
                      label
                      class="text-capitalize"
                    >
                      {{ item.status ? $t('payments.'+item.status) : '' }}
                    </VChip>
                  </div>
                </td>

                <td>
                  <IconBtn  @click="showPaymentsAction(item, item.term_id)">
                    <VIcon icon="tabler-eye" />
                  </IconBtn>
                </td>
              </tr>
            </tbody>
          </VTable>
          <!-- SECTION -->
        </VCard>
      </VCol>
    </VRow>

    <VRow v-if="showPayments">
      <VCol>
        <VCard>
          <CasePayments :case-data="caseData" :fee-data="feeData" :term-data="paymentsTerm" />
        </VCard>
      </VCol>
    </VRow>
    <SnackbarComponent ref="snackbarRef" />
  </section>
</template>

<style lang="scss">
  .name-route:not(:hover) {
    color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
  }
  .text-capitalize {
    text-transform: capitalize;
  }
</style>

<route lang="yaml">
  meta:
    action: access_study-fees
    subject: access_study-fees
</route>