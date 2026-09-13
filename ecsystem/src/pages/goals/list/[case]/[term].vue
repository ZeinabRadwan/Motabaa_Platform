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
const is_without = ref(false)
const search = ref()
const searchGoals = ref()
const casesItem = ref([]);
const loadingItem = ref(false);
const selectedCase = ref(null);
const itemsTerm = ref([]);
const selectedTerm = ref(null);
const selectedFrom = ref(null);
const selectedTo = ref(null);
const editGeneralGoal = ref(null);
const editFirstFeild = ref(null);
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


watch(editToDate, query => {
  const from = new Date(editFromDate.value);
  const to = new Date(editToDate.value);
  if (from > to) {
    editToDate.value = '';
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('cant_choose_date_before'), 'warning');
  } 
})

// 👉 Fetching users
const fetchGoals = () => {
  loading.value.goals = true
  goalsReqest.fetchAll({
    q: searchQuery.value,
    status: selectedStatus.value,
    category: 'educational',
    case_id: route.params.case,
    term_id: route.params.term,
    feild_id: selectedFeild.value,
    date_from: selectedFrom.value,
    date_to: selectedTo.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    users.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalGoals.value = response.data.total
    options.value.page = response.data.currentPage
    loading.value.goals = false
  }).catch(error => {
    console.error(error)
  })
}

