<script setup>
import { watchAssessmentTableFetch } from '@core/utils/tableFetch'
import { paginationMeta } from '@/@fake-db/utils'
import i18n from '@/plugins/i18n/index.js'
import { avatarText } from '@core/utils/formatters'
import { useRoute, useRouter } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { can, canDoes } from '@layouts/plugins/casl'
import {returnIdUserIfNotAdmin} from "@core/utils/helper";
import {
  periodTermItems,
  termItems
} from '@core/utils/generalItems';

import { useUserListStore } from '@/views/apps/user/useUserListStore'
import {goalsApi} from "@/plugins/apis/goalsReqest"
import {casesApi} from "@/plugins/apis/casesReqest"
import SnackbarComponent from '@core/components/SnackbarCustom.vue';

const route = useRoute()
const router = useRouter()
const casesReqest = casesApi()
const userListStore = useUserListStore()
const goalsReqest = goalsApi()

const snackbarRef = ref(null);
const searchQuery = ref('')
const selectedFeild = ref()
const previousSelectedFeild = ref()
const itemsFeild = ref([])
const selectedStatus = ref('active')
const totalPage = ref(1)
const totalGoals = ref(0)
const goals = ref([])
const isChangeDialogVisible = ref(false)
const changeDialogAction = ref('')
const search = ref()
const casesItem = ref([]);
const changedGoals = ref({});
const changedEvaluationGoals = ref({});
const selectedCase = ref(null);
const previousSelectedCase = ref(null);
const itemsTerm = ref(null);
const selectedTerm = ref(null);
const selectedTermData = computed(() => (itemsTerm.value || []).find(item => item.id == selectedTerm.value) || null)
const previousSelectedTerm = ref(null);
const selectedFrom = ref(null);
const selectedTo = ref(null);
const teacherItems = ref([])
const teacher = ref(returnIdUserIfNotAdmin() ?? null)
const previousTeacher = ref(returnIdUserIfNotAdmin() ?? null)
const period = ref(null)
const previousPeriod = ref(null)
const isFinishDialogVisible = ref(false)
const finishDialogStatus = ref('')
const finishDialogMessage = ref('')
const periodStatus = ref()

const loading = ref({
  users: true,
  cases: false,
  feilds:false,
  goals:false,
  period_pdf: false,
})
const answerGoals = ref({});

const updatingGoal = ref(false);

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

// Evaluating a goal only makes sense for one case, in one term, in one period.
const canEvaluate = computed(() =>
  selectedCase.value != null && selectedCase.value != ''
  && selectedTerm.value != null && selectedTerm.value != ''
  && period.value != null && period.value !== ''
)

onMounted(() => {
  termItems().then(data => {
    itemsTerm.value = data
  })
  fetchFeilds()
});

// A case belongs to a single teacher, so a previous pick may no longer be valid.
watch(teacher, () => {
  if(!updatingGoal.value) {
    selectedCase.value = null
  }
})

// Terms can define a different number of evaluation periods.
watch(selectedTerm, () => {
  if(!updatingGoal.value) {
    const allowed = periodTermItems(selectedTermData.value).map(item => String(item.value))
    if (period.value != null && period.value !== '' && !allowed.includes(String(period.value)))
      period.value = null
  }
})

const fetchGoals = () => {
  if(updatingGoal.value) {
    dialogAction()
  }
  else {
    getGoals()
  }
}

// 👉 Fetching users
const getGoals = () => {
  loading.value.goals = true
  goalsReqest.fetchAll({
    q: searchQuery.value,
    status: selectedStatus.value,
    category: 'educational',
    teacher_id: teacher.value,
    case_id: selectedCase.value,
    term_id: selectedTerm.value,
    period: (period.value != null && period.value !== '') ? `${period.value}` : null,
    feild_id: selectedFeild.value,
    date_from: selectedFrom.value,
    date_to: selectedTo.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    goals.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalGoals.value = response.data.total
    options.value.page = response.data.currentPage

    const answerValueMap = {};
    goals.value.forEach(item => {
      answerValueMap[item.id] = (changedGoals.value[item.id]?.value ?? item.evaluation_value) || null;
    });

    answerGoals.value = answerValueMap
    loading.value.goals = false

  }).catch(error => {
    loading.value.goals = false
    console.error(error)
  })
}

