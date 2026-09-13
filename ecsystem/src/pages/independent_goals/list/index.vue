<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { paginationMeta } from '@/@fake-db/utils'
import i18n from '@/plugins/i18n/index.js'
import { avatarText } from '@core/utils/formatters'
import { useRoute, useRouter } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { can, canDoes } from '@layouts/plugins/casl'
import {returnIdUserIfNotAdmin} from "@core/utils/helper";
import {assessmentsApi} from "@/plugins/apis/assessmentReqest"
import {
  termItems,
  goalsGeneralizationItems,
  goalsStandardItems
} from '@core/utils/generalItems';
import {
requiredValidator
} from '@validators';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';

import { useUserListStore } from '@/views/apps/user/useUserListStore'
import {goalsApi} from "@/plugins/apis/goalsReqest"
import {casesApi} from "@/plugins/apis/casesReqest"
import {termsApi} from "@/plugins/apis/termsRequest"

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const casesReqest = casesApi()
const userListStore = useUserListStore()
const assessmentsReqest = assessmentsApi()
const goalsReqest = goalsApi()
const termsReqest = termsApi()

const searchQuery = ref('')
const selectedFeild = ref(null)
const snackbarRef = ref(null)
const itemsFeild = ref([])
const selectedStatus = ref('active')
const totalPage = ref(1)
const totalGoals = ref(0)
const users = ref([])
const isDialogVisible = ref(false)
const isDialogVisibleAddGoal = ref(false)
const isAddDialogVisible = ref(true)
const is_without = ref(false)
const search = ref()
const searchGoals = ref()
const casesItem = ref([]);
const loadingItem = ref(false);
const selectedCase = ref(null);
const itemsTerm = ref(null);
const selectedTerm = ref(null);
const selectedFrom = ref(null);
const selectedTo = ref(null);
const sessionsCountMin = ref(null);
const sessionsCountMax = ref(null);
const editGeneralGoal = ref(null);
const editFirstFeild = ref(null);
const teacherItems = ref([])
const teacher = ref(returnIdUserIfNotAdmin() ?? null)
const canEditCustom = ref(false);

const parentId = ref(null)
const rootId = ref(null);
const rootItems = ref([]);
const feildId = ref(null);
const feildItems = ref([]);
const goalId = ref(null);
const goalItems = ref([]);

const loading = ref({
  users: true,
  cases: false,
  feilds:false,
  goals:false,
  assessment: false,
  plan_pdf: false,
  period_pdf: false,
})

const idGoal = ref(null);
const editBehavioralGoal = ref(null);
const editGeneralization = ref(null);
const editStandard = ref(null);
const editFromDate = ref(null);
const editToDate = ref(null);
const isDialogTransferGoalVisible = ref(false);
const transferTerms = ref([]);
const dialogTransferGoalID = ref();
const transferToTerm = ref();

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

onMounted(() => {
  termItems().then(data => {
    itemsTerm.value = data
  })
  fetchFeilds()
})



watch(editToDate, query => {
  const from = new Date(editFromDate.value);
  const to = new Date(editToDate.value);
  if (from > to) {
    editToDate.value = '';
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('cant_choose_date_before'), 'warning');
  } 
})

watch(teacher, query => {
  casesItem.value = []
  selectedCase.value = null
})

// 👉 Fetching users
const fetchGoals = () => {
  loading.value.goals = true
  goalsReqest.fetchAll({
    q: searchQuery.value,
    status: selectedStatus.value,
    category: 'independent',
    teacher_id: teacher.value,
    case_id: selectedCase.value,
    term_id: selectedTerm.value,
    feild_id: selectedFeild.value,
    date_from: selectedFrom.value,
    date_to: selectedTo.value,
    sessions_count_min: sessionsCountMin.value,
    sessions_count_max: sessionsCountMax.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    users.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalGoals.value = response.data.total
    options.value.page = response.data.currentPage
    loading.value.goals = false
  }).catch(error => {
    loading.value.goals = false
    console.error(error)
  })
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchGoals, {
  search: searchQuery,
  filters: () => [
    teacher.value,
    selectedCase.value,
    selectedStatus.value,
    selectedTerm.value,
    selectedFeild.value,
    selectedFrom.value,
    selectedTo.value,
    sessionsCountMin.value,
    sessionsCountMax.value,
  ],
  options,
})

