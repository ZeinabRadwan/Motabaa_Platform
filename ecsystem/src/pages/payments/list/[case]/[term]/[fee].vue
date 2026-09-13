<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useRoute, useRouter } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { paymentsApi } from "@/plugins/apis/paymentsRequest";
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { can } from '@layouts/plugins/casl'
import { casesApi } from "@/plugins/apis/casesReqest";
import { termsApi } from "@/plugins/apis/termsRequest";
import { feesApi } from "@/plugins/apis/feesRequest";
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import {
requiredValidator
} from '@validators';
import { search } from "@core/utils/helper";
import {
  paymentMethods,
} from '@core/utils/generalItems';

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const paymentListStore = paymentsApi()
const caseListStore = casesApi()
const termListStore = termsApi()
const feeListStore = feesApi()
const userListStore = useUserListStore()
const searchQuery = ref('')
const selectedTerm = ref()
const selectedBatch = ref()
const selectedCase = ref()
const selectedFee = ref('')
const selectedStatus = ref('all')
const searchCase = ref()
const totalPage = ref(1)
const totalPayments = ref(0)
const payments = ref([])
const scases = ref([])
const terms = ref([])
const fees = ref([])
const snackbarRef = ref(null);
const fromCase = ref(false);
const putPaymentDialog = ref(false);
const putDialogTitle = ref('');
const addAnother = ref(true);
const searchPutCase = ref()
const putSCases = ref([])
const putID = ref('')
const putSCase = ref('')
const putSCaseID = ref('')
const putSCaseName = ref('')
const putTerm = ref('')
const putFee = ref('')
const putBatch = ref('')
const putAmount = ref('')
const putDueDate = ref('')
const isDialogVisible = ref(false)
const dialogPaymentID = ref()
const dialogMessage = ref('')
const dialogButton = ref('')
const dialogAction = ref('')
const loadingPayments = ref(false)
const putFileDialog = ref(false);
const fileLoading = ref(false);
const putPaidBy = ref('')
const putMethod = ref()
const putNotes = ref('')
const file = ref('')

const loading = ref({
  cases: false,
  put_cases: false
})

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})


// 👉 Fetching Payments
const fetchPayments = () => {
  paymentListStore.fetchPayments({
    q: searchQuery.value,
    term_id: route.params.term,
    scase_id: route.params.case,
    case_fee_id: route.params.fee,
    batch: selectedBatch.value,
    status: selectedStatus.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    payments.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalPayments.value = response.data.total
    options.value.page = response.data.currentPage
  }).catch(error => {
    console.error(error)
  })
}

// 👉 Fetch Terms
termListStore.items().then(response => {
  terms.value = response.data.data
}).catch(() => {
})

feeListStore.fetchFee(route.params.fee).then(response => {
  selectedFee.value = response.data.data;
}).catch(error => {
  console.error(error)
})

const fetchTerm = () => {
  termListStore.fetchTerm(route.params.term).then(response => {
    selectedTerm.value = response.data.data
  }).catch(error => {
    console.error(error)
  })
}

const fetchCase = () => {
  loading.value.cases = true;
  caseListStore.fetchCase(route.params.case).then(response => {
    loading.value.cases = false;
    selectedCase.value = response.data.data
    casesItem.value = [{ id: response.data.data.id, name: response.data.data.name }]
  }).catch(error => {
    console.error(error)
  })
};

// 👉 Fetch Cases
const searchPutCases = params => caseListStore.selectItems(params)

const printPayments = (pdfType) => {
  
  loadingPayments.value = true;
  paymentListStore.fetchPayments({
    q: searchQuery.value,
    pdf: pdfType,
    term_id: route.params.term,
    scase_id: route.params.case,
    case_fee_id: route.params.fee,
    batch: selectedBatch.value,
    status: selectedStatus.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    loadingPayments.value = false;
    window.open(response.data.data.url, '_blank');
  }).catch(error => {
    loadingPayments.value = false;
    console.error(error)
  })
}

// 👉 Headers
const translatedHeaders = () => {
  const headers = [
    {
      title: 'Name',
      key: 'scase_name',
      width: '20%',
    },
    {
      title: 'payments.Term',
      key: 'term_name',
      width: '20%',
    },
    {
      title: 'payments.Batch',
      key: 'batch',
      width: '10%',
    },
    {
      title: 'payments.Payment Status',
      key: 'status',
      width: '10%',
    },
    {
      title: 'Information',
      key: 'information',
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

const resolvePaymentStatusVariant = stat => {
  if (stat === 0)
    return 'error'
  if (stat === 1)
    return 'success'
  
  return 'primary'
}

const statusItems = () => {
  let items = [
    {
      title: 'payments.Unpaid',
      value: 0,
    },
    {
      title: 'payments.Paid',
      value: 1,
    },
    {
      title: 'All',
      value: 'all',
    },
  ]
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))

  return translatedItems;
}

const batchs = () => {
  let items = [
    {
      title: 'payments.First Batch',
      value: 1,
    },
    {
      title: 'payments.Second Batch',
      value: 2,
    },
    {
      title: 'payments.Third Batch',
      value: 3,
    },
    {
      title: 'payments.Fourth Batch',
      value: 4,
    },
    {
      title: 'payments.Fifth Batch',
      value: 5,
    },
  ]
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))

  return translatedItems;
}

