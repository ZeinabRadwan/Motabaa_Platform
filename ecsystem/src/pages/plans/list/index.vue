<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useRoute, useRouter } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { can } from '@layouts/plugins/casl'
import { operationalPlansApi } from "@/plugins/apis/operationalPlansRequest";
import { termsApi } from "@/plugins/apis/termsRequest";
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import {
requiredValidator
} from '@validators';

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const snackbarRef = ref(null);
const operationalPlansListStore = operationalPlansApi()
const termListStore = termsApi()
const searchQuery = ref('')
const selectedTerm = ref()
const selectedStatus = ref({title: 'Active_user', value: 'active'})
const totalPage = ref(1)
const totalPlans = ref(0)
const plans = ref([])
const terms = ref([])
const putPlanDialog = ref(false);
const putDialogTitle = ref('');
const putID = ref('')
const putTerm = ref('')

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

// Headers
const translatedHeaders = () => {
  const headers = [
    {
      title: 'Term',
      key: 'term',
      with: '90%'
    },
    {
      title: 'Active',
      key: 'active',
      with: '5%',
      sortable: false,
    },
    {
      title: 'Actions',
      key: 'actions',
      with: '5%',
      sortable: false,
    },
  ]
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

const resolvePlanStatusVariant = stat => {
  const statLowerCase = stat.toLowerCase()
  if (statLowerCase === 'pending')
    return 'warning'
  if (statLowerCase === 'active')
    return 'success'
  if (statLowerCase === 'inactive')
    return 'secondary'
  
  return 'primary'
}


// 👉 Fetch Terms
termListStore.items().then(response => {
  terms.value = response.data.data
}).catch(() => {
})

// 👉 Fetching plans
const fetchPlans = () => {
  operationalPlansListStore.fetchPlans({
    q: searchQuery.value,
    term: selectedTerm.value,
    status: selectedStatus.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    plans.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalPlans.value = response.data.total
    options.value.page = response.data.currentPage
  }).catch(error => {
    console.error(error)
  })
}

const planDialog = (id) =>{
  
  putID.value = id
  putDialogTitle.value = i18n.global.t('operational_plan.add_operational_plan')
  putPlanDialog.value = true;
  putTerm.value = null;
}

const putPlan = () =>{
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){
      const formData = new FormData();
      formData.append('term_id', putTerm.value);
      formData.append('center_id', Number(localStorage.getItem('center')));

      operationalPlansListStore.putPlan(putID.value, formData).then(response => {
        if(response.data['status']){
          fetchPlans()
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
          putPlanDialog.value = false;
        }
      }).catch((e=>{
        putPlanDialog.value = false;
        const { errors: formErrors } = e.response.data
        errors.value = formErrors
      }))
    }
  })
}

const deletePlan = id => {
  operationalPlansListStore.deletePlan(id).then(() =>{
    fetchPlans()
  })
}

const restorePlan = id => {
  operationalPlansListStore.restorePlan(id).then(() =>{
    fetchPlans()
  })
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchPlans, {
  search: searchQuery,
  filters: () => [selectedTerm.value, selectedStatus.value],
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
              <VCol
                cols="12"
                sm="4"
              >
                  <AppSelect
                    v-model="selectedTerm"
                    :label="$t('Term')"
                    :items="terms"
                    :item-title="item => item.title"
                    :item-value="item => item.id"
                    class="pa-1"
                    clear-icon="tabler-x"
                    clearable
                  >
                  </AppSelect>
              </VCol>

              <!-- 👉 Select Status -->
              <VCol
                cols="12"
                sm="3"
              >
                <AppSelect
                  v-model="selectedStatus"
                  :label="$t('Active')"
                  :items="statusItems()"
                  clearable
                  clear-icon="tabler-x"
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

            <div class="justify-end d-flex align-center flex-wrap gap-4">
              <!-- 👉 Search  -->
              <div style="inline-size: 20rem;">
                <AppTextField
                  v-model="searchQuery"
                  :placeholder="$t('Search')"
                  density="compact"
                />
              </div>
              
              <!-- 👉 Add user button -->
              <VBtn
                v-if="can('edit_operation-plans', 'edit_operation-plans')"
                prepend-icon="tabler-plus"
                @click="planDialog(0)"
              >
                {{ $t('operational_plan.add_operational_plan') }}
              </VBtn>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="plans"
            :items-length="totalPlans"
            :headers="translatedHeaders()"
            class="dataTable-hidescroller-y"
            @update:options="onTableOptions"
          >
            <!-- Name -->
            <template #item.term="{ item }">
              <RouterLink
                :to="{ name: 'classes-view-id', params: { id: item.raw.term.id } }"
                class="font-weight-medium name-route"
              >
                {{ item.raw.term.title }}
              </RouterLink>
            </template>
          
            <!-- Active -->
            <template #item.active="{ item }">
              <VChip
                :color="resolvePlanStatusVariant(item.raw.deleted_at ? 'inactive' : 'active' )"
                size="small"
                label
                class="text-capitalize"
              >
                {{ item.raw.deleted_at ? $t('Inactive') : $t('Active_user') }}
              </VChip>
            </template>

            <!-- Actions -->
            <template #item.actions="{ item }">
              <IconBtn v-if="can('show_operation-plans', 'show_operation-plans')" @click="router.push({ name: 'plans-tab-id-view', params: { tab: 'general_info', id: item.raw.id } })">
                <VIcon icon="tabler-eye" />
              </IconBtn>

              <IconBtn v-if="!item.raw.deleted_at && can('admin_operation-plans', 'admin_operation-plans')" @click="deletePlan(item.raw.id)">
                <VIcon icon="tabler-trash" />
              </IconBtn>

              <IconBtn v-if="item.raw.deleted_at && can('admin_operation-plans', 'admin_operation-plans')" @click="restorePlan(item.raw.id)">
                <VIcon icon="tabler-refresh" />
              </IconBtn>
            </template>

            <!-- pagination -->
            <template #bottom>
              <VDivider />
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalPlans) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalPlans / options.itemsPerPage)"
                  :total-visible="$vuetify.display.xs ? 1 : Math.ceil(totalPlans / options.itemsPerPage)"
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
        v-model="putPlanDialog"
        persistent
        class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="putPlanDialog = !putPlanDialog" />

      <VCard :title="putDialogTitle">
        <VForm 
            ref="refVForm"
            @submit.prevent="putPlan"
          >
          <VCardText>
            <VRow>
              <VCol cols="12" md="12">
                <AppSelect
                  v-model="putTerm"
                  :label="$t('payments.Term')"
                  :items="terms"
                  :item-title="item => item.title"
                  :item-value="item => item.id"
                  clearable
                  clear-icon="tabler-x"
                  class="pa-1"
                  :rules="[requiredValidator]"
                />
              </VCol>
            </VRow>
          </VCardText>
          <VCardText class="d-flex justify-end gap-3 flex-wrap">
            <VBtn type="submit">
                {{ $t('Save') }}
            </VBtn>
            <VBtn
              color="secondary"
              variant="tonal"
              @click="putPlanDialog = false"
            >
              {{ $t('Cancel') }}
            </VBtn>
          </VCardText>
        </VForm>
      </VCard>
    </VDialog>
    <SnackbarComponent ref="snackbarRef" />
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
    action: access_operation-plans
    subject: access_operation-plans
</route>