const fetchFeilds = () => {
    loading.value.feilds = true;
    goalsReqest.fetchFilterItems({
      category: 'independent',
    }).then(response => {
        loading.value.feilds = false;
        itemsFeild.value = response.data.data;
        itemsFeild.value.push({
          value: 'without_feild',
          title: i18n.global.t("without_feild")
        })
    }).catch(error => {
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
      width: '12%',
      sortable: false,
    },
    {
      title: 'goals.skill',
      key: 'behavioral_goal',
      width: '30%',
      sortable: false,
    },
    {
      title: 'goals.reinforcement',
      key: 'first_feild',
      width: '20%',
      sortable: false,
    },
    {
      title: 'Period',
      key: 'period',
      width: '15%',
      sortable: false,
    },
    {
      title: 'Assessment',
      key: 'assessment',
      width: '10%',
      sortable: false,
    },
    {
      title: 'goals.sessions_count',
      key: 'sessions_count',
      width: '10%',
      sortable: false,
    },
    {
      title: 'Status',
      key: 'status',
      width: '10%',
      sortable: false,
    },
    // {
    //   title: 'Active',
    //   key: 'active',
    //   sortable: false,
    // },
    {
      title: 'Actions',
      key: 'actions',
      width: '15%',
      sortable: false,
    },
  ]

  if(selectedCase.value) {
    headers = headers.filter(header => header.key != 'case')
  }

  if(canDoes('parent')) {
    headers = headers.filter(header => !['sessions_count', 'status'].includes(header.key))
  }

  let translatedHeaders = headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }));

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
  ];
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }));

  return translatedItems;
};

const resolveUserStatusVariant = stat => {
  const statLowerCase = stat.toLowerCase()
  if (statLowerCase === 'pending')
    return 'warning'
  if (statLowerCase === 'active')
    return 'success'
  if (statLowerCase === 'inactive')
    return 'secondary'
  
  return 'primary'
}

const editGoal = goal => {
  window.scrollTo({
    top: 0,
  });
  editBehavioralGoal.value = goal.title
  editGeneralization.value = goal.generalization
  editStandard.value = goal.standard
  editFromDate.value = goal.date_from
  editFirstFeild.value = goal.custom_first_feild;
  editGeneralGoal.value = goal.custom_general_goal;
  editToDate.value = goal.date_to
  idGoal.value = goal.id;
  isDialogVisible.value = true;
  isAddDialogVisible.value = false;
  canEditCustom.value = goal.assessment_id ? false : true;
}

const saveGoal = () => {

  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){

      if((editToDate.value != '' && editToDate.value != null) && (editFromDate.value == '' || editFromDate.value == null)){
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('must_add_from_to_dates'), 'warning');
        return ;
      }
      if((editFromDate.value != '' && editFromDate.value != null) && (editToDate.value == '' || editToDate.value == null)){
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('must_add_from_to_dates'), 'warning');
        return ;
      }
      goalsReqest.put({title: editBehavioralGoal.value,
      generalization: editGeneralization.value,
      standard: editStandard.value,
      custom_first_feild: editFirstFeild.value,
      date_from: editFromDate.value,
      date_to: editToDate.value,}, idGoal.value).then(() =>{
        isDialogVisible.value = false;
        isDialogVisibleAddGoal.value = false;
        idGoal.value = null;
        fetchGoals()
      })
    }
  })
}

const deleteUser = id => {
  goalsReqest.delete(id).then(() =>{
    fetchGoals()
  })
}

const restoreUser = id => {
  goalsReqest.restore(id).then(() =>{
    fetchGoals()
  })
}

const fetchAssessments = (parentId, root = false, search = '') => {
  loading.value.assessment = true;
  if(root){
    feildId.value = null;
    goalId.value = null;
  }
  assessmentsReqest.fetchAll({
    q: search,
    parent_id: parentId,
    category: parentId == null ? 'independent' : null,
    load_goals: feildId.value != null && feildId.value != '' ? 'load' : ''
  }).then(response => {
    if(parentId == null){
      rootItems.value = response.data.data;
    } else if(feildId.value == null){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('please_choose_subfeild'), 'info');
      feildItems.value = response.data.data;
      goalItems.value = [];
      feildId.value = null;
    } else {
      goalItems.value = response.data.data;
    }
    loading.value.assessment = false;
  });
}