const paymentDialog = (payment) =>{

  putSCases.value = []
  putSCase.value = selectedCase.value?.id ?? selectedCase.value
  putSCaseID.value = '';
  putSCaseName.value = '';
  searchPutCase.value = '';
  putID.value = payment ? payment.id : 0;
  putTerm.value = selectedTerm.value;
  putFee.value = selectedFee.value;
  putBatch.value = payment ? payment.batch : '';
  putAmount.value = payment ? payment.amount : '';
  putDueDate.value = payment ? payment.due_date : '';
  addAnother.value = payment ? false : true;
  putDialogTitle.value = i18n.global.t('payments.Add Payment')
  if(payment){
    putDialogTitle.value = i18n.global.t('payments.Edit Payment')
    putSCaseID.value = payment.scase_id;
    putSCaseName.value = payment.scase_name;
    putSCase.value = payment.scase_id
  }
  putPaymentDialog.value = true;
}

const putPayment = (closeDialog = true) =>{
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){
      
      const formData = new FormData();
      formData.append('id', putID.value);
      formData.append('scase_id', route.params.case);
      formData.append('term_id', route.params.term);
      formData.append('case_fee_id', route.params.fee);
      formData.append('batch', putBatch.value);
      formData.append('amount', putAmount.value);
      formData.append('due_date', putDueDate.value);

      paymentListStore.putPayment(formData).then(response => {
        if(response.data['status']){

          putPaymentDialog.value = !closeDialog;
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
          fetchPayments()
          refVForm.value?.resetValidation();
        }

      }).catch((e=>{
        
        const { errors: formErrors } = e.response.data
        errors.value = formErrors
      }))
    }
  })
}

const fileDialog = (id) =>{

  window.scrollTo({
    top: 0,
  });
  dialogAction.value = 'pay'
  dialogPaymentID.value = id;
  file.value = null;
  fileLoading.value = false;
  putFileDialog.value = true;
  putPaidBy.value = '';
  putMethod.value = '';
  putNotes.value = '';
}

const actionDialog = (id, action) =>{

  dialogPaymentID.value = id;
  if(action == 'unpay') {

    dialogMessage.value = i18n.global.t('payments.Are you sure you want to unpay this Payment?');
    dialogButton.value = i18n.global.t('payments.Unpay');
  }
  else if(action == 'delete') {

    dialogMessage.value = i18n.global.t('payments.Are you sure you want to delete this Payment?');
    dialogButton.value = i18n.global.t('delete');
  }
  dialogAction.value = action;
  isDialogVisible.value = true;
}

const dialogFunction = () =>{

  if(dialogAction.value == 'pay') {

    refVForm.value?.validate().then(({ valid: isValid }) => {
      if(isValid){
        
        if(!file.value || (file.value && file.value[0].size <= (100 * 1024 * 1024))) {
          
          const formData = new FormData();
          formData.append('action', dialogAction.value);
          formData.append('paid_by', putPaidBy.value);
          formData.append('method', putMethod.value);
          formData.append('notes', putNotes.value);

          if(file.value) {
            
            fileLoading.value = true
            formData.append('file', file.value[0]);
          }

          paymentListStore.actionPayment(dialogPaymentID.value, formData).then((response) => {
            if(response.status == 200){
              if(response.data.status) {
                snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data.message), 'success');
                fetchPayments()
              }
              putFileDialog.value = false;
              fileLoading.value = false;
            }
          }).catch((error=>{
        
            putFileDialog.value = false;
            fileLoading.value = false
            const { errors: formErrors } = error.response.data
            errors.value = formErrors
          }))
        }
      }
      else {
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.less_than_100MB'), 'error');
      }
    })
  }
  else if(dialogAction.value == 'unpay') {
    const formData = new FormData();
    formData.append('action', dialogAction.value);
    paymentListStore.actionPayment(dialogPaymentID.value, formData).then((response) => {
      if(response.status == 200){
        if(response.data.status) {
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data.message), 'success');
          fetchPayments()
        }
      }
    })
  }
  else if(dialogAction.value == 'delete') {
    paymentListStore.deletePayment(dialogPaymentID.value).then(() => {
      fetchPayments()
    })
  }
  isDialogVisible.value = false;
}

