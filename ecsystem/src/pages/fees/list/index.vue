<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
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
  serviceitems
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
const selectedBatch = ref()
const selectedCase = ref()
const selectedService = ref()
const selectedStatus = ref('active')
const selectedPaidStatus = ref('')
const searchCase = ref()
const totalPage = ref(1)
const totalFees = ref(0)
const fees = ref([])
const cases = ref([])
const terms = ref([])
const serviceItemsData = ref([])
const snackbarRef = ref(null);
const putFeeDialog = ref(false);
const putDialogTitle = ref('');
const addAnother = ref(true);
const searchPutCase = ref()
const putCases = ref([])
const putID = ref('')
const putCase = ref('')
const putCaseID = ref('')
const putCaseName = ref('')
const putTerm = ref('')
const putAmount = ref('')
const putServices = ref([])
const putNotes = ref('')
const isDialogVisible = ref(false)
const dialogFeeID = ref()
const dialogMessage = ref('')
const dialogButton = ref('')
const dialogAction = ref('')
const loadingFees = ref(false)
const putFileDialog = ref(false);
const fileLoading = ref(false);
const file = ref('')
const showPayments = ref(false);
const paymentsTerm = ref();
const isEditDialog = ref(false)

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

onMounted(() => {
  serviceitems().then(data => {
    serviceItemsData.value = data
  })
});


// 👉 Fetching Fees
const fetchFees = () => {
  feeListStore.fetchFees({
    q: searchQuery.value,
    term_id: selectedTerm.value,
    case_id: selectedCase.value,
    service_id: selectedService.value,
    status: selectedStatus.value,
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

// 👉 Fetch Cases
const searchCases = params => caseListStore.selectItems(params)
const searchPutCases = params => caseListStore.selectItems(params)

const printFees = (pdfType) => {
  
  loadingFees.value = true;
  feeListStore.fetchFees({
    q: searchQuery.value,
    pdf: pdfType,
    term_id: selectedTerm.value,
    case_id: selectedCase.value,
    service_id: selectedService.value,
    status: selectedStatus.value,
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

// 👉 Fetch Terms
termListStore.items().then(response => {
  terms.value = response.data.data
}).catch(() => {
})

// 👉 Headers
const translatedHeaders = () => {
  const headers = [
    {
      title: 'Name',
      key: 'case_name',
      width: '20%',
    },
    {
      title: 'payments.Term',
      key: 'term_name',
      width: '20%',
    },
    {
      title: 'Information',
      key: 'information',
      width: '20%',
    },
    {
      title: 'Status',
      key: 'active',
      width: '20%',
    },
    {
      title: 'Actions',
      key: 'actions',
      width: '20%',
      sortable: false,
    },
  ]

  let translatedHeaders = headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }))

  return translatedHeaders;
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

const statusItems = () => {
  let items = [
    {
      title: 'All',
      value: 'all',
    },
    {
      title: 'Active_user',
      value: 'active',
    },
    {
      title: 'Inactive',
      value: 'inactive',
    },
  ]
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))

  return translatedItems;
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

const paidStatusItems = () => {
  let items = [
    {
      title: 'payments.Unpaid',
      value: 'unpaid',
    },
    {
      title: 'payments.Paid',
      value: 'paid',
    },
    {
      title: 'payments.Partially Paid',
      value: 'partially_paid',
    },
  ]
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))

  return translatedItems;
}

const feeDialog = (fee) =>{

  window.scrollTo({
    top: 0,
  });
  putCases.value = []
  putCase.value = '';
  putCaseID.value = '';
  putCaseName.value = '';
  searchPutCase.value = '';
  putID.value = fee ? fee.id : 0;
  putTerm.value = '';
  putAmount.value = fee ? fee.amount : '';
  putServices.value = [];
  putNotes.value = fee ? fee.notes : '';
  addAnother.value = fee ? false : true;
  putDialogTitle.value = i18n.global.t('payments.Add Fee')
  isEditDialog.value = false
  if(fee){
    caseListStore.fetchCase(fee.case_id).then(response => {
      putCase.value = response.data.data.id
    }).catch(error => {
      console.error(error)
    })
    termListStore.fetchTerm(fee.term_id).then(response => {
      putTerm.value = response.data.data
    }).catch(error => {
      console.error(error)
    })
    fee.services.forEach(item => {
      putServices.value.push(item.id)
    });
    isEditDialog.value = true
    putDialogTitle.value = i18n.global.t('payments.Edit Fee')
    putCaseID.value = fee.case_id;
    putCaseName.value = fee.case_name;
  }
  putFeeDialog.value = true;
}

const putFee = (closeDialog = true) =>{
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){
      
      const formData = new FormData();
      formData.append('id', putID.value);
      if(isEditDialog.value) {
        formData.append('case_id', putCase.value?.id ?? putCase.value);
        formData.append('term_id', putTerm.value.id);
      }
      else {
        formData.append('case_id', putCase.value?.id ?? putCase.value);
        formData.append('term_id', putTerm.value);
      }
      formData.append('amount', putAmount.value);
      formData.append('services', putServices.value);
      formData.append('notes', putNotes.value);

      feeListStore.putFee(formData).then(response => {
        if(response.data['status']){

          putFeeDialog.value = !closeDialog;
          if(putFeeDialog.value) {
            putCase.value = '';
            putTerm.value = '';
            putAmount.value = '';
            putServices.value = [];
            putNotes.value = '';
            refVForm.value?.reset();
            refVForm.value?.resetValidation();
          }
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
          fetchFees()
        }

      }).catch((e=>{
        
        const { errors: formErrors } = e.response.data
        errors.value = formErrors
      }))
    }
  })
}