const AddGaol = (without) => {
  window.scrollTo({
    top: 0,
  });
  is_without.value = without;
  isDialogVisibleAddGoal.value = true;
  editBehavioralGoal.value = '';
  editFirstFeild.value = '';
  idGoal.value = '-1';
  goalId.value = null;
  isAddDialogVisible.value = true;
  if(!without){
    rootId.value = null;
    editBehavioralGoal.value = null;
    fetchAssessments(null, true)
  }
}

const saveAddWithout = () => {

  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){
      
      let title = goalItems.value.filter((i) => i.id == goalId.value)[0]?.title;

      try {
        let caseName = casesItem.value.filter((i) => i.id == selectedCase.value)[0].name;
        const words = title.split(' ');
        const firstWord = words[0];
        const prefix = ` أن ${firstWord} ${caseName.split(' ')[0]} `;
        const prefixEn = ` ${caseName} Does `; 
        const arabicScriptRegex = /[\u0600-\u06FF\u0750-\u077F]/;
        if (arabicScriptRegex.test(title)) {
            words.shift();
            title = words.join(' ');
            const match = title.match(/^(\d+\.\s+)/);
            if (match) {
                const numberAndDot = match[1];
                title = title.replace(numberAndDot, numberAndDot + prefix);
            } else {
                title = prefix + title;
            }
        }

      } catch (error) {
      }

      goalsReqest.put({
        title: editBehavioralGoal.value ?? title,
        case_id: selectedCase.value,
        custom_first_feild: editFirstFeild.value,
        term_id: selectedTerm.value,
        assessment_id: goalId.value,
        category: 'independent',
      }, idGoal.value).then(() =>{
        idGoal.value = '';
        isDialogVisibleAddGoal.value = false;
        idGoal.value = null;
        fetchGoals()
      }).catch((e)=> {
        if(e.response.status == 406){
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t('the_goal_already_exists'), 'error');
        }
      })
    }
  })
}

const printPlans = (pdf_type) => {
  if(selectedTerm.value != null && selectedTerm.value != '' && selectedCase.value != null & selectedCase.value != ''){
    if(pdf_type == 'independent_case_planning'){
      loading.value.plan_pdf = true;
    }
    goalsReqest.fetchAll({
      q: searchQuery.value,
      status: selectedStatus.value,
      category: 'independent',
      pdf: pdf_type,
      case_id: selectedCase.value,
      period: 'null',
      term_id: selectedTerm.value,
      feild_id: selectedFeild.value,
      date_from: selectedFrom.value,
      date_to: selectedTo.value,
      sessions_count_min: sessionsCountMin.value,
      sessions_count_max: sessionsCountMax.value,
      options: options.value,
      page: options.value.page,

    }).then(response => {
      loading.value.plan_pdf = false;
      window.open(response.data.data.url, '_blank');
    }).catch(error => {
      loading.value.plan_pdf = false;
      console.error(error)
    })
  }
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

  let valueMethod = translated.find(item => item.value === String(value))
  if(valueMethod)
    return valueMethod.title

  return '';
}

const transferGoalDialog = id => {
  termsReqest.items({except: [selectedTerm.value]}).then(response => {
    transferTerms.value = response.data.data
  });
  window.scrollTo({
    top: 0,
  });
  dialogTransferGoalID.value = id;
  transferToTerm.value = '';
  isDialogTransferGoalVisible.value = true;
}