const openNewTab = (url) => {
  window.open(url, '_blank');
}

fetchCase()
fetchTerm()
const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchPayments, {
  search: searchQuery,
  filters: () => [selectedBatch.value, selectedStatus.value],
  options,
})
</script>

<template>
  <section v-if="selectedCase && selectedTerm">
    <VRow>

      <VCol cols="12">
        <VCard>
          <!-- 👉 Filters -->
          <VCardText v-if="!fromCase">
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
                  disabled="true"
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
                  v-model:search="searchCase"
                  :label="$t('payments.Case')"
                  :items="scases"
                  :item-title="'name'"
                  :item-value="'id'"
                  :loading="loading.cases"
                  :placeholder="$t('Type Case Name')"
                  disabled="true"
                  clear-icon="tabler-x"
                  clearable
                />
              </VCol>

              <!-- 👉 Select Payment Date -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedBatch"
                  :label="$t('payments.Batch')"
                  :items="batchs()"
                  clearable
                  clear-icon="tabler-x"
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
                  :label="$t('payments.Paid Status')"
                  :items="statusItems()"
                  class="pa-1"
                >
                </AppSelect>
              </VCol>
            </VRow>
          </VCardText>

          <VDivider v-if="!fromCase" />

          <VCardText class="d-flex flex-wrap py-4 gap-4" v-if="!fromCase">
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

            <div class="app-payment-search-filter justify-end d-flex align-center flex-wrap gap-4">
              <!-- 👉 Search  -->
              <!-- <div style="inline-size: 10rem;">
                <AppTextField
                  v-model="searchQuery"
                  :placeholder="$t('Search')"
                  density="compact"
                />
              </div> -->
              
              <!-- 👉 Add payment button -->
              <VBtn
                v-if="can('edit_study-fees', 'edit_study-fees')"
                color="success" 
                @click="printPayments('payments')" 
                :loading="loading.loadingPayments"
              >
                {{ $t('payments.Print Payments') }}
              </VBtn>
              
              <VBtn
                v-if="can('edit_study-fees', 'edit_study-fees')"
                prepend-icon="tabler-plus"
                @click="paymentDialog(null)"
              >
                {{ $t('payments.Add Payment') }}
              </VBtn>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="payments"
            :items-length="totalPayments"
            :headers="translatedHeaders()"
            class="dataTable-hidescroller-y dataTable"
            @update:options="onTableOptions"
          >
            <!-- SCase Name -->
            <template #item.scase_name="{ item }">
              <RouterLink
                :to="{ name: 'cases-view-tab-id', params: { id: item.raw.scase_id, tab: 'payments' } }"
                class="font-weight-medium name-route"
              >
                {{ item.raw.scase_name }}
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

            <!-- Batch -->
            <template #item.batch="{ item }">
              {{ $t("payments."+item.raw.batch_name) }}
            </template>

            <!-- Status -->
            <template #item.status="{ item }">
              <div style="width: 100px;">
                <VChip
                  :color="resolvePaymentStatusVariant(item.raw.status)"
                  size="small"
                  label
                  class="text-capitalize"
                >
                  {{ (item.raw.status==1) ? $t('payments.Paid') : $t('payments.Unpaid') }}
                </VChip>
              </div>
            </template>

            <!-- Info -->
            <template #item.information="{ item }">
              <div style="width: 250px;">
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('payments.Amount') }}:</span> {{ item.raw.amount }}<br></span>
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('Created By') }}:</span> {{ item.raw.created_by_name }}<br></span>
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('payments.Due Date') }}:</span> {{ item.raw.due_date }}<br></span>
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('services') }}:</span> {{ item.raw.services }}<br></span>
                <span style="font-size: 13px;" v-if="item.raw.status==1"> <span style="color: #7374d3;">{{ $t('payments.Payment Date') }}:</span> {{ item.raw.payment_date }}<br></span>
                <span style="font-size: 13px;" v-if="item.raw.status==1"> <span style="color: #7374d3;">{{ $t('payments.Paid By') }}:</span> {{ item.raw.paid_by }}<br></span>
                <span style="font-size: 13px;" v-if="item.raw.method"> <span style="color: #7374d3;">{{ $t('payments.method') }}:</span> {{ item.raw.method }}<br></span>
                <span style="font-size: 13px;" v-if="item.raw.notes"> <span style="color: #7374d3;">{{ $t('payments.notes') }}:</span> {{ item.raw.notes }}</span>
              </div>
            </template>

            <!-- Actions -->
            <template #item.actions="{ item }">
              <IconBtn v-if="item.raw.payment_file_url" @click="openNewTab(item.raw.payment_file_url)" color="success">
                <VIcon 
                  icon="tabler-download"
                  size="22"
                />
              </IconBtn>

              <IconBtn 
                v-if="(item.raw.status==0 && can('edit_study-fees', 'edit_study-fees')) || can('admin_study-fees', 'admin_study-fees')"
                @click="paymentDialog(item.raw)"
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
                      v-if="item.raw.status==0 && can('admin_study-fees', 'admin_study-fees')"
                      @click="fileDialog(item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon color="success" icon="tabler-check" />
                      </template>
                      <VListItemTitle>{{ $t('payments.Pay') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="item.raw.status==1 && can('admin_study-fees', 'admin_study-fees')"
                      @click="actionDialog(item.raw.id, 'unpay')"
                    >
                      <template #prepend>
                        <VIcon color="error" icon="tabler-x" />
                      </template>
                      <VListItemTitle>{{ $t('payments.Unpay') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="item.raw.status==0 && can('admin_study-fees', 'admin_study-fees')" 
                      @click="actionDialog(item.raw.id, 'delete')"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-trash" />
                      </template>
                      <VListItemTitle>{{ $t('delete') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="item.raw.status==1 && can('edit_study-fees', 'edit_study-fees')" 
                      @click="printPayments('payment_statement', item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-download" />
                      </template>
                      <VListItemTitle>{{ $t('payments.payment_statement') }}</VListItemTitle>
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
                  {{ paginationMeta(options, totalPayments) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalPayments / options.itemsPerPage)"
                  :total-visible="$vuetify.display.xs ? 1 : Math.ceil(totalPayments / options.itemsPerPage)"
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
    
    <VDialog
        v-model="putPaymentDialog"
        persistent
        class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="putPaymentDialog = !putPaymentDialog" />

      <VCard :title="putDialogTitle">
        <VForm 
            ref="refVForm"
            @submit.prevent="putPayment"
          >
          <VCardText>
            <VRow>
              <VCol cols="12" md="12">
                <AppAutocomplete
                  v-model="putSCase"
                  :server-search="searchPutCases"
                  :label="$t('Name')"
                  :item-title="'name'"
                  :item-value="'id'"
                  :placeholder="$t('Type Case Name')"
                  :rules="[requiredValidator]"
                  disabled="true"
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
                  disabled="true"
                  clear-icon="tabler-x"
                  clearable
                  class="pa-1"
                />
              </VCol>
              
              <VCol cols="12" md="12">
                <AppSelect
                  v-model="putFee"
                  :label="$t('payments.fees')"
                  :items="fees"
                  :item-title="item => item.title"
                  :item-value="item => item.id"
                  :rules="[requiredValidator]"
                  disabled="true"
                  class="pa-1"
                  clear-icon="tabler-x"
                  clearable
                />
              </VCol>
              
              <VCol cols="12" md="6">
                <AppSelect
                  v-model="putBatch"
                  :label="$t('payments.Batch')"
                  :items="batchs()"
                  clearable
                  clear-icon="tabler-x"
                  class="pa-1"
                  :rules="[requiredValidator]"
                />
              </VCol>
              
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="putAmount"
                  :label="$t('payments.Amount')"
                  type="number"
                  step="0.01"
                  :rules="[requiredValidator]"
                />
              </VCol>
              
              <VCol cols="12" md="12">
                <AppDateTimePicker
                  v-model="putDueDate"
                  :label="$t('payments.Payment Date')"
                  :rules="[requiredValidator]"
                />
              </VCol>
            </VRow>
          </VCardText>
          <VCardText class="d-flex justify-end gap-3 flex-wrap">
              <VBtn v-if="addAnother" @click="putPayment(false)" color="info">
                {{ $t('save_add_another') }}
              </VBtn>
              <VBtn @click="putPayment()">
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
              <VCol cols="12" md="12">
                <AppTextField
                  v-model="putPaidBy"
                  :label="$t('Name')"
                  :rules="[requiredValidator]"
                />
              </VCol>
              <VCol cols="12" md="12">
                <AppSelect
                  v-model="putMethod"
                  :label="$t('payments.method')"
                  :items="paymentMethods()"
                  clearable
                  clear-icon="tabler-x"
                  class="pa-1"
                  :rules="[requiredValidator]"
                />
              </VCol>
              <VCol cols="12" md="12">
                <AppTextField
                  v-model="putNotes"
                  :label="$t('payments.notes')"
                />
              </VCol>
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
                    :label="$t('payments.Payment File')"
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