const fileDialog = (id) =>{

  dialogAction.value = 'pay'
  dialogFeeID.value = id;
  file.value = null;
  fileLoading.value = false;
  putFileDialog.value = true;
}

const actionDialog = (id, action) =>{

  dialogFeeID.value = id;
  if(action == 'delete') {

    dialogMessage.value = i18n.global.t('payments.Are you sure you want to delete this Fee?');
    dialogButton.value = i18n.global.t('delete');
  }
  else if(action == 'restore') {

    dialogMessage.value = i18n.global.t('payments.Are you sure you want to restore this Fee?');
    dialogButton.value = i18n.global.t('restore');
  }
  dialogAction.value = action;
  isDialogVisible.value = true;
}

const dialogFunction = () =>{

  if(dialogAction.value == 'delete') {
    feeListStore.deleteFee(dialogFeeID.value).then(() => {
      fetchFees()
    })
  }
  else if(dialogAction.value == 'restore') {
    feeListStore.restoreFee(dialogFeeID.value).then(() => {
      fetchFees()
    })
  }
  isDialogVisible.value = false;
}

const openNewTab = (url) => {
  window.open(url, '_blank');
}

const showPaymentsAction = (fee, term) =>{

  showPayments.value = true;
  paymentsTerm.value = terms.value.find(item => item.id === term);
  feeData.value = fee;
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchFees, {
  search: searchQuery,
  filters: () => [
    selectedTerm.value,
    selectedBatch.value,
    selectedCase.value,
    selectedService.value,
    selectedStatus.value,
  ],
  options,
})
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
                sm="4"
              >
                <AppSelect
                  v-model="selectedTerm"
                  :label="$t('payments.Term')"
                  :items="terms"
                  :item-title="item => item.title"
                  :item-value="item => item.id"
                  clear-icon="tabler-x"
                  clearable
                  class="pa-1"
                >
                </AppSelect>
              </VCol>

              <!-- 👉 Select Status -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppAutocomplete
                  v-model="selectedCase"
                  :server-search="searchCases"
                  :label="$t('payments.Case')"
                  :item-title="'name'"
                  :item-value="'id'"
                  :placeholder="$t('Type Case Name')"
                  clear-icon="tabler-x"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedService"
                  :label="$t('services')"
                  :items="serviceItemsData"
                  clear-icon="tabler-x"
                  clearable
                  class="pa-1"
                >
                </AppSelect>
              </VCol>

              <!-- 👉 Select Status -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedStatus"
                  :label="$t('Active Status')"
                  :items="statusItems()"
                  class="pa-1"
                >
                </AppSelect>
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VCardText class="d-flex flex-wrap py-4 gap-4">
            <div class="me-3 d-flex gap-3">
              <AppSelect
                :model-value="options.itemsPerPage"
                :items="[
                  { value: 10, title: '10' },
                  { value: 25, title: '25' },
                  { value: 50, title: '50' },
                  { value: 100, title: 'All' },
                ]"
                style="width: 6.25rem;"
                @update:model-value="options.itemsPerPage = parseInt($event, 10)"
              />
            </div>
            <VSpacer />

            <div class="app-fee-search-filter justify-end d-flex align-center flex-wrap gap-4">
              <!-- 👉 Search  -->
              <!-- <div style="inline-size: 10rem;">
                <AppTextField
                  v-model="searchQuery"
                  :placeholder="$t('Search')"
                  density="compact"
                />
              </div> -->
              
              <!-- 👉 Add fee button -->
              <VBtn
                v-if="can('edit_study-fees', 'edit_study-fees')"
                color="success" 
                @click="printFees('fees')" 
                :loading="loading.loadingFees"
              >
                {{ $t('payments.Print Fees') }}
              </VBtn>
              
              <VBtn
                v-if="can('edit_study-fees', 'edit_study-fees')"
                prepend-icon="tabler-plus"
                @click="feeDialog(null)"
              >
                {{ $t('payments.Add Fee') }}
              </VBtn>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="fees"
            :items-length="totalFees"
            :headers="translatedHeaders()"
            class="dataTable-hidescroller-y dataTable"
            @update:options="onTableOptions"
          >
            <!-- Case Name -->
            <template #item.case_name="{ item }">
              <RouterLink
                :to="{ name: 'cases-view-tab-id', params: { id: item.raw.case_id, tab: 'payments' } }"
                class="font-weight-medium name-route"
              >
                {{ item.raw.case_name }}
              </RouterLink>
            </template>

            <!-- Term -->
            <template #item.term_name="{ item }">
              <RouterLink
                :to="{ name: 'classes-view-id', params: { id: item.raw.term_id } }"
                class="font-weight-medium name-route"
              >
                {{ item.raw.term_name }}
              </RouterLink>
            </template>
          
            <!-- Active -->
            <template #item.active="{ item }">
              <div class="d-flex gap-3 align-center mb-1">
                <VChip
                  :color="resolveFeeStatusVariant(item.raw.deleted_at ? 'inactive' : 'active' )"
                  size="small"
                  label
                  class="text-capitalize"
                >
                  {{ item.raw.deleted_at ? $t('Inactive') : $t('Active_user') }}
                </VChip>
                <VChip
                  :color="resolvePaidPaymentStatusVariant(item.raw.status)"
                  size="small"
                  label
                  class="text-capitalize"
                >
                  {{ item.raw.status ? $t('payments.'+item.raw.status) : '' }}
                </VChip>
              </div>
            </template>

            <!-- Info -->
            <template #item.information="{ item }">
              <div style="width: 250px;">
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('payments.Amount') }}:</span>{{ item.raw.amount }}</span><br>
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('payments.Paid Payment') }}:</span>{{ item.raw.paid_amount }}</span><br>
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('Created By') }}:</span> {{ item.raw.created_by_name }}</span><br>
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('services') }}:</span> {{ item.raw.services_names }}</span>
              </div>
            </template>

            <!-- Actions -->
            <template #item.actions="{ item }">
              <IconBtn 
                :title="$t('View Payments')" 
                :to="{ name: 'payments-list-case-term-fee', params: { case: item.raw.case_id, term: item.raw.term_id, fee: item.raw.id } }"
              >
                <VIcon icon="tabler-eye" />
              </IconBtn>

              <IconBtn 
                v-if="!item.raw.deleted_at && can('edit_study-fees', 'edit_study-fees')"
                @click="feeDialog(item.raw)"
              >
                <VIcon icon="tabler-edit" />
              </IconBtn>

              <VBtn
                icon
                variant="text"
                size="small"
                color="medium-emphasis"
              >
                <VIcon
                  size="24"
                  icon="tabler-dots-vertical"
                />
                <VMenu activator="parent">
                  <VList>
                    <VListItem 
                      v-if="!item.raw.deleted_at && can('admin_study-fees', 'admin_study-fees')" 
                      @click="actionDialog(item.raw.id, 'delete')"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-trash" />
                      </template>
                      <VListItemTitle>{{ $t('delete') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="item.raw.deleted_at && can('admin_study-fees', 'admin_study-fees')" 
                      @click="actionDialog(item.raw.id, 'restore')"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-refresh" />
                      </template>
                      <VListItemTitle>{{ $t('restore') }}</VListItemTitle>
                    </VListItem>
                  </VList>
                </VMenu>
              </VBtn>
            </template>

            <!-- pagination -->
            <template #bottom>
              <VDivider />
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalFees) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalFees / options.itemsPerPage)"
                  :total-visible="$vuetify.display.xs ? 1 : Math.ceil(totalFees / options.itemsPerPage)"
                >
                  <template #prev="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      Previous
                    </VBtn>
                  </template>

                  <template #next="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      Next
                    </VBtn>
                  </template>
                </VPagination>
              </div>
            </template>
          </VDataTableServer>
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
    
    <VDialog
        v-model="putFeeDialog"
        persistent
        class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="putFeeDialog = !putFeeDialog" />

      <VCard :title="putDialogTitle">
        <VForm 
            ref="refVForm"
            @submit.prevent="putFee"
          >
          <VCardText>
            <VRow>
              <VCol cols="12" md="12">
                <AppAutocomplete
                  v-model="putCase"
                  :server-search="searchPutCases"
                  :label="$t('Name')"
                  :item-title="'name'"
                  :item-value="'id'"
                  :placeholder="$t('Type Case Name')"
                  :rules="[requiredValidator]"
                  :disabled="isEditDialog"
                  clear-icon="tabler-x"
                  clearable
                  class="pa-1"
                />
              </VCol>

              <VCol cols="12" md="12">
                <AppSelect
                  v-model="putTerm"
                  :label="$t('payments.Term')"
                  :items="terms"
                  :item-title="item => item.title"
                  :item-value="item => item.id"
                  :rules="[requiredValidator]"
                  :disabled="isEditDialog"
                  clear-icon="tabler-x"
                  clearable
                  class="pa-1"
                />
              </VCol>

              <VCol cols="12" md="12">
                <AppTextField
                  v-model="putAmount"
                  :label="$t('payments.Amount')"
                  type="number"
                  step="0.01"
                  :rules="[requiredValidator]"
                />
              </VCol>
              
              <VCol cols="12" md="12">
                <AppSelect
                    v-model="putServices"
                    :items="serviceItemsData"
                    :label="$t('services')"
                    :loading="loading.services"
                    :rules="[requiredValidator]"
                    class="pa-1"
                    chips
                    closable-chips
                    multiple
                  >
                  </AppSelect>
              </VCol>

              <VCol cols="12" md="12">
                <AppTextarea
                  v-model="putNotes"
                  :label="$t('Notes')"
                  rows="2"
                />
              </VCol>
            </VRow>
          </VCardText>
          <VCardText class="d-flex justify-end gap-3 flex-wrap">
              <VBtn v-if="addAnother" @click="putFee(false)" color="info">
                {{ $t('save_add_another') }}
              </VBtn>
              <VBtn @click="putFee()">
                  {{ $t('Save') }}
              </VBtn>
          </VCardText>
        </VForm>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isDialogVisible"
      persistent
      class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isDialogVisible = !isDialogVisible" />

      <!-- Dialog Content -->
      <VCard>
        <VCardText>
          {{ dialogMessage }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn @click="dialogFunction">
            {{ dialogButton }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDialogVisible = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
    
    <VDialog
      v-model="putFileDialog"
      persistent
      class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="putFileDialog = !putFileDialog" />

      <VCard :title="$t('payments.Pay')">
        <VForm 
            ref="refVForm"
            @submit.prevent="dialogFunction()"
          >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                sm="12"
              >
                <VRow>
                  <VCol
                    cols="12"
                    class="mt-1"
                  >
                  <VFileInput
                    v-model="file"
                    :label="$t('payments.Fee File')"
                    :loading="fileLoading"
                  />
                  </VCol>
                </VRow>
              </VCol>
            </VRow>
          </VCardText>
          <VCardText class="d-flex justify-end gap-3 flex-wrap">
              <VBtn type="submit">
                  {{ $t('payments.Pay') }}
              </VBtn>
              <VBtn
              color="secondary"
              variant="tonal"
              @click="putFileDialog = false"
              >
                  {{ $t('Close') }}
              </VBtn>
          </VCardText>
        </VForm>
      </VCard>
    </VDialog>
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