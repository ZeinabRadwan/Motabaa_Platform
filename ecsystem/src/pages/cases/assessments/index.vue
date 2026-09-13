<script setup>
import { useRoute, useRouter } from 'vue-router';
import ScalesListRoots from '@/views/scale/ScalesListRoots.vue';
import ScalesListField from '@/views/scale/ScalesListField.vue';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import i18n from '@/plugins/i18n/index.js'
import {assessmentEvaluationsApi} from "@/plugins/apis/assessmentEvaluationsReqest"
import {assessmentsApi} from "@/plugins/apis/assessmentReqest"
import {casesApi} from "@/plugins/apis/casesReqest"
import { can } from '@layouts/plugins/casl'
import {
  termItems
} from '@core/utils/generalItems';

const snackbarRef = ref(null);
const route = useRoute()
const router = useRouter()
const assessmentsReqest = assessmentsApi()
const assessmentEvaluationsReqest = assessmentEvaluationsApi()
const refVForm = ref()

const scales = ref({})
const selectedscale = ref({})
const selectedScales = ref([])
const isEndingAssessment = ref(false)
const itemsTerm = ref(null);
const casesReqest = casesApi()
const selectedCase = ref(null);
const selectedTerm = ref(null);
const selectedGoals = ref({});
const weaks = ref({});
const oldTitles = ref({});
const weaksParents = ref({});
const casesItem = ref([]);
const loadingItem = ref(false);
const search = ref()
const caseData = ref(null);
const canSave = ref(false);
const isLeaveDialogVisible = ref(false);
const leaveDialogAction = ref();
const leaveDialogFirstValue = ref();
const leaveDialogSecondValue = ref();

//loading Rotate
const loading = ref(false)
const answerGoals = ref(null);

const searchCases = params => casesReqest.selectItems(params).then(response => {
    casesItem.value = response.data.data ?? []
    return response
})

watch(selectedCase, caseId => {
    if(caseId == null){
        scales.value = {};
    }
    caseData.value = casesItem.value.filter(i => i.id == caseId)[0];
    caseId && fetchAssessments(caseId)
})

watch(selectedTerm, query => {
    query && listWeaks()
})

const clickedScale = (scale) => {
    selectedScales.value.push(scale)
    selectedscale.value = scale.id;
    fetchAssessments(selectedCase.value)
};

const fetchAssessments = (caseId) => {
    scales.value = {};
    answerGoals.value = null;
    loading.value = true;
    assessmentEvaluationsReqest.fetchAll({
        case_id: caseId,
        parent_id: selectedscale.value,
    }).then(response => {
        loading.value = false;
        scales.value = response.data.data;
        if(scales.value[Object.keys(scales.value)[0]]?.type == 'goal'){
            const answerValueMap = {};
            scales.value.forEach(item => {
                const parentId = item.id;
                const answer = item.answer[0]?.value || null;
                answerValueMap[parentId] = answer;
            });

            answerGoals.value = answerValueMap
        }
    })
};

const gotoAssessments = (scale = null, scales = []) => {
    if(selectedCase.value != '' && selectedCase.value != null){
        isEndingAssessment.value = false
        selectedscale.value = scale;
        selectedScales.value = scales.filter(item => item.id <= scale);
        saveGoals(false)
        fetchAssessments(selectedCase.value)
    }
}

const getEvaluationMethod = () => {
    if(scales.value[0]?.evaluation_method){
        let items = JSON.parse(scales.value[0]?.evaluation_method.items);

        let translated = items.map(item => ({
        ...item,
        title: i18n.global.t(item.title),
        }));

        return translated;
    }
    return [];
}

const getEvaluation = (value) => {
    let method = getEvaluationMethod()
    let evaluation = method.find(item => item.value === String(value))
    if(evaluation) return evaluation.title;
    return '';
}

const addGoal = (event, goal) => {
    canSave.value = true
    selectedGoals.value[goal.id] = {
        case_id: selectedCase.value,
        assesment_id: goal.id,
        ability:getEvaluationMethod().filter((i) => i.value == event)[0]?.ability ?? null,
        value: event,
    };
}

const saveGoals = (showsnackbar = true) => {
    canSave.value = false
    loading.value = true;
    assessmentEvaluationsReqest.saveGoals({
        goals: Object.assign([], Object.values(selectedGoals.value))
    }).then(response => {
        loading.value = false;
        if(showsnackbar){
            snackbarRef.value.exposevisibleSnackbar(i18n.global.t('updated_successfully'), 'success');
        }
    })
}

onMounted(() => {

  termItems().then(data => {
    itemsTerm.value = data
  })
});

const alertColor = (total_goals, total_power_goals, total_weak_goals) => {
    if(total_power_goals == 0 && total_weak_goals ==0){
        return 'secondary';
    }
    if(total_power_goals == 0 && total_weak_goals > 0){
        return 'error';
    }
    if(total_power_goals > 0 && total_weak_goals > 0){
        return 'warning';
    }
    if(total_goals == total_power_goals){
        return 'success';
    }

    return 'primary';
}

