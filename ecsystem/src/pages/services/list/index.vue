<script setup>
import { applyServerTableOptions, fetchAllPages, watchServerTableFetch } from '@core/utils/tableFetch'
import { paginationMeta } from '@/@fake-db/utils'
import i18n from '@/plugins/i18n/index.js'
import { useRoute, useRouter } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { can, canDoes } from '@layouts/plugins/casl'
import {returnIdUserIfNotAdmin} from "@core/utils/helper";
import {assessmentsApi} from "@/plugins/apis/assessmentReqest"
import {
  scaleTypeFilterItems,
  termItems
} from '@core/utils/generalItems';
import {
requiredValidator
} from '@validators';
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import {goalsApi} from "@/plugins/apis/goalsReqest"
import {termsApi} from "@/plugins/apis/termsRequest"
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import { isParentUser } from '@core/utils/staffSessionVisibility'
import { useParentBeneficiary } from '@/composables/useParentBeneficiary'
import { casesApi } from '@/plugins/apis/casesReqest'
import ParentBeneficiaryPicker from '@/views/parent/ParentBeneficiaryPicker.vue'
import ParentGoalTitleLink from '@/components/parent/ParentGoalTitleLink.vue'
import ParentGoalSearchField from '@/components/parent/ParentGoalSearchField.vue'
import { parentServiceTypeOptions, uniqueOptionsByValue } from '@/utils/parentFilterOptions'
import { filterByAnyWord, goalTitleTexts } from '@/utils/parentGoalSearch'

const isParent = isParentUser()

const route = useRoute()
const router = useRouter()

// Filters carried back from the parent goal details view; applied once their options load.
const pendingParentFilters = {
  serviceType: isParent ? (route.query.service_type ?? null) : null,
  feild: isParent ? (route.query.feild ?? null) : null,
}
const refVForm = ref()
const userListStore = useUserListStore()
const assessmentsReqest = assessmentsApi()
const goalsReqest = goalsApi()
const termsReqest = termsApi()

const snackbarRef = ref(null)
const searchQuery = ref('')
const selectedFeild = ref(null)
const selectedScaleType = ref(null)
const itemsFeild = ref([])
const selectedStatus = ref('active')
const totalPage = ref(1)
const totalGoals = ref(0)
const goals = ref([])

// Parents search the loaded goals of the selected service on the page, by any word of the visible columns.
const parentSearch = ref('')

const displayedGoals = computed(() => (isParent
  ? filterByAnyWord(goals.value, parentSearch.value, goal => [
    ...goalTitleTexts(goal, i18n.global.t),
    goal.assessment_first_feild?.title,
  ])
  : goals.value))

const searchGoals = ref()
const isDialogVisible = ref(false)
const search = ref()
const isDialogVisibleAddGoal = ref(false)
const is_without = ref(false)
const casesItem = ref([]);
const loadingItem = ref(false);
const selectedCase = ref(null);
const selectedTerm = ref(null);
const itemsTerm = ref(null);
const selectedFrom = ref(null);
const selectedTo = ref(null);
const sessionsCountMin = ref(null);
const sessionsCountMax = ref(null);
const editGeneralGoal = ref(null);
const editFirstFeild = ref(null);
const specialistItems = ref([])
const specialist = ref(returnIdUserIfNotAdmin() ?? null)
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

const parentBeneficiary = ref(null)
const parentServiceTypeItems = ref([])
const loadingParentServiceTypes = ref(false)

const parentServiceTypeSelectItems = computed(() =>
  parentServiceTypeOptions(parentServiceTypeItems.value, i18n.global.t, i18n.global.te))

const { selectBeneficiary, selectedBeneficiaryId, selectedBeneficiary, resolveBeneficiarySelection } = useParentBeneficiary()
const casesReqest = casesApi()
const parentContextReady = ref(false)
const parentNeedsPick = computed(() => isParent && parentContextReady.value && !selectedCase.value)

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})


const applyParentCaseFromContext = item => {
  if (!item?.id)
    return

  onParentBeneficiarySelect(item)
}

onMounted(async () => {
  if (isParent) {
    options.value.itemsPerPage = 100

    const routeCaseId = route.query.case
    const { selected } = await resolveBeneficiarySelection({ routeCaseId })
    parentContextReady.value = true

    if (selected)
      applyParentCaseFromContext(selected)
    else if (route.query.term)
      selectedTerm.value = Number(route.query.term)

    return
  }

  termItems().then(data => {
    itemsTerm.value = data
  })
  fetchFeilds()
})