const printGoals = () => {
  if(canEvaluate.value){
    loading.value.period_pdf = true;
    goalsReqest.fetchAll({
      q: searchQuery.value,
      status: selectedStatus.value,
      category: 'educational',
      pdf: 'period_assessment',
      case_id: selectedCase.value,
      term_id: selectedTerm.value,
      period: period.value,
      feild_id: selectedFeild.value,
      date_from: selectedFrom.value,
      date_to: selectedTo.value,
      options: options.value,
      page: options.value.page,

    }).then(response => {
      loading.value.period_pdf = false;
      window.open(response.data.data.url, '_blank');
    }).catch(error => {
      loading.value.period_pdf = false;
      console.error(error)
    })
  }
}

// Domains are listed for every case the user may see, so this filter stands on
// its own instead of waiting for a case and a term.
const fetchFeilds = () => {
    loading.value.feilds = true;
    goalsReqest.fetchFilterItems({
      category: 'educational',
    }).then(response => {
        loading.value.feilds = false;
        itemsFeild.value = response.data.data;
        itemsFeild.value.push({
          value: 'without_feild',
          title: i18n.global.t("without_feild")
        })
    }).catch(error => {
        loading.value.feilds = false;
        console.error(error)
    })
};

const searchTeachers = params => userListStore.searchItems({
  ...params,
  roles: ['teacher'],
})

const searchCases = params => casesReqest.selectItems({
  ...params,
  teacher_id: teacher.value,
}).then(response => {
  casesItem.value = response.data.data ?? []
  return response
})

const translatedHeaders = () => {
  let headers = [
    {
      title: 'Case',
      key: 'case',
      sortable: false,
    },
    {
      title: 'behavioral_goal',
      key: 'behavioral_goal',
      sortable: false,
    },
    {
      title: 'caseField',
      key: 'first_feild',
      sortable: false,
    },
    {
      title: 'general_goal',
      key: 'general_goal',
      sortable: false,
    },
    {
      title: 'Period',
      key: 'period',
      sortable: false,
    },
    {
      title: 'Assessment',
      key: 'assessment',
      sortable: false,
    },
  ]

  // A single case is already named in the filter, no need to repeat it per row.
  if(selectedCase.value) {
    headers = headers.filter(header => header.key != 'case')
  }

  let translatedHeaders = headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }));

  return translatedHeaders;
}

const updateGoal = (event, goal, items) => {
  updatingGoal.value = true;
  previousTeacher.value = teacher.value
  previousSelectedCase.value = selectedCase.value
  previousSelectedTerm.value = selectedTerm.value
  previousSelectedFeild.value = selectedFeild.value
  previousPeriod.value = period.value

  changedEvaluationGoals.value[goal.id] = {
    goal_id: goal.id,
    period: period.value,
    value: event,
  }
  changedGoals.value[goal.id] = {
    id: goal.id,
    value: event,
    ability: items.find(item => item.value === event).ability,
  };
}

const saveGoals = () => {
   
  goalsReqest.update({goals: changedGoals.value, evaluation_goals: Object.values(changedEvaluationGoals.value)}).then(() =>{
    updatingGoal.value = false;
    changedGoals.value = {};
    changedEvaluationGoals.value = {};
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('updated_successfully'), 'success');
  })
}

const getEvaluationMethod = (itemsString) => {
  if(!itemsString){
    itemsString = `[{"title": "Able", "value": "1", "ability": "power"}, {"title": "Able with help", "value": "2", "ability": "weak"}, {"title": "Need Training", "value": "3", "ability": "weak"}]`
  }
  let items = JSON.parse(itemsString);

  let translated = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }));

  return translated;
}

const getEvaluation = (value, method) => {
  if(!method){
    method = `[{"title": "Able", "value": "1", "ability": "power"}, {"title": "Able with help", "value": "2", "ability": "weak"}, {"title": "Need Training", "value": "3", "ability": "weak"}]`
  }
  let items = JSON.parse(method);

  let translated = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }));

  let title = ''
  if(value)
   title = translated.find(item => item.value === value)?.title ?? '';

  return title;
}

const dialogAction = () => {
  if(changeDialogAction.value != 'cancel')
    isChangeDialogVisible.value = true;
  changeDialogAction.value = '';
}

const dialogFunction = () => {
  updatingGoal.value = false;
  isChangeDialogVisible.value = false;
  getGoals()
}

const dialogCancel = () => {
  changeDialogAction.value = 'cancel';
  teacher.value = previousTeacher.value;
  selectedCase.value = previousSelectedCase.value;
  selectedTerm.value = previousSelectedTerm.value;
  selectedFeild.value = previousSelectedFeild.value;
  period.value = previousPeriod.value;
  isChangeDialogVisible.value = false;
}