const listWeaks = () => {
    // loading.value = true;
    isEndingAssessment.value = true
    assessmentEvaluationsReqest.listWeaks({
        case_id: selectedCase.value,
        term_id: selectedTerm.value,
        assesment_id: selectedScales.value ? selectedScales.value[0].id : null,
    }).then(response => {
        weaks.value = response.data.data.goals;
        weaksParents.value = response.data.data.goals_parents;
        const arabicScriptRegex = /[\u0600-\u06FF\u0750-\u077F]/;
        for (const key in weaks.value) {
            oldTitles.value[key] = weaks.value[key].title
            const words = weaks.value[key].title.split(' ');
            const firstWord = words[0];
            const prefix = ` أن ${firstWord} ${caseData.value.name.split(' ')[0]} `;
            const prefixEn = ` ${caseData.value.name} Does `; 
            if ((weaks.value[key].category === "educational" || weaks.value[key].category === "independent") && arabicScriptRegex.test(weaks.value[key].title)) {
                words.shift();
                weaks.value[key].title = words.join(' ');
                const match = weaks.value[key].title.match(/^(\d+\.\s+)/);
                if (match) {
                    const numberAndDot = match[1];
                    weaks.value[key].title = weaks.value[key].title.replace(numberAndDot, numberAndDot + prefix);
                } else {
                    weaks.value[key].title = prefix + weaks.value[key].title;
                }
            }
        }
    })
}

const moveWeaks = () => {
    for (const key in weaks.value) {
        if (weaks.value.hasOwnProperty(key)) {
            const obj = weaks.value[key];
            obj.term_id = selectedTerm.value;
            obj.title_local = obj.title;
        }
    }
    assessmentEvaluationsReqest.moveWeaks({goals:weaks.value}).then(response => {
        isEndingAssessment.value = false
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('updated_successfully'), 'success');
    })
}

const cancel = () => {
    fetchAssessments(selectedCase.value)
    isEndingAssessment.value = false
    selectedTerm.value = null
}

const openLeaveDialog = (action, firstValue=null, secondValue=null) => {
    selectedGoals.value = {};
    leaveDialogAction.value = action
    leaveDialogFirstValue.value = firstValue
    leaveDialogSecondValue.value = secondValue
    if(canSave.value == true) {
        isLeaveDialogVisible.value = true
    }
    else {
        leaveFunction()
    }
}

const leaveFunction = () => {
    if(leaveDialogAction.value == 'end') {
        listWeaks()
    }
    else if(leaveDialogAction.value == 'clicked_scale') {
        gotoAssessments(leaveDialogFirstValue.value, leaveDialogSecondValue.value)
    }
    leaveDialogAction.value = null
    leaveDialogFirstValue.value = null
    leaveDialogSecondValue.value = null
    canSave.value = false
    isLeaveDialogVisible.value = false
}

</script>

