<script setup>
import { watchServerTableFetch } from '@core/utils/tableFetch'
import { useRoute, useRouter } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { operationalPlansApi } from "@/plugins/apis/operationalPlansRequest";
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { can } from '@layouts/plugins/casl'
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import {
  departmentsItems
} from '@core/utils/generalItems';
import {
requiredValidator
} from '@validators';

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const snackbarRef = ref(null);
const planListStore = operationalPlansApi()
const searchQuery = ref('')
const selectedDepartment = ref()
const selectedStatus = ref('all')
const totalPage = ref(1)
const totalGoals = ref(0)
const goals = ref([])
const isActionDialogVisible = ref(false);
const actionDialogMessage = ref();
const actionDialogButton = ref();
const actionDialogGoalID = ref();
const actionDialogAction = ref();
const actionDialogImplementedAt = ref();

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
      title: 'Department',
      key: 'department',
      width: '20%'
    },
    {
      title: 'operational_plan.general_goal',
      key: 'general_goal',
      width: '25%'
    },
    {
      title: 'operational_plan.goal_information',
      key: 'goal_information',
      width: '30%'
    },
    {
      title: 'operational_plan.implementation',
      key: 'implementation',
      width: '10%'
    },
    {
      title: 'Actions',
      key: 'actions',
      sortable: false,
      width: '15%'
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
      title: 'operational_plan.implemented',
      value: '1',
    },
    {
      title: 'operational_plan.not_implemented',
      value: '0',
    },
  ]
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))

  return translatedItems;
}

const resolveGoalStatusVariant = stat => {
  const statLowerCase = stat.toLowerCase()
  if (statLowerCase === 'implemented')
    return 'success'
  if (statLowerCase === 'not_implemented')
    return 'danger'
  
  return 'primary'
}


// 👉 Fetching Goals
const fetchGoals = () => {
  planListStore.fetchGoals({
    q: searchQuery.value,
    operational_plan_id: Number(route.params.id),
    department: selectedDepartment.value,
    status: selectedStatus.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    goals.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalGoals.value = response.data.total
    options.value.page = response.data.currentPage
  }).catch(error => {
    console.error(error)
  })
}

const actionDialog = (id, action) =>{

  actionDialogGoalID.value = id;
  actionDialogImplementedAt.value = null
  if(action == 'implemented') {

    actionDialogMessage.value = i18n.global.t('operational_plan.Are you sure this goal is implemented?');
    actionDialogButton.value = i18n.global.t('yes');
  }
  else if(action == 'not_implemented') {

    actionDialogMessage.value = i18n.global.t('operational_plan.Are you sure this goal is not implemented?');
    actionDialogButton.value = i18n.global.t('yes');
  }
  else if(action == 'delete') {

    actionDialogMessage.value = i18n.global.t('operational_plan.Are you sure you want to delete this goal?');
    actionDialogButton.value = i18n.global.t('delete');
  }
  actionDialogAction.value = action;
  isActionDialogVisible.value = true;
}

const dialogFunction = () =>{

  if(actionDialogAction.value == 'delete') {
    planListStore.deleteGoal(actionDialogGoalID.value).then(() =>{
      fetchGoals();
      isActionDialogVisible.value = false;
    })
  }
  else {
    refVForm.value?.validate().then(({ valid: isValid }) => {
      if(isValid){
        const formData = new FormData();
        formData.append('action', actionDialogAction.value);
        formData.append('implemented_at', actionDialogImplementedAt.value);
        planListStore.actionGoal(actionDialogGoalID.value, formData).then((response) => {
          if(response.status == 200){
            if(response.data.status) {
              snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data.message), 'success');
              fetchGoals();
              isActionDialogVisible.value = false;
            }
          }
        })
      }
    })
  }
}

watchServerTableFetch(fetchGoals, {
  search: searchQuery,
  filters: () => [selectedDepartment.value, selectedStatus.value],
  options,
})
</script>