const fetchPeriodStatus = () => {
   
   if(canEvaluate.value){
    goalsReqest.fetchPeriodStatus({
      case_id: selectedCase.value, 
      term_id: selectedTerm.value, 
      category: 'educational', 
      period: period.value
    }).then(response => {
      periodStatus.value = response.data.data
    }).catch(error => {
      console.error(error)
    })
  }
  else {
    periodStatus.value = null;
  }
}

const dialogPeriodAction = (status) => {
  isFinishDialogVisible.value = true;
  finishDialogStatus.value = status;
  if(status == 'finish') {
    finishDialogMessage.value = i18n.global.t('Are you sure you want to finish this period?');
  }
  else if(status == 'unfinish') {
    finishDialogMessage.value = i18n.global.t('Are you sure you want to restore this period?');
  }
}

const periodAction = () => {
   
  if(canEvaluate.value){
    goalsReqest.periodAction({
      case_id: selectedCase.value, 
      term_id: selectedTerm.value, 
      category: 'educational', 
      period: period.value, 
      status: finishDialogStatus.value, 
    }).then(() =>{
      if(finishDialogStatus.value == 'finish') {
        periodStatus.value = 1
      }
      else if(finishDialogStatus.value == 'unfinish') {
        periodStatus.value = 0
      }
      getGoals()
      isFinishDialogVisible.value = false;
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('updated_successfully'), 'success');
    }).catch(error => {
      isFinishDialogVisible.value = false;
      console.error(error)
    })
  }
}