watch(selectedBeneficiaryId, (id, previous) => {
  if (!isParent || !parentContextReady.value || id == null || id === previous)
    return

  if (selectedBeneficiary.value && Number(selectedBeneficiary.value.id) === Number(id)) {
    applyParentCaseFromContext(selectedBeneficiary.value)

    return
  }

  applyParentCaseFromContext({ id })
})


watch(editToDate, query => {
  const from = new Date(editFromDate.value);
  const to = new Date(editToDate.value);
  if (from > to) {
    editToDate.value = '';
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('cant_choose_date_before'), 'warning');
  } 
})

watch(specialist, query => {
  casesItem.value = []
  selectedCase.value = null
})

const casesFilterKey = computed(() => [
  specialist.value,
  selectedScaleType.value,
  selectedTerm.value,
].join('|'))

watch(selectedScaleType, () => {
  if (isParent) {
    selectedFeild.value = null
    if (selectedScaleType.value && selectedCase.value)
      fetchFeilds()
    else
      itemsFeild.value = []

    return
  }

  fetchFeilds()
  selectedCase.value = null
})

watch(selectedTerm, () => {
  selectedCase.value = null
})



const onParentBeneficiarySelect = item => {
  if (item?.id)
    selectBeneficiary(item)

  parentBeneficiary.value = selectedBeneficiary.value || item
  selectedCase.value = item.id
  selectedScaleType.value = null
  selectedFeild.value = null
  itemsFeild.value = []
  parentServiceTypeItems.value = []
  goals.value = []
  totalGoals.value = 0
  parentSearch.value = ''
  fetchParentServiceTypes()
}

const clearParentBeneficiary = () => {
  parentBeneficiary.value = null
  selectedCase.value = null
  selectedScaleType.value = null
  selectedFeild.value = null
  itemsFeild.value = []
  parentServiceTypeItems.value = []
  goals.value = []
  totalGoals.value = 0
  parentSearch.value = ''
}

const fetchParentServiceTypes = () => {
  if (!isParent || !selectedCase.value)
    return

  loadingParentServiceTypes.value = true

  const caseId = selectedCase.value

  goalsReqest.fetchParentTreatmentCategories({ case_id: caseId }).then(response => {
    if (caseId !== selectedCase.value)
      return

    parentServiceTypeItems.value = uniqueOptionsByValue(response.data?.data)
    const wanted = pendingParentFilters.serviceType
    pendingParentFilters.serviceType = null
    const restored = wanted != null
      ? parentServiceTypeItems.value.find(item => String(item.value) === String(wanted))
      : null
    if (restored)
      selectedScaleType.value = restored.value
    else {
      pendingParentFilters.feild = null
      if (parentServiceTypeItems.value.length === 1)
        selectedScaleType.value = parentServiceTypeItems.value[0].value
    }
  }).catch(() => {
    if (caseId === selectedCase.value)
      parentServiceTypeItems.value = []
  }).finally(() => {
    if (caseId === selectedCase.value)
      loadingParentServiceTypes.value = false
  })
}

const parentGoalDetailsLocation = goal => {
  if (!selectedCase.value || !can('access_treatment-goals', 'access_treatment-goals'))
    return null

  const query = {}
  const term = route.query.term ?? selectedTerm.value
  if (term != null && term !== '')
    query.term = String(term)
  if (selectedScaleType.value)
    query.service_type = String(selectedScaleType.value)
  if (selectedFeild.value != null && selectedFeild.value !== '')
    query.feild = String(selectedFeild.value)

  return {
    name: 'services-details-case-goal',
    params: { case: selectedCase.value, goal: goal.id },
    query,
  }
}

// 👉 Fetching users
const fetchGoals = () => {
  if (isParent && (!selectedCase.value || !selectedScaleType.value)) {
    goals.value = []
    totalGoals.value = 0
    loading.value.goals = false

    return
  }

  loading.value.goals = true

  const listParams = {
    case_id: selectedCase.value,
    category: selectedScaleType.value,
    feild_id: selectedFeild.value,
    options: options.value,
    page: options.value.page,
  }

  // Parents get the whole list (no pagination controls) so the on-page search covers every goal.
  const request = isParent
    ? fetchAllPages(page => goalsReqest.fetchAll({ ...listParams, page, options: { ...options.value, page } }))
    : goalsReqest.fetchAll({
      q: searchQuery.value,
      status: selectedStatus.value,
      specialist_id: specialist.value,
      term_id: selectedTerm.value,
      date_from: selectedFrom.value,
      date_to: selectedTo.value,
      sessions_count_min: sessionsCountMin.value,
      sessions_count_max: sessionsCountMax.value,
      ...listParams,
    })

  request.then(response => {
    if (isStaleParentList(listParams))
      return

    goals.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalGoals.value = response.data.total
    options.value.page = response.data.currentPage
    loading.value.goals = false
  }).catch(error => {
    if (isStaleParentList(listParams))
      return

    loading.value.goals = false
    console.error(error)
  })
}

