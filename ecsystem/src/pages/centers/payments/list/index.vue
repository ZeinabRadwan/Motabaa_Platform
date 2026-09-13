<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { paginationMeta } from '@/@fake-db/utils';
import { centersApi } from "@/plugins/apis/centersRequest";
import i18n from '@/plugins/i18n/index.js';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import { can } from '@layouts/plugins/casl';
import {
  betweenValidator,
  emailValidator,
  integerValidator,
  requiredValidator
} from '@validators';
import { useRoute, useRouter } from 'vue-router';
import { VDataTableServer } from 'vuetify/labs/VDataTable';

const props = defineProps({
  centerData: {
    type: Object,
    required: true,
  },
  itemsPerPage: 6
})

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const snackbarRef = ref(null);
const searchQuery = ref('')
const centerListStore = centersApi()
const selectedFrom = ref()
const selectedTo = ref()
const selectedPackage = ref()
const selectedStatus = ref('active')
const packages = ref([])
const payments = ref([])
const totalPage = ref(1)
const totalPayments = ref(0)
const exportLoading = ref(false)
const fromCenter = ref(false)
const isDialogVisible = ref(false)
const dialogPaymentID = ref()
const dialogMessage = ref('')
const dialogButton = ref('')
const dialogAction = ref('')
const putFileDialog = ref(false);
const fileLoading = ref(false);
const putName = ref('')
const putEmail = ref('')
const putPhone = ref()
const file = ref('')

const today = new Date();
const formattedToday = today.toLocaleDateString('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
});
const defaultFrom = ref(`1900-12-30`);
const defaultTo = ref(formattedToday);

const options = ref({
  page: 1,
  itemsPerPage: (props.centerData && fromCenter) ? props.itemsPerPage : 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

if(props.centerData && props.centerData.id>0) {
  fromCenter.value = true
}

// 👉 Headers
const translatedHeaders = () => {
  const headers = [];
  headers.push({
    title: 'centers.user_name',
    key: 'user_name',
    width: '35%',
  });

  if(props.centerData && fromCenter) {
    headers.push({
      title: 'centers.amount',
      key: 'amount',
      width: '20%',
    });
  }

  headers.push({
    title: 'centers.subscription',
    key: 'package_name',
    width: '25%',
  });
  
  headers.push({
    title: 'Information',
    key: 'information',
    width: '20%',
  });

  if(!(props.centerData && fromCenter)) {
    headers.push({
      title: 'Actions',
      key: 'actions',
      width: '10%',
      sortable: false,
    });
  }

  let translatedHeaders = headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }))

  return translatedHeaders;
}

const statusItems = () => {
  let items = [
    {
      title: 'All',
      value: 'all',
    },
    {
      title: 'centers.pay',
      value: 1,
    },
    {
      title: 'centers.unpay',
      value: 0,
    },
  ]
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))

  return translatedItems;
}

const resolvePaymentStatusVariant = stat => {
  const statLowerCase = stat.toLowerCase()
  if (statLowerCase === 'pending')
    return 'warning'
  if (statLowerCase === 'active')
    return 'success'
  if (statLowerCase === 'inactive')
    return 'secondary'
  
  return 'primary'
}


centerListStore.fetchPackages().then(response => {
  packages.value = response.data.data
})