watchAssessmentTableFetch({
  search: searchQuery,
  filters: () => [
    teacher.value,
    selectedCase.value,
    period.value,
    selectedStatus.value,
    selectedTerm.value,
    selectedFeild.value,
    selectedFrom.value,
    selectedTo.value,
  ],
  periodKey: () => [selectedCase.value, selectedTerm.value, period.value],
  options,
  fetchRows: () => fetchGoals(),
  fetchPeriodStatus: () => fetchPeriodStatus(),
  isBusy: updatingGoal,
  onBusy: () => dialogAction(),
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
                v-if="!returnIdUserIfNotAdmin() && !canDoes('parent')"
              > 
                <AppAutocomplete
                    v-model="teacher"
                    :server-search="searchTeachers"
                    preload
                    :item-title="'name'"
                    :item-value="'id'"
                    :label="$t('teacher')"
                    clearable
                    clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppAutocomplete
                v-model="selectedCase"
                :server-search="searchCases"
                preload
                :preload-key="teacher"
                :label="$t('Cases')"
                :item-title="'name'"
                :item-value="'id'"
                clearable
                clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedTerm"
                  :label="$t('term')"
                  :items="itemsTerm"
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedFeild"
                  :label="$t('caseField')"
                  :loading="loading.feilds"
                  :items="itemsFeild"
                  clearable
                  clear-icon="tabler-x"
                >
                <template v-slot:selection="{ item }">
                  <div class="selected-item">
                    {{ item.title }}
                  </div>
                </template>
                </AppSelect>
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="period"
                  :label="$t('Period')"
                  :items="periodTermItems(selectedTermData)"
                  clearable
                  clear-icon="tabler-x"
                />
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
                ]"
                style="width: 6.25rem;"
                @update:model-value="options.itemsPerPage = parseInt($event, 10)"
              />
            </div>
            <VSpacer />

            <div class="justify-end d-flex align-center flex-wrap gap-4">
              <!-- 👉 Search  -->
              <!-- <div style="inline-size: 12rem;">
                <AppTextField
                  v-model="searchQuery"
                  :placeholder="$t('Search')"
                  density="compact"
                />
              </div> -->

              <!-- 👉 Add user button -->
              <VBtn v-if="can('edit_education-evaluations', 'edit_education-evaluations') && goals.length>0 && canEvaluate && periodStatus != 1 && !canDoes('parent')" @click="saveGoals" :disabled="!updatingGoal">
                {{ $t('Save') }}
              </VBtn>
              <VBtn v-if="can('edit_education-evaluations', 'edit_education-evaluations') && goals.length>0 && canEvaluate && periodStatus == 1 && !canDoes('parent')" color="success" @click="printGoals" :loading="loading.period_pdf">
                {{ $t('print_period') }}
              </VBtn>
              <VBtn v-if="can('edit_education-evaluations', 'edit_education-evaluations') && goals.length>0 && canEvaluate && periodStatus != 1 && !canDoes('parent')" color="success" @click="dialogPeriodAction('finish')" :disabled="updatingGoal">
                {{ $t('finish') }}
              </VBtn>
              <VBtn v-if="can('admin', 'admin') && goals.length>0 && canEvaluate && periodStatus == 1 && !canDoes('parent')" @click="dialogPeriodAction('unfinish')">
                {{ $t('unfinish') }}
              </VBtn>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="goals"
            :loading="loading.goals"
            :items-length="totalGoals"
            :headers="translatedHeaders()"
            class="text-no-wrap dataTable"
            hide-default-footer
            @update:options="options = $event"
          >          
            <!-- User -->
            <template #item.first_feild="{ item }">
              <div class="d-flex align-center" style="width: 130px; white-space: pre-wrap;">
                {{item.raw.assessment_first_feild?.title}}
              </div>
            </template>

            <!-- User -->
            <template #item.general_goal="{ item }">
              <div class="align-center" style="width: 130px; white-space: pre-wrap;">
                {{item.raw.assessment_parent?.title}}
              </div>
            </template>

            <template #item.behavioral_goal="{ item }">
              <div class="align-center" style="width: 250px; white-space: pre-wrap;">
                  <span>{{ item.raw.title }}</span>
                  <span v-if="item.raw.standard">&nbsp;{{ $t(item.raw.standard) }}</span>
                  <span v-if="item.raw.generalization">&nbsp;{{ $t(item.raw.generalization) }}</span>
              </div>
            </template>

            <template #item.period="{ item }">
              <div class="align-center" v-if="item.raw.date_from" style="width: 130px; white-space: pre-wrap;">
                {{ $t("goals.periodTo", {from: String(item.raw.date_from).split("-").reverse().join("-"), to : String(item.raw.date_to)}) }}
              </div>
            </template>

            <template #item.case="{ item }">
              <div class="align-center" style="width: 130px; white-space: pre-wrap;">
                {{ item.raw.case?.name }}
              </div>
            </template>

            <template #item.assessment="{ item }">
              <div class="align-center" style="width: 210px;">
                <span v-if="canEvaluate && periodStatus != 1 && !canDoes('parent')">
                  <AppSelect
                    v-model="answerGoals[item.raw.id]"
                    :items="getEvaluationMethod(item.raw.assessment_evaluation_method?.items)"
                    @update:modelValue="updateGoal($event, item.raw, getEvaluationMethod(item.raw.assessment_evaluation_method?.items))"
                  />
                </span>
                <span v-else>
                  {{getEvaluation(item.raw.evaluation_value || item.raw.value, item.raw.assessment_evaluation_method?.items)}}
                </span>
              </div>
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
                  :disabled="loading.goals"
                  total-visible="5"
                  :length="Math.ceil(totalGoals / options.itemsPerPage) || 1"
                >
                  <template #prev="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      {{ $t('$vuetify.pagination.ariaLabel.previous') }}
                    </VBtn>
                  </template>

                  <template #next="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      {{ $t('$vuetify.pagination.ariaLabel.next') }}
                    </VBtn>
                  </template>
                </VPagination>
              </div>
            </template>
          </VDataTableServer>
          <!-- SECTION -->
        </VCard>
      </vcol>
    </vrow>

    <VDialog
      v-model="isChangeDialogVisible"
      persistent
      class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="dialogCancel" />

      <!-- Dialog Content -->
      <VCard>
        <VCardText>
          {{ $t('Are you sure you want to change this, Your changes will be lost?') }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn @click="dialogFunction">
            {{ $t('yes') }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            @click="dialogCancel"
          >
            {{ $t('Cancel') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isFinishDialogVisible"
      persistent
      class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isFinishDialogVisible = !isFinishDialogVisible" />

      <!-- Dialog Content -->
      <VCard>
        <VCardText>
          {{ finishDialogMessage }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn @click="periodAction">
            {{ $t('yes') }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isFinishDialogVisible=false"
          >
            {{ $t('Cancel') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
    <SnackbarComponent ref="snackbarRef" />
  </section>
</template>

<style lang="scss">
.app-user-search-filter {
  inline-size: 31.6rem;
}

.text-capitalize {
  text-transform: capitalize;
}

.user-list-name:not(:hover) {
  color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
}
.dataTable > div{
  overflow-y: hidden !important;
}

.selected-item {
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  max-width: 300px; /* Adjust this width as needed */
}
</style>
<route lang="yaml">
  meta:
    action: access_education-evaluations
    subject: access_education-evaluations
</route>