const fetchFeilds = () => {
    loading.value.feilds = true;
    goalsReqest.fetchFilterItems({
      case_id: route.params.case,
      term_id: route.params.term,
      category: 'educational',
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

const fetchTerm = () => {
  termsReqest.fetchTerm(route.params.term).then(response => {
    selectedTerm.value = response.data.data
  }).catch(error => {
    console.error(error)
  })
}

const fetchCase = () => {
  loading.value.cases = true;
  casesReqest.fetchCase(route.params.case).then(response => {
    loading.value.cases = false;
    selectedCase.value = response.data.data
    casesItem.value = [{ id: response.data.data.id, name: response.data.data.name }]
    fetchGoals()
    fetchFeilds()
  }).catch(error => {
    console.error(error)
  })
};

fetchCase()
fetchTerm()
const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchGoals, {
  search: searchQuery,
  filters: () => [
    selectedStatus.value,
    selectedFeild.value,
    selectedFrom.value,
    selectedTo.value,
  ],
  options,
})

const translatedHeaders = () => {
  let headers = [
    {
      title: 'behavioral_goal',
      key: 'behavioral_goal',
      width: '20%',
      sortable: false,
    },
    {
      title: 'caseField',
      key: 'first_feild',
      width: '15%',
      sortable: false,
    },
    {
      title: 'general_goal',
      key: 'general_goal',
      width: '15%',
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

  if(canDoes('parent')) {
    delete headers[5]
    delete headers[6]
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
      custom_general_goal: editGeneralGoal.value,
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
    category: parentId == null ? 'educational' : null,
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
  editGeneralGoal.value = '';
  idGoal.value = '-1';
  goalId.value = null;
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
        let caseName = selectedCase.name;
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
        case_id: route.params.case,
        custom_first_feild: editFirstFeild.value,
        custom_general_goal: editGeneralGoal.value,
        term_id: route.params.term,
        assessment_id: goalId.value,
        category: 'educational',
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
  if(pdf_type == 'case_planning'){
    loading.value.plan_pdf = true;
  }
  goalsReqest.fetchAll({
    q: searchQuery.value,
    status: selectedStatus.value,
    category: 'educational',
    pdf: pdf_type,
    case_id: route.params.case,
    period: 'null',
    term_id: route.params.term,
    feild_id: selectedFeild.value,
    date_from: selectedFrom.value,
    date_to: selectedTo.value,
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
   title = translated.find(item => item.value === value).title

  return title;
}

const transferGoalDialog = id => {
  termsReqest.items({except: [selectedTerm.value.id]}).then(response => {
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
              cols="12"
              sm="4"
            >
              <AppAutocomplete
                v-model="selectedCase"
                v-model:search="search"
                :label="$t('Cases')"
                :item-title="'name'"
                :item-value="'id'"
                :loading="loading.cases"
                :placeholder="$t('Type Case Name')"
                :items="casesItem"
                :disabled="true"
                clear-icon="tabler-x"
                clearable
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
                :disabled="true"
                clear-icon="tabler-x"
                clearable
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
            <VBtn v-if="can('edit_education-goals','edit_education-goals')" color="success" @click="printPlans('case_planning')" :loading="loading.plan_pdf">
              {{ $t('goals.print_plans') }}
            </VBtn>
            <VMenu v-if="can('edit_education-goals','edit_education-goals')">
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                >
                  {{ $t('goals.add_behavioral_goal') }}
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
                :to="{ name: 'goals-sessions-case-term-goal', params: { case: route.params.case, term: route.params.term, goal: item.raw.id } }"
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
              v-if="can('access_education-goals','access_education-goals')" 
              :title="$t('View')" 
              @click="router.push({ name: 'goals-sessions-case-term-goal', params: { case: route.params.case, term: route.params.term, goal: item.raw.id } });"
            >
              <VIcon icon="tabler-eye" />
            </IconBtn>
            
            <IconBtn v-if="can('edit_education-goals','edit_education-goals')" :title="$t('Edit')" @click="editGoal(item.raw)">
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
                    v-if="!item.raw.deleted_at && can('admin_education-goals','admin_education-goals')" 
                    :title="$t('delete')" 
                    @click="deleteUser(item.raw.id)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-trash" />
                    </template>
                    <VListItemTitle>{{ $t('delete_user') }}</VListItemTitle>
                  </VListItem>

                  <VListItem 
                    v-if="item.raw.deleted_at && can('admin_education-goals','admin_education-goals')" 
                    :title="$t('restore')" 
                    @click="restoreUser(item.raw.id)"
                  >
                    <template #prepend>
                      <VIcon icon="tabler-refresh" />
                    </template>
                    <VListItemTitle>{{ $t('restore_user') }}</VListItemTitle>
                  </VListItem>

                  <VListItem 
                    v-if="can('edit_education-goals','edit_education-goals')"
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
      <VCard :title="$t('Edit Goal')">
        <VCardText>
          <VRow>
            <VCol
              v-if="canEditCustom"
              cols="12"
              sm="6"
            >
              <AppTextField v-model="editGeneralGoal" :label="$t('general_goal')" />
            </VCol>
            <VCol
              v-if="canEditCustom"
              cols="12"
              sm="6"
            >
              <AppTextField v-model="editFirstFeild" :label="$t('caseField')" />
            </VCol>
          </VRow>
          <AppTextarea 
            v-model="editBehavioralGoal" 
            :label="$t('behavioral_goal')" 
            :rules="[requiredValidator]"
          />
          <VRow class="mt-3 mb-2">
            <VCol cols="6">
              <AppSelect
                v-model="editStandard"
                :items="goalsStandardItems()"
                clearable
                clear-icon="tabler-x"
                :label="$t('goals.Standard')"
              />
            </VCol>
            <VCol cols="6">
              <AppSelect
                :items="goalsGeneralizationItems()"
                v-model="editGeneralization"
                clearable
                clear-icon="tabler-x"
                :label="$t('goals.Generalization')"
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
      <VCard :title="$t('Add Goal')">
        <VCardText v-if="is_without">
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField 
                v-model="editGeneralGoal" 
                :label="$t('general_goal')" 
                :rules="[requiredValidator]"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField 
                v-model="editFirstFeild" 
                :label="$t('caseField')" 
                :rules="[requiredValidator]"
              />
            </VCol>
            <VCol
              cols="12"
              sm="12"
            >
              <AppTextarea 
                v-model="editBehavioralGoal" 
                :label="$t('behavioral_goal')" 
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
    action: access_education-goals
    subject: access_education-goals
</route>