const transferGoal = () => {

  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){

      goalsReqest.transferGoal({
        term_id: transferToTerm.value
      }, dialogTransferGoalID.value).then(response =>{
        isDialogTransferGoalVisible.value = false;
        dialogTransferGoalID.value = null;
        if(response.data.status == true){
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t('goals.copied_successfully'), 'success');
          fetchGoals()
        }
      })
    }
  })
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
              <VCol
                v-if="!returnIdUserIfNotAdmin() && !canDoes('parent')"
                cols="12"
                sm="4"
              > 
                <AppAutocomplete
                    v-model="teacher"
                    :server-search="searchTeachers"
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
                :placeholder="$t('Type Case Name')"
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
                v-if="!canDoes('parent')"
                cols="12"
                sm="4"
              >
                <VLabel class="mb-1">{{ $t('date') }}</VLabel>
                <VRow>
                  <VCol cols="6">
                    <AppDateTimePicker
                      v-model="selectedFrom"
                      clearable
                      clear-icon="tabler-x"
                      :placeholder="$t('from')"
                    />
                  </VCol>
                  <VCol cols="6">
                    <AppDateTimePicker
                      v-model="selectedTo"
                      clearable
                      clear-icon="tabler-x"
                      :placeholder="$t('to')"
                    />
                  </VCol>
                </VRow>
              </VCol>
              <VCol
                v-if="!canDoes('parent')"
                cols="12"
                sm="4"
              >
                <VLabel class="mb-1">{{ $t('goals.sessions_count') }}</VLabel>
                <VRow>
                  <VCol cols="6">
                    <AppTextField
                      v-model="sessionsCountMin"
                      type="number"
                      min="0"
                      clearable
                      clear-icon="tabler-x"
                      :placeholder="$t('from')"
                    />
                  </VCol>
                  <VCol cols="6">
                    <AppTextField
                      v-model="sessionsCountMax"
                      type="number"
                      min="0"
                      clearable
                      clear-icon="tabler-x"
                      :placeholder="$t('to')"
                    />
                  </VCol>
                </VRow>
              </VCol>
              <VCol
                v-if="!canDoes('parent')"
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedStatus"
                  :label="$t('Active')"
                  :items="statusItems()"
                  clearable
                  clear-icon="tabler-x"
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
              <div style="inline-size: 12rem;">
                <AppTextField
                  v-model="searchQuery"
                  :placeholder="$t('Search')"
                  density="compact"
                />
              </div>

              <!-- 👉 Add user button -->
              <VBtn v-if="can('edit_independent-goals','edit_independent-goals')" color="success" @click="printPlans('independent_case_planning')" :loading="loading.plan_pdf" :disabled="selectedTerm == '' || selectedTerm == null">
                {{ $t('goals.print_plans') }}
              </VBtn>
              <VMenu v-if="can('edit_independent-goals','edit_independent-goals')">
                <template #activator="{ props }">
                  <VBtn
                    :disabled="selectedTerm == '' || selectedTerm == null"
                    v-bind="props"
                  >
                    {{ $t('goals.add_goal') }}
                  </VBtn>
                </template>

                <VList>
                  <VListItem @click="AddGaol(false)">{{  $t('with_feild') }}</VListItem>
                  <VListItem @click="AddGaol(true)">{{  $t('without_feild') }}</VListItem>
                </VList>
              </VMenu>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="users"
            :loading="loading.goals"
            :items-length="totalGoals"
            :headers="translatedHeaders()"
            class="dataTable"
            @update:options="onTableOptions"
          >
            <template #item.case="{ item }">
              <div class="align-center">
                {{ item.raw.case?.name }}
              </div>
            </template>

            <template #item.assessment="{ item }">
              <div class="align-center">
                {{getEvaluation(item.raw.value, item.raw.assessment_evaluation_method?.items)}}
              </div>
            </template>
            
            <template #item.first_feild="{ item }">
              <div class="align-center">
                {{item.raw.assessment_first_feild?.title}}
              </div>
            </template>

            <template #item.general_goal="{ item }">
              <div class="align-center">
                {{item.raw.assessment_parent?.title}}
              </div>
            </template>

            <template #item.behavioral_goal="{ item }">
              <div class="align-center">
                <RouterLink
                  target="_blank"
                  :to="{ name: 'independent_goals-sessions-case-term-goal', params: { case: selectedCase, term: selectedTerm, goal: item.raw.id } }"
                  class="font-weight-medium user-list-name"
                >
                  <span>{{ item.raw.title }}</span> <span v-if="item.raw.standard">{{ $t(item.raw.standard) }}</span> <span v-if="item.raw.generalization">{{ $t(item.raw.generalization) }}</span>
                </RouterLink>
              </div>
            </template>

            <template #item.period="{ item }">
              <div class="align-center" v-if="item.raw.date_from">
                  
                {{ $t("goals.periodTo", {from: String(item.raw.date_from).split("-").reverse().join("-"), to : String(item.raw.date_to)}) }}              </div>
            </template>

            <template #item.sessions_count="{ item }">
              <div class="align-center">
                {{ item.raw.sessions_count ?? 0 }}
              </div>
            </template>

            <template #item.status="{ item }">
              <div class="d-flex gap-3">
                <div class="align-center mb-1" v-if="item.raw.started_session && item.raw.ended_session">
                  <VChip
                    v-if="!item.raw.value"
                    size="small"
                    label
                    class="text-capitalize"
                  >
                    {{ $t("didnt start") }}
                  </VChip>
                  <VChip
                    v-else-if="(!item.raw.assesment_evaluation_power && item.raw.value == 1) || (item.raw.assesment_evaluation_power && (item.raw.value == item.raw.assesment_evaluation_power))"
                    size="small"
                    label
                    color="success"
                    class="text-capitalize"
                  >
                    {{ $t("goals.finished") }}
                  </VChip>
                  <VChip
                    v-else-if="item.raw.value && !item.raw.late"
                    size="small"
                    label
                    color="warning"
                    class="text-capitalize"
                  >
                    {{ $t("goals.started") }}
                  </VChip>
                  <VChip
                    v-else-if="item.raw.value && item.raw.late"
                    size="small"
                    label
                    color="error"
                    class="text-capitalize"
                  >
                    {{ $t("goals.late") }}
                  </VChip>
                </div>
                <div class="align-center mb-1" v-else>
                  <VChip
                    v-if="!item.raw.value"
                    size="small"
                    label
                    class="text-capitalize"
                  >
                    {{ $t("didnt start") }}
                  </VChip>
                  <VChip
                    v-else-if="(!item.raw.assesment_evaluation_power && item.raw.value == 1) || (item.raw.assesment_evaluation_power && (item.raw.value == item.raw.assesment_evaluation_power))"
                    size="small"
                    label
                    color="success"
                    class="text-capitalize"
                  >
                    {{ $t("goals.finished") }}
                  </VChip>
                  <VChip
                    v-else-if="item.raw.value"
                    size="small"
                    label
                    color="warning"
                    class="text-capitalize"
                  >
                    {{ $t("goals.started") }}
                  </VChip>
                </div>
                <div v-if="item.raw.deleted_at">
                  <VChip
                    :color="resolveUserStatusVariant(item.raw.deleted_at? 'inactive' : 'active' )"
                    size="small"
                    label
                    class="text-capitalize"
                  >
                    {{ item.raw.deleted_at? $t('Inactive') :$t('Active_user') }}
                  </VChip>
                </div>
              </div>
            </template>

            <!-- Actions -->
            <template #item.actions="{ item }">

              <IconBtn 
                v-if="can('access_independent-goals','access_independent-goals')" 
                :title="$t('View')" 
                @click="router.push({ name: 'independent_goals-sessions-case-term-goal', params: { case: selectedCase, term: selectedTerm, goal: item.raw.id } });"
              >
                <VIcon icon="tabler-eye" />
              </IconBtn>

              <IconBtn v-if="can('edit_independent-goals','edit_independent-goals')" :title="$t('Edit')" @click="editGoal(item.raw)">
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
                      v-if="!item.raw.deleted_at && can('admin_independent-goals','admin_independent-goals')" 
                      :title="$t('delete')" 
                      @click="deleteUser(item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-trash" />
                      </template>
                      <VListItemTitle>{{ $t('delete_user') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="item.raw.deleted_at && can('admin_independent-goals','admin_independent-goals')" 
                      :title="$t('restore')" 
                      @click="restoreUser(item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-refresh" />
                      </template>
                      <VListItemTitle>{{ $t('restore_user') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="can('edit_independent-goals','edit_independent-goals')"
                      @click="transferGoalDialog(item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-arrow-bear-right" />
                      </template>
                      <VListItemTitle>{{ $t('goals.transfer_to_another_term') }}</VListItemTitle>
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
                  :disabled="loading.goals"
                  :length="Math.ceil(totalGoals / options.itemsPerPage)"
                  :total-visible="5"
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
      </VCol>
    </VRow>

  <VDialog
    v-model="isDialogVisible"
    :close-on-back="false"
    persistent
    width="500"
  >
    <VForm 
      ref="refVForm"
      @submit.prevent="saveGoal"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isDialogVisible = !isDialogVisible" />

      <!-- Dialog Content -->
      <VCard :title="isAddDialogVisible ? $t('goals.add_goal') : $t('goals.edit_goal')">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
            >
              <AppTextField v-model="editBehavioralGoal" :label="$t('goals.skill')" />
            </VCol>
            <VCol
              cols="12"
            >
              <AppTextarea 
                v-model="editFirstFeild" 
                :label="$t('goals.reinforcement')" 
                :rules="[requiredValidator]"
              />
            </VCol>
          </VRow>

          <VLabel class="mb-1">{{ $t('date') }}</VLabel>
          <VRow>
            <VCol cols="6">
              <AppDateTimePicker
                v-model="editFromDate"
                clearable
                clear-icon="tabler-x"
                :placeholder="$t('from')"
              />
            </VCol>
            <VCol cols="6">
              <AppDateTimePicker
                v-model="editToDate"
                clearable
                clear-icon="tabler-x"
                :placeholder="$t('to')"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardText class="d-flex justify-end">
          <VBtn type="submit">
            {{  $t('Save') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VForm>
  </VDialog>
  <VDialog
    v-model="isDialogVisibleAddGoal"
    :close-on-back="false"
    persistent
    width="800"
  >
    <VForm 
      ref="refVForm"
      @submit.prevent="saveAddWithout"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isDialogVisibleAddGoal = !isDialogVisibleAddGoal" />

      <!-- Dialog Content -->
      <VCard :title="isAddDialogVisible ? $t('goals.add_goal') : $t('goals.edit_goal')">
        <VCardText v-if="is_without">
          <VRow>
            <VCol
              cols="12"
              sm="12"
            >
              <AppTextField 
                v-model="editBehavioralGoal" 
                :label="$t('goals.skill')" 
                :rules="[requiredValidator]"
              />
            </VCol>
            <VCol
              cols="12"
              sm="12"
            >
              <AppTextarea 
                v-model="editFirstFeild" 
                :label="$t('goals.reinforcement')" 
                :rules="[requiredValidator]"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardText v-if="!is_without">
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppAutocomplete
                v-model="rootId"
                :label="$t('scale')"
                :loading="loading.assessment"
                :items="rootItems"
                :item-title="'title'"
                :item-value="'id'"
                @update:modelValue="fetchAssessments($event, true)"
                :rules="[requiredValidator]"
              >
              <template v-slot:selection="{ item }">
                <div class="selected-item">
                  {{ item.title }}
                </div>
              </template>
              </AppAutocomplete>
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppAutocomplete
                v-model="feildId"
                :label="$t('caseField')"
                :loading="loading.assessment"
                :items="feildItems"
                :disabled="feildItems == [] || feildItems == null || feildItems == ''"
                :item-title="'title'"
                :item-value="'id'"
                @update:modelValue="fetchAssessments($event)"
                :rules="[requiredValidator]"
              >
              </AppAutocomplete>
            </VCol>
            <VCol
              cols="12"
              sm="12"
            >
              <AppAutocomplete
                v-model="goalId"
                v-model:search="searchGoals"
                @input="fetchAssessments(feildId, false, searchGoals)"
                :label="$t('goals.goal')"
                :loading="loading.assessment"
                :items="goalItems"
                :disabled="feildItems == [] || feildItems == null || feildItems == ''"
                :item-title="'title'"
                :item-value="'id'"
                :rules="[requiredValidator]"
              >
              </AppAutocomplete>
            </VCol>
          </VRow>
        </VCardText>
        <VCardText class="d-flex justify-end">
          <VBtn type="submit">
            {{  $t('Save') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VForm>
  </VDialog>

  <VDialog
    v-model="isDialogTransferGoalVisible"
    :close-on-back="false"
    persistent
    width="500"
  >
    <VForm 
      ref="refVForm"
      @submit.prevent="transferGoal"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isDialogTransferGoalVisible = !isDialogTransferGoalVisible" />

      <!-- Dialog Content -->
      <VCard :title="$t('goals.transfer_to_another_term')">
        <VCardText>
          <VRow class="mt-3 mb-2">
            <VCol cols="12">
              <AppSelect
                v-model="transferToTerm"
                :items="transferTerms"
                clearable
                clear-icon="tabler-x"
                :label="$t('Term')"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardText class="d-flex justify-end">
          <VBtn type="submit">
            {{  $t('Save') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VForm>
  </VDialog>
  <SnackbarComponent ref="snackbarRef"></SnackbarComponent>
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
    action: access_independent-goals
    subject: access_independent-goals
</route>