<template>
  <div>
    <VRow v-if="!isEndingAssessment">
      <VCol cols="12">
        <VCard>   
            <template v-slot:title>
            <VRow>
                <span class="v-col v-col-6 d-flex gap-4"> {{ $t('Add Assessment') }}</span>
            </VRow>
            </template>

            <VCardText>
            
                <div class="justify-lg-space-between d-flex align-center flex-wrap gap-4 pb-9">
                    <!-- 👉 Search  -->
                    <VCol cols="5">
                        <AppAutocomplete
                            v-model="selectedCase"
                            :server-search="searchCases"
                            :label="$t('Cases')"
                            :item-title="'name'"
                            :item-value="'id'"
                            clearable
                            :placeholder="$t('Type Case Name')"
                        />
                    </VCol>

                    <div  class="d-flex gap-4">
                        <VBtn 
                            v-if="can('apply_assessments_cases','apply_assessments_cases') && scales && scales[Object.keys(scales)[0]]?.type == 'goal'" 
                            :disabled="!canSave"
                            @click="saveGoals()" 
                        >
                            {{ $t('Save') }}
                        </VBtn>
                        <VBtn 
                            v-if="can('apply_assessments_cases','apply_assessments_cases') && selectedScales.length>0" 
                            color="success" 
                            @click="openLeaveDialog('end')" 
                            :disabled="selectedCase == '' || selectedCase == null"
                        >
                            {{ $t('ending') }}
                        </VBtn>

                    </div>
                </div>
                <div class="">
                    <span class="pointer-cursor">
                        <VIcon style="color: rgb(var(--v-theme-primary)) !important;" @click="gotoAssessments()" size="17" icon="tabler-home"/>
                    </span>
                    <span v-for="step in selectedScales" :key="step.id" @click="openLeaveDialog('clicked_scale', step.id, selectedScales)" class="pointer-cursor ml-1 mr-1">
                        <span class="d-inline-block"> <VIcon class="mt-1" size="16" icon="tabler-math-lower"/>  {{ step.title }}</span>
                    </span>
                </div>

                <VRow v-if="loading">
                    <VCol cols="12" class="d-flex justify-center">
                        <VProgressCircular
                        :size="50"
                        color="primary"
                        indeterminate
                        />
                    </VCol>
                </VRow>
                <div v-if="!loading && scales && scales[Object.keys(scales)[0]]?.type != 'goal'" class="mt-2">
                    <VAlert
                        v-for="(scale, key) in scales" 
                        :key="key"
                        :color="alertColor(scale.total_goals, scale.total_power_goals, scale.total_weak_goals)"
                        class="mb-2"
                        height="100"
                        rounded="0"
                    >
                        <template v-slot:text>
                            <div @click="clickedScale(scale)"  class="d-flex align-center pointer-cursor">
                                <span class="pr-16"></span>
                                <span class="ma-auto d-flex font-weight-black text-h4" style="color: white;">
                                    <span>({{ $t('scalesCount', { totel: scale.total_goals, power: scale.total_power_goals  })}}) {{ $t(scale.title) }}</span>
                                </span>
                            </div>
                        </template>
                    </VAlert>
                </div>

            </VCardText>

                    
            <div v-if="!loading && scales && scales[Object.keys(scales)[0]]?.type == 'goal'">
                <VDivider />
                <div v-for="(field, key) in scales" :key="key">
                    <VCardText>
                        <VRow>
                            <VCol cols="12" lg="10" md="10" sm="12">
                                <p class="font-weight-medium text-subtitle-2 mt-2">
                                    {{ field.title }}
                                </p>
                            </VCol>
                            <VCol cols="12" lg="2" md="2" sm="12">
                                <AppSelect
                                    v-model="answerGoals[field.id]"
                                    :items="getEvaluationMethod()"
                                    @update:modelValue="addGoal($event, field)"
                                    clearable
                                />
                            </VCol>
                        </VRow>
                    </VCardText>
                    <VDivider />
                </div>
            </div>

        </VCard>
      </VCol>
    </VRow>

    <VRow v-if="isEndingAssessment">
      <VCol cols="12">
        <VCard>   
            <template v-slot:title>
            <VRow>
                <span class="v-col v-col-6 d-flex gap-4"> {{ $t('Case name') }}: {{ caseData.name }}</span>
            </VRow>
            </template>

            <VCardText>
                <div class="justify-lg-space-between d-flex align-center flex-wrap gap-4 pb-9">
                    <!-- 👉 Search  -->
                    <div style="inline-size: 23rem;">
                        <AppSelect
                            :label="$t('choose term')"
                            :items="itemsTerm"
                            v-model="selectedTerm"
                        />
                    </div>

                    <div  class="d-flex gap-4">
                        <VBtn @click="moveWeaks()" :disabled="selectedTerm == '' || selectedTerm == null">
                            {{ $t('Create a qualification plan') }}
                        </VBtn>
                        <VBtn
                        color="secondary"
                        @click="cancel()"
                        >
                        {{ $t('Cancel') }}
                        </VBtn>
                    </div>
                </div>
            </VCardText>
            <div >
                <VDivider />
                <div v-for="(weak, key) in weaks" :key="key">
                    <VCardText >
                        <VRow>
                            <VCol cols="12">
                                <div style="font-size: 12px;">
                                    <span class="pointer-cursor">
                                        <VIcon 
                                            size="12" 
                                            icon="tabler-home"
                                            style="color: rgb(var(--v-theme-primary)) !important;" 
                                            @click="gotoAssessments()" 
                                        />
                                    </span>
                                    <span 
                                        v-for="parent in weaksParents[key]" 
                                        :key="parent.id" 
                                        class="pointer-cursor ml-1 mr-1"
                                        @click="gotoAssessments(parent.id, weaksParents[key])" 
                                    >
                                        <span class="d-inline-block">
                                            <VIcon 
                                                size="12" 
                                                class="mt-1" 
                                                icon="tabler-math-lower"
                                            />
                                            {{ parent.title }}
                                        </span>
                                    </span>
                                    <span>
                                        {{ getEvaluation(weaks[key]['value']) ? '('+getEvaluation(weaks[key]['value'])+')' : '' }}
                                    </span>
                                    <br/>
                                    <span>
                                        {{ oldTitles[key] }}
                                    </span>
                                </div>
                                <AppTextarea
                                    class="mt-1"
                                    v-model="weaks[key]['title']"
                                    row-height="20"
                                    auto-grow
                                    rows="2"
                                />
                            </VCol>
                        </VRow>
                    </VCardText>
                    <VDivider />
                </div>
            </div>

        </VCard>
      </VCol>
    </VRow>


    <VDialog
      v-model="isLeaveDialogVisible"
      persistent
      class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isLeaveDialogVisible = !isLeaveDialogVisible" />

      <!-- Dialog Content -->
      <VCard>
        <VCardText>
          {{ $t('Are you sure you want to leave this page, Your changes will be lost?') }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn @click="leaveFunction()">
            {{ $t('yes') }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isLeaveDialogVisible=false"
          >
            {{ $t('Cancel') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>
<route lang="yaml">
  meta:
    action: access_assessments_cases
    subject: access_assessments_cases
</route>