// A response for a previously selected child or service type must not overwrite the current list.
const isStaleParentList = params => isParent
  && (params.case_id !== selectedCase.value || params.category !== selectedScaleType.value)

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchGoals, {
  search: isParent ? null : searchQuery,
  filters: () => isParent
    ? [selectedCase.value, selectedFeild.value, selectedScaleType.value]
    : [
      specialist.value,
      selectedCase.value,
      selectedStatus.value,
      selectedTerm.value,
      selectedFeild.value,
      selectedScaleType.value,
      selectedFrom.value,
      selectedTo.value,
      sessionsCountMin.value,
      sessionsCountMax.value,
    ],
  options,
})


const fetchFeilds = () => {
    if (isParent && (!selectedCase.value || !selectedScaleType.value)) {
      itemsFeild.value = []

      return
    }

    loading.value.feilds = true;
    goalsReqest.fetchFilterItems({
      category: selectedScaleType.value,
      case_id: isParent ? selectedCase.value : undefined,
    }).then(response => {
        loading.value.feilds = false;
        itemsFeild.value = isParent ? uniqueOptionsByValue(response.data.data) : (response.data.data ?? []);
        if (isParent && pendingParentFilters.feild != null) {
          const wantedFeild = pendingParentFilters.feild
          pendingParentFilters.feild = null
          const match = itemsFeild.value.find(item => String(item.value) === String(wantedFeild))
          if (match)
            selectedFeild.value = match.value
        }
        if (!isParent) {
          itemsFeild.value.push({
            value: 'without_feild',
            title: i18n.global.t("without_feild"),
          })
        }
    }).catch(error => {
        loading.value.feilds = false;
        console.error(error)
    })
};

const searchSpecialists = params => userListStore.searchItems({
  ...params,
  roles: ['physiotherapist_specialist', 'occupational_specialist', 'psychotherapist_specialist', 'pronunciation_speech_specialist'],
})

const searchCases = params => goalsReqest.selectCaseItems({
  ...params,
  specialist_id: specialist.value,
  category: selectedScaleType.value,
  term_id: selectedTerm.value,
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
      title: 'goals.goal',
      key: 'first_feild',
      width: '20%',
      sortable: false,
    },
    // {
    //   title: 'general_goal',
    //   key: 'general_goal',
    //   sortable: false,
    // },
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

  if (isParent) {
    headers = headers.filter(header => ['behavioral_goal', 'first_feild'].includes(header.key))
  }
  else if (canDoes('parent')) {
    headers = headers.filter(header => !['sessions_count', 'status', 'assessment', 'actions'].includes(header.key))
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
  editToDate.value = goal.date_to
  editFirstFeild.value = goal.custom_first_feild;
  editGeneralGoal.value = goal.custom_general_goal;
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
      date_from: editFromDate.value,
      custom_first_feild: editFirstFeild.value,
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
  if(root){
    feildId.value = null;
    goalId.value = null;
  }
  loading.value.assessment = true;
  assessmentsReqest.fetchAll({
    q: search,
    parent_id: parentId,
    category: parentId == null ? selectedScaleType.value : null,
    load_goals: feildId.value != null && feildId.value != '' ? 'load' : ''
  }).then(response => {
    if(parentId == null){
      rootItems.value = response.data.data;
    } else if(root){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('please_choose_subfeild'), 'info');
      feildItems.value = response.data.data;
      goalItems.value = [];
      feildId.value = null;
    } else if(feildId.value == null){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('please_choose_subfeild'), 'info');
      feildItems.value = response.data.data;
      goalItems.value = [];
      feildId.value = null;
    } else {
      goalItems.value = response.data.data;
    }
    loading.value.assessment = false;
  }).catch(() => {
    loading.value.assessment = false
  });
}

const hasSelectedScale = computed(() => rootId.value != null && rootId.value !== '')
const hasSelectedField = computed(() => feildId.value != null && feildId.value !== '')