// 👉 Fetching Payments
const fetchPayments = () => {
  centerListStore.fetchPayments({
    q: searchQuery.value,
    from_date: selectedFrom.value,
    to_date: selectedTo.value,
    package_id: selectedPackage.value,
    status: selectedStatus.value,
    center_id: (props.centerData && props.centerData.id>0 && fromCenter.value) ? props.centerData.id : null,
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

// 👉 Export Cases
const exportPayments = () => {
  exportLoading.value = true;
  centerListStore.fetchPayments({
    q: searchQuery.value,
    center_id: (props.centerData && fromCenter) ? props.centerData.id : null,
    from_date: selectedFrom.value,
    to_date: selectedTo.value,
    package_id: selectedPackage.value,
    status: selectedStatus.value,
    center_id: (props.centerData && fromCenter) ? props.centerData.id : null,
    pdf: 'payments',
  }).then(response => {
    exportLoading.value = false;
    window.open(response.data.data.url, '_blank');
  }).catch(error => {
    exportLoading.value = false;
    console.error(error)
  })
}

const deletePayment = id => {
  centerListStore.deletePayment(id).then(response => {
    if(response.data['status']){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      fetchPayments()
    }
  })
}

const restorePayment = id => {
  centerListStore.restorePayment(id).then(response => {
    if(response.data['status']){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      fetchPayments()
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
  putName.value = '';
  putEmail.value = '';
  putPhone.value = '';
}

const actionDialog = (id, action) =>{

  dialogPaymentID.value = id;
  if(action == 'unpay') {

    dialogMessage.value = i18n.global.t('centers.Are you sure you want to unpay this Payment?');
    dialogButton.value = i18n.global.t('centers.Unpay');
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
          formData.append('name', putName.value);
          formData.append('email', putEmail.value);
          formData.append('phone', putPhone.value);

          if(file.value) {
            
            fileLoading.value = true
            formData.append('file', file.value[0]);
          }

          centerListStore.actionPayment(dialogPaymentID.value, formData).then((response) => {
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
    centerListStore.actionPayment(dialogPaymentID.value, formData).then((response) => {
      if(response.status == 200){
        if(response.data.status) {
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data.message), 'success');
          fetchPayments()
        }
      }
    })
  }
  isDialogVisible.value = false;
}

const openNewTab = (url) => {
  window.open(url, '_blank');
}

watch(selectedFrom, query => {
    if(query == null || query == ''){
      defaultFrom.value = `1900-12-30`
    }
    else {
      defaultFrom.value = selectedFrom.value
    }
})
watch(selectedTo, query => {
    if(query == null || query == ''){
        defaultTo.value = formattedToday
    }
    else {
      defaultTo.value = selectedTo.value
    }
})

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchPayments, {
  search: searchQuery,
  filters: () => [selectedStatus.value, selectedPackage.value, selectedFrom.value, selectedTo.value],
  options,
})
</script>

<template>
  <section>
    <VRow>

      <VCol cols="12">
        <VCard>
          <!-- 👉 Filters -->
          <VCardText v-if="!fromCenter">
            <VRow>

              <!-- 👉 Select Start Date -->
              <VCol
                cols="12"
                sm="4"
              >
                <div class="pa-1">
                  {{ $t('date') }}
                </div>
                <VRow>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppDateTimePicker
                      v-model="selectedFrom"
                      :key="defaultTo"
                      :config="{ enableTime: false, dateFormat: 'Y-m-d', enable: [{ from: `1900-12-30`, to: `${defaultTo}` }] }"
                      :placeholder="$t('from')"
                    />
                  </VCol>
                  
                  <!-- 👉 Select End Date -->
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppDateTimePicker
                      v-model="selectedTo"
                      :key="defaultFrom"
                      :config="{ enableTime: false, dateFormat: 'Y-m-d', enable: [{ from:  `${defaultFrom}`, to: `9999-12-31` }] }"
                      :placeholder="$t('to')"
                    />
                  </VCol>
                </VRow>
              </VCol>

              <!-- 👉 Select Status -->
              <VCol
                cols="12"
                sm="3"
              >
                <AppSelect
                  v-model="selectedPackage"
                  :label="$t('centers.subscription')"
                  :items="packages"
                  :item-title="'title'"
                  :item-value="'id'"
                  clearable
                  clear-icon="tabler-x"
                  class="pa-1"
                >
                </AppSelect>
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VCardText class="d-flex flex-wrap py-4 gap-4" v-if="!fromCenter">
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

            <div class="justify-end d-flex align-center flex-wrap gap-4">
              <!-- 👉 Search  -->
              <div style="inline-size: 20rem;">
                <AppTextField
                  v-model="searchQuery"
                  :placeholder="$t('Search')"
                  density="compact"
                />
              </div>
              
              <!-- 👉 Add payment button -->
              <VBtn
                v-if="can('edit_centers', 'edit_centers')"
                :loading="exportLoading"
                @click="exportPayments()"
              >
                {{ $t('centers.print_report') }}
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
            class="dataTable-hidescroller-y"
            @update:options="onTableOptions"
          >
            <template #item.user_name="{ item }">
              {{ item.raw.user.name }}
            </template>

            <template #item.information="{ item }">
              <div style="width: 250px;">
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('centers.payment_type') }}: </span>{{ item.raw.payment_type == 1 ? $t('centers.yearly') : $t('centers.monthly')}}</span><br>
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('centers.payment_duration') }}: </span>{{ item.raw.payment_duration }}</span><br>
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('centers.payment_date') }}: </span>{{ item.raw.date }}</span><br>
                <span style="font-size: 13px;"> <span style="color: #7374d3;">{{ $t('centers.payment_expiry_date') }}: </span>{{ item.raw.expiry_date }}</span>
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

              <VBtn
                v-if="can('edit_centers', 'edit_centers') && (item.raw.status==0 || item.raw.status==1 && item.raw.payment_file_url)"
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
                      v-if="item.raw.status==0 && can('edit_centers', 'edit_centers')"
                      @click="fileDialog(item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon color="success" icon="tabler-check" />
                      </template>
                      <VListItemTitle>{{ $t('centers.Pay') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="item.raw.status==1 && can('edit_centers', 'edit_centers') && item.raw.payment_file_url"
                      @click="actionDialog(item.raw.id, 'unpay')"
                    >
                      <template #prepend>
                        <VIcon color="error" icon="tabler-x" />
                      </template>
                      <VListItemTitle>{{ $t('centers.Unpay') }}</VListItemTitle>
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
                  total-visible="5"
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

      <VCard :title="$t('centers.Pay')">
        <VForm 
            ref="refVForm"
            @submit.prevent="dialogFunction()"
          >
          <VCardText>
            <VRow>
              <VCol cols="12" md="12">
                <AppTextField
                  v-model="putName"
                  :label="$t('Name')"
                  :rules="[requiredValidator]"
                />
              </VCol>
              <VCol cols="12" md="12">
                <AppTextField
                  v-model="putEmail"
                  :label="$t('centers.email')"
                      :rules="[requiredValidator, emailValidator]"
                />
              </VCol>
              <VCol cols="12" md="12">
                <AppTextField
                  v-model="putPhone"
                  :label="$t('phone')"
                      :rules="[requiredValidator, integerValidator, betweenValidator(putPhone, 12, 12)]"
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
                    :label="$t('centers.payment_file')"
                    :loading="fileLoading"
                    :rules="[requiredValidator]"
                  />
                  </VCol>
                </VRow>
              </VCol>
            </VRow>
          </VCardText>
          <VCardText class="d-flex justify-end gap-3 flex-wrap">
              <VBtn type="submit">
                  {{ $t('centers.Pay') }}
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
    <SnackbarComponent ref="snackbarRef"></SnackbarComponent>
  </section>
</template>

<style lang="scss">
  .text-capitalize {
    text-transform: capitalize;
  }

  .name-route:not(:hover) {
    color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
  }
</style>

<route lang="yaml">
  meta:
    action: access_centers
    subject: access_centers
</route>