<template>
  <section>
    
    <VCard>
      <VRow>

        <VCol cols="12">
            <!-- 👉 Filters -->
            <VCardText>
              <VRow>

                <!-- 👉 Select Start Date -->
                <VCol
                  cols="12"
                  sm="4"
                >
                  <AppSelect
                    v-model="selectedDepartment"
                    :label="$t('Department')"
                    :items="departmentsItems()"
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
                    :label="$t('operational_plan.status')"
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
                
                <!-- 👉 Add button -->
                <VBtn
                  v-if="can('edit_operation-plans', 'edit_operation-plans')"
                  prepend-icon="tabler-plus"
                  @click="()=> router.push(route.query.to ? String(route.query.to) : `/plans/goals/${Number(route.params.id)}/put/0`)"
                >
                  {{ $t('Add Goal') }}
                </VBtn>
              </div>
            </VCardText>

            <VDivider />

            <!-- SECTION datatable -->
            <VDataTableServer
              v-model:items-per-page="options.itemsPerPage"
              v-model:page="options.page"
              :items="goals"
              :items-length="totalGoals"
              :headers="translatedHeaders()"
              class="dataTable-hidescroller-y"
              style="width:100%"
              @update:options="options = $event"
            >
              <!-- Department -->
              <template #item.department="{ item }">
                {{ item.raw.department ? $t('department.'+item.raw.department) : ''}}
              </template>

              <!-- General Goal -->
              <template #item.general_goal="{ item }">
                {{ item.raw.general_goal }}
              </template>

              <!-- Information -->
              <template #item.goal_information="{ item }">
                <div class="pt-2 pb-2" style="font-size: 13px;">
                  <span style="color: #7374d3;">{{ $t('operational_plan.targeted_by') }}:</span> {{ item.raw.targeted_by }}<br>
                  <span style="color: #7374d3;">{{ $t('operational_plan.implemented_by') }}:</span> {{ item.raw.implemented_by }}<br>
                  <span style="color: #7374d3;">{{ $t('operational_plan.activities_and_programs') }}:</span> <span v-for='activitiesAndPrograms in item.raw.activities_and_programs.split("\n")'><br>{{ activitiesAndPrograms }}</span><br>
                  <span style="color: #7374d3;">{{ $t('operational_plan.goals_services') }}:</span> <span v-for='goalsServices in item.raw.goals_services.split("\n")'><br>{{ goalsServices }}</span><br>
                  <span style="color: #7374d3;">{{ $t('operational_plan.performance_indicator') }}:</span> {{ item.raw.performance_indicator }}<br>
                  <span style="color: #7374d3;">{{ $t('operational_plan.reference_feed') }}:</span> <span v-for='referenceFeed in item.raw.reference_feed.split("\n")'><br>{{ referenceFeed }}</span><br>
                  <span v-if="item.raw.implemented_at"> <span style="color: #7374d3;">{{ $t('operational_plan.implemented_at') }}:</span> {{ item.raw.implemented_at }}</span>
                </div>
              </template>
              
              <!-- Implementation -->
              <template #item.implementation="{ item }">
                <VChip
                  :color="resolveGoalStatusVariant((item.raw.status==1) ? 'implemented' : 'not_implemented' )"
                  size="small"
                  label
                  class="text-capitalize"
                >
                  {{ (item.raw.status==1) ? $t('operational_plan.implemented') : $t('operational_plan.not_implemented') }}
                </VChip>
              </template>

              <!-- Actions -->
              <template #item.actions="{ item }">
                <IconBtn 
                  v-if="can('edit_operation-plans', 'edit_operation-plans')" 
                  @click="()=> router.push(`/plans/goals/${Number(route.params.id)}/put/${item.raw.id}`)"
                >
                  <VIcon icon="tabler-edit" />
                </IconBtn>

                <IconBtn 
                  v-if="can('edit_operation-plans', 'edit_operation-plans')" 
                  @click="actionDialog(item.raw.id, 'delete')"
                >
                  <VIcon icon="tabler-trash" />
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
                          v-if="item.raw.status==0 && can('edit_operation-plans', 'edit_operation-plans')"
                          @click="actionDialog(item.raw.id, 'implemented')"
                        >
                          <template #prepend>
                            <VIcon color="success" icon="tabler-check" />
                          </template>
                          <VListItemTitle>{{ $t('operational_plan.implement') }}</VListItemTitle>
                        </VListItem>

                        <VListItem 
                          v-if="item.raw.status==1 && can('edit_operation-plans', 'edit_operation-plans')"
                          @click="actionDialog(item.raw.id, 'not_implemented')"
                        >
                          <template #prepend>
                            <VIcon color="error" icon="tabler-x" />
                          </template>
                          <VListItemTitle>{{ $t('operational_plan.not_implemented') }}</VListItemTitle>
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
                    {{ paginationMeta(options, totalGoals) }}
                  </p>

                  <VPagination
                    v-model="options.page"
                    :length="Math.ceil(totalGoals / options.itemsPerPage)"
                    :total-visible="$vuetify.display.xs ? 1 : Math.ceil(totalGoals / options.itemsPerPage)"
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
        </vcol>
      </VRow>

      <VDialog
        v-model="isActionDialogVisible"
        persistent
        class="v-dialog-sm"
      >
        <!-- Dialog close btn -->
        <DialogCloseBtn @click="isActionDialogVisible = !isActionDialogVisible" />

        <!-- Dialog Content -->
        <VCard :title="actionDialogMessage">
          <VForm 
            ref="refVForm"
            @submit.prevent="dialogFunction"
          >
            <VCardText>
              <AppDateTimePicker
                v-if="actionDialogAction == 'implemented'"
                v-model="actionDialogImplementedAt"
                :label="$t('date')"
                :config="{ enableTime: false, dateFormat: 'Y-m-d'}"
                :rules="[requiredValidator]"
              />
            </VCardText>

            <VCardText class="d-flex justify-end gap-3 flex-wrap">
              <VBtn type="submit">
                {{ actionDialogButton }}
              </VBtn>
              <VBtn
                color="secondary"
                variant="tonal"
                @click="isActionDialogVisible = false"
              >
                {{ $t('Cancel') }}
              </VBtn>
            </VCardText>
          </VForm>
        </VCard>
      </VDialog>
      <SnackbarComponent ref="snackbarRef" />
    </VCard>
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