const onScaleSelected = id => {
  if (id == null || id === '') {
    feildId.value = null
    goalId.value = null
    feildItems.value = []
    goalItems.value = []
    return
  }

  fetchAssessments(id, true)
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

      goalsReqest.put({
        title: editBehavioralGoal.value ?? title,
        case_id: selectedCase.value,
        term_id: selectedTerm.value,
        custom_first_feild: editFirstFeild.value,
        assessment_id: goalId.value,
        category: selectedScaleType.value,
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

const printPlans = (pdf_type, period = null) => {
  if(selectedTerm.value != null && selectedTerm.value != '' && selectedCase.value != null & selectedCase.value != ''){
    if(pdf_type == 'services_case_planning'){
      loading.value.plan_pdf = true;
    }
    goalsReqest.fetchAll({
      q: searchQuery.value,
      status: selectedStatus.value,
      category: selectedScaleType.value,
      pdf: pdf_type,
      case_id: selectedCase.value,
      period: period ? period : 'null',
      teacher_or_specialist_id: specialist.value,
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
      loading.value.period_pdf = false;
      window.open(response.data.data.url, '_blank');
    }).catch(error => {
      loading.value.plan_pdf = false;
      loading.value.period_pdf = false;
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

  let title = ''
  if(value)
   title = translated.find(item => item.value === value).title

  return title;
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
    <ParentBeneficiaryPicker
      v-if="parentNeedsPick"
      title-i18n-key="parent_treatment_plans.beneficiaries_title"
      hint-i18n-key="parent_treatment_plans.beneficiaries_hint"
      aria-i18n-key="parent_treatment_plans.beneficiaries_aria"
      empty-i18n-key="parent_treatment_plans.beneficiaries_empty"
      error-i18n-key="dashboard.parent_beneficiaries_error"
      :auto-select-single="false"
      @select="onParentBeneficiarySelect"
    />
    <div
      v-else-if="isParent && !parentContextReady"
      class="pa-6 d-flex justify-center"
    >
      <VProgressCircular
        indeterminate
        color="primary"
      />
    </div>
    <VRow v-else-if="!isParent || selectedCase">
      <VCol cols="12">
        <VCard>
          <!-- 👉 Filters -->
          <VCardText>
            <VRow>
              <VCol
                v-if="isParent"
                cols="12"
                sm="6"
              >
                <AppSelect
                  v-model="selectedScaleType"
                  :loading="loadingParentServiceTypes"
                  :items="parentServiceTypeSelectItems"
                  item-title="title"
                  item-value="value"
                  :label="$t('goals.service_type')"
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                v-if="!isParent"
                cols="12"
                sm="4"
              > 
                <AppAutocomplete
                    :items="scaleTypeFilterItems()"
                    v-model="selectedScaleType"
                    search
                    :label="$t('goals.service_type')"
                    clearable
                    clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                v-if="!returnIdUserIfNotAdmin() && !canDoes('parent')"
                cols="12"
                sm="4"
              > 
                <AppAutocomplete
                    v-model="specialist"
                    :server-search="searchSpecialists"
                    :item-title="'name'"
                    :item-value="'id'"
                    :label="$t('specialists')"
                    clearable
                    clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                v-if="!isParent"
                cols="12"
                sm="4"
              >
                <AppAutocomplete
                v-model="selectedCase"
                :server-search="searchCases"
                preload
                :preload-key="casesFilterKey"
                :label="$t('Cases')"
                :item-title="'name'"
                :item-value="'id'"
                clearable
                :placeholder="$t('Type Case Name')"
                clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                v-if="!isParent"
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
                v-if="!isParent || selectedScaleType"
                cols="12"
                :sm="isParent ? 6 : 4"
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
            <p
              v-if="isParent && parentBeneficiary && !loadingParentServiceTypes && !parentServiceTypeItems.length"
              class="text-medium-emphasis mb-0 mt-2"
            >
              {{ $t('parent_treatment_plans.no_service_types') }}
            </p>
            <ParentGoalSearchField
              v-if="isParent && selectedScaleType && goals.length"
              v-model="parentSearch"
              class="mt-2"
              style="max-inline-size: 32rem;"
              :total="goals.length"
              :shown="displayedGoals.length"
            />
            <p
              v-if="isParent && selectedScaleType && !loading.goals && goals.length && !displayedGoals.length"
              class="text-medium-emphasis mt-3 mb-0"
            >
              {{ $t('parent_goal_search.no_results') }}
            </p>
          </VCardText>

          <VDivider />

          <VCardText
            v-if="!isParent"
            class="d-flex flex-wrap py-4 gap-4"
          >
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

              <VBtn v-if="can('edit_treatment-goals','edit_treatment-goals')" color="success" @click="printPlans('services_case_planning')" :loading="loading.plan_pdf" :disabled="selectedTerm == '' || selectedTerm == null">
                {{ $t('goals.print_plans') }}
              </VBtn>
              <VMenu v-if="can('edit_treatment-goals','edit_treatment-goals')">
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

          <VDivider v-if="!isParent" />

          <p
            v-if="isParent && selectedScaleType && !loading.goals && !goals.length"
            class="text-center text-medium-emphasis pa-8"
          >
            {{ $t('parent_treatment_plans.empty_plans') }}
          </p>

          <!-- SECTION datatable -->
          <VDataTableServer
            v-if="!isParent || (selectedScaleType && (displayedGoals.length || loading.goals))"
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="displayedGoals"
            :loading="loading.goals"
            :items-length="isParent ? displayedGoals.length : totalGoals"
            :headers="translatedHeaders()"
            class="dataTable"
            :hide-default-footer="isParent"
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

            <template #item.behavioral_goal="{ item }">
              <div class="align-center">
                <RouterLink
                  v-if="!isParentUser()"
                  target="_blank"
                  :to="{ name: 'services-sessions-case-term-goal', params: { case: selectedCase, term: selectedTerm, goal: item.raw.id } }"
                  class="font-weight-medium user-list-name"
                >
                  <span>{{ item.raw.title }}</span> <span v-if="item.raw.standard">{{ $t(item.raw.standard) }}</span> <span v-if="item.raw.generalization">{{ $t(item.raw.generalization) }}</span>
                </RouterLink>
                <ParentGoalTitleLink
                  v-else
                  :goal="item.raw"
                  :to="parentGoalDetailsLocation(item.raw)"
                />
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
              <div class="gap-3">
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
                v-if="!isParentUser() && can('access_treatment-goals','access_treatment-goals')" 
                :title="$t('View')" 
                @click="router.push({ name: 'services-sessions-case-term-goal', params: { case: selectedCase, term: selectedTerm, goal: item.raw.id } });"
              >
                <VIcon icon="tabler-eye" />
              </IconBtn>

              <IconBtn v-if="can('edit_treatment-goals','edit_treatment-goals')" :title="$t('Edit')" @click="editGoal(item.raw)">
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
                      v-if="!item.raw.deleted_at && can('admin_treatment-goals','admin_treatment-goals')" 
                      :title="$t('delete')" 
                      @click="deleteUser(item.raw.id)" 
                    >
                      <template #prepend>
                        <VIcon icon="tabler-trash" />
                      </template>
                      <VListItemTitle>{{ $t('delete_user') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="item.raw.deleted_at && can('admin_treatment-goals','admin_treatment-goals')" 
                      :title="$t('restore')" 
                      @click="restoreUser(item.raw.id)" 
                    >
                      <template #prepend>
                        <VIcon icon="tabler-refresh" />
                      </template>
                      <VListItemTitle>{{ $t('restore_user') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="can('edit_treatment-goals','edit_treatment-goals')"
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
            <template
              v-if="!isParent"
              #bottom
            >
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
      </vcol>
    </vrow>

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
              sm="12"
            >
              <AppTextField v-model="editFirstFeild" :label="$t('goals.goal')" />
            </VCol>
          </VRow>
          <AppTextarea 
            v-model="editBehavioralGoal" 
            :label="$t('goals.skill')" 
            :rules="[requiredValidator]"
          />
          <!-- <AppTextarea v-model="editStandard" :label="$t('goals.Standard')" />
          <AppTextarea v-model="editGeneralization" :label="$t('goals.Generalization')" /> -->

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
            <!-- <VCol
              cols="12"
              sm="6"
            > -->
              <!-- <AppTextField v-model="editGeneralGoal" :label="$t('general_goal')" /> -->
            <!-- </VCol> -->
            <VCol
              cols="12"
              sm="12"
            >
              <AppTextField 
                v-model="editFirstFeild" 
                :label="$t('goals.goal')" 
                :rules="[requiredValidator]"
              />
            </VCol>
            <VCol
              cols="12"
              sm="12"
            >
              <AppTextarea 
                v-model="editBehavioralGoal" 
                :label="$t('goals.skills')" 
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
                @update:modelValue="onScaleSelected"
                :rules="[requiredValidator]"
              />
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
                :disabled="!hasSelectedScale"
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
                :disabled="!hasSelectedField"
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
    action: access_treatment-goals
    subject: access_treatment-goals
</route>