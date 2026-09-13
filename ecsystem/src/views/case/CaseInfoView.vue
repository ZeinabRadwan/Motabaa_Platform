<script setup>
import CaseViewDataTable from '@/views/case/components/CaseViewDataTable.vue';
import {cuntriesIndexed} from "@core/utils/cuntries";
import {convertGregorianToHijri} from "@core/utils/helper";
import i18n from '@/plugins/i18n/index.js';
import ChartJsLineChart from '@/views/charts/chartjs/ChartJsLineChart.vue'
import CaseSkillsViewTable from '@/views/case/components/CaseSkillsViewTable.vue';
import moment from 'moment';
import {
  periodItemsSearch
} from '@core/utils/generalItems';
import {
  general_questions as generalQuestions,
  psychological_study as psychologicalStudy,
  case_study, 
  sensoryAbilities,
  dailySkills,
  academicSkills,
  generalHealthCondition,
  adaptiveBehaviorSkills, 
  physicalMotorAbilities,
  languageAbilitie, 
  capabilitiesSurrentStatus
} from '@/views/case/fields/refFiledsTabs';

const currentTab = ref(0)
const props = defineProps({
  caseData: {
    type: Object,
    required: true,
  },
})

const general_questions = props.caseData.general_questions == null || props.caseData.general_questions == '' || props.caseData.general_questions == 'null'? 
                          Object.keys(generalQuestions).map(key => ({ name: key, statement: generalQuestions[key] })) : 
                          Object.keys(props.caseData.general_questions).map(key => ({ name: key, statement: props.caseData.general_questions[key] }));

const enhancement = props.caseData.case_study == null || props.caseData.case_study == '' || props.caseData.case_study == 'null'? 
                          Object.keys(case_study.enhancement).map(key => ({ name: key, statement: case_study.enhancement[key] })) : 
                          Object.keys(props.caseData.case_study.form.enhancement).map(key => ({ name: key, statement: props.caseData.case_study.form.enhancement[key] }));

const investigations = props.caseData.case_study == null || props.caseData.case_study == '' || props.caseData.case_study == 'null'? 
                          Object.keys(case_study.investigations).map(key => ({ name: key, statement: case_study.investigations[key] })) : 
                          Object.keys(props.caseData.case_study.form.investigations).map(key => ({ name: key, statement: props.caseData.case_study.form.investigations[key] }));

const afterBirth = props.caseData.case_study == null || props.caseData.case_study == '' || props.caseData.case_study == 'null'? 
                          Object.keys(case_study.afterBirth).map(key => ({ name: key, statement: case_study.afterBirth[key] })) : 
                          Object.keys(props.caseData.case_study.form.afterBirth).map(key => ({ name: key, statement: props.caseData.case_study.form.afterBirth[key] }));

const associatedDisorder = props.caseData.case_study == null || props.caseData.case_study == '' || props.caseData.case_study == 'null'? 
                          Object.keys(case_study.associatedDisorder).map(key => ({ name: key, statement: case_study.associatedDisorder[key] })) : 
                          Object.keys(props.caseData.case_study.form.associatedDisorder).map(key => ({ name: key, statement: props.caseData.case_study.form.associatedDisorder[key] }));

props.caseData.case_study == null || props.caseData.case_study == '' || props.caseData.case_study == 'null' ?
  null : delete props.caseData.case_study.form.familyDealCase[""];




const tablesTab3Subjets =[
  'fatherInfo',
  'motherInfo',
  'familyInfo',
  'familyDealCase',
  'caseHistory',
  'effectDisorder',
  'evolutionaryHistory',,
  'linguisticHistory',
  'other',
  'growthHistory',
  'medicalHistory',
  'generalCapabilities',
  'personalSkills',
  'selfReliance',
  'communicationSkills',
];
let tables = {};

props.caseData.case_study == null || props.caseData.case_study == '' || props.caseData.case_study == 'null' ?
  tablesTab3Subjets.forEach((keyTable) => {
    tables[keyTable] = Object.keys(case_study[keyTable])
                          .map(key => ({ name: key, statement: case_study[keyTable][key] }));
  }) :
  tablesTab3Subjets.forEach((keyTable) => {
    tables[keyTable] = Object.keys(props.caseData.case_study.form[keyTable])
                          .map(key => ({ name: key, statement: props.caseData.case_study.form[keyTable][key] }));
  })



const tablesTab4Subjets =[
    'healthStatus',
    'family',
    'mother',
    'brothersAndSisters',
    'OtherMembersLiveWithTheFamily',
    'EvolutionaryHistoryOfTheCondition',
    'psychologicalScales',
    'communicationSkills',
    'generalMentalAbility',
    'generalMoodOfTheSituation',
    'reinforcers',
    'PsychologicalExamination',
    'mentalCapacities',
    'miscellaneousQuestions',
  ];
let tablesTab4 = {};

props.caseData.psychological_study == null || props.caseData.psychological_study == '' || props.caseData.psychological_study == 'null' ?
    tablesTab4Subjets.forEach((keyTable) => {
      tablesTab4[keyTable] = Object.keys(psychologicalStudy[keyTable])
                            .map(key => ({ name: key, statement: psychologicalStudy[keyTable][key] }));
    }) :
    tablesTab4Subjets.forEach((keyTable) => {
      tablesTab4[keyTable] = Object.keys(props.caseData.psychological_study[keyTable])
                            .map(key => ({ name: key, statement: props.caseData.psychological_study[keyTable][key] }));
    });

props.caseData.psychological_study == null || props.caseData.psychological_study == '' || props.caseData.psychological_study == 'null' ?
  props.caseData.psychological_study = {initFields: { diagnosisChild: '', description: '', reasons: '', }}: null;

props.caseData.case_study == null || props.caseData.case_study == '' || props.caseData.case_study == 'null' ?
  props.caseData.case_study = {form : {initFields: { uninterruptedBehaviors: '', caseDetails: '', caseBehavior: '', }}}: null;


const data = ref([
    {name : 'Insurance Number', statement: props.caseData.beneficiary_number},
    {name : 'idNumber', statement: props.caseData.id_or_residence_number},
    {name : 'Nationality', statement: cuntriesIndexed[props.caseData.nationality][i18n.global.locale.value == "ar" ? "country_arNationality" : "country_enNationality"]},
    {name : 'Age', statement: `${props.caseData.age}` },
    {name : 'Birth date', statement: convertGregorianToHijri(props.caseData.birthdate)},
    {name : 'Period', statement: periodItemsSearch(`${props.caseData.period}`)[0]['title'] },
    {name : 'Blood Type', statement: props.caseData.blood_type},
    {name : 'Types of disability', statement: props.caseData.disability_type_names.toString()},
    {name : 'Services provided', statement: props.caseData.services.toString()},
    {name : 'parent', statement: props.caseData.parents.map(obj => obj.name).toString(), parents: props.caseData.parents, is_parent: true},
    {name : 'teacher', statement: props.caseData.teacher?.name, id: props.caseData.teacher?.id, is_user: true},
    {name : 'Physiotherapist', statement: props.caseData.physiotherapist?.name, id: props.caseData.physiotherapist?.id, is_user: true},
    {name : 'Occupational therapist', statement: props.caseData.occupational_therapy?.name, id: props.caseData.occupational_therapy?.id, is_user: true},
    {name : 'Psychotherapist', statement: props.caseData.psychotherapist?.name, id: props.caseData.psychotherapist?.id, is_user: true},
    {name : 'pronunciation_speech_specialist', statement: props.caseData.pronunciation_speech_specialist?.name, id: props.caseData.pronunciation_speech_specialist?.id, is_user: true},
    {name : 'Contact in case of emergency', statement: props.caseData.emergency_contact},
    {name : 'Phone Number', statement: props.caseData.phone},
    {name : 'Building number', statement: props.caseData.address_building},
    {name : 'Street name', statement: props.caseData.address_street},
    {name : 'El Hay', statement: props.caseData.address_area},
    {name : 'City', statement: props.caseData.address_city},
    {name : 'Zip', statement: props.caseData.address_zipcode},
    {name : 'Second Zip', statement: props.caseData.address_number},
    {name : 'Unit number', statement: props.caseData.address_unit},
])

if(props.caseData.beneficiary_number == '' || props.caseData.beneficiary_number == null){
  data.value.shift();
}

const chartJsCustomColors = {
  white: '#fff',
  yellow: '#ffe802',
  primary: '#836af9',
  areaChartBlue: '#2c9aff',
  barChartYellow: '#ffcf5c',
  polarChartGrey: '#4f5d70',
  polarChartInfo: '#299aff',
  lineChartYellow: '#d4e157',
  polarChartGreen: '#28dac6',
  lineChartPrimary: '#9e69fd',
  lineChartWarning: '#ff9800',
  horizontalBarInfo: '#26c6da',
  polarChartWarning: '#ff8131',
  scatterChartGreen: '#28c76f',
  warningShade: '#ffbd1f',
  areaChartBlueLight: '#84d0ff',
  areaChartGreyLight: '#edf1f4',
  scatterChartWarning: '#ff9f43',
}
</script>

<template>
  <VRow>

    <VCol
      cols="12"
      md="12"
      lg="12"
    >

    <VTabs 
        v-model="currentTab"
        grow
        hide-slider
        stacked
    >
      <VTab>
        <VRow value="tab-1">
            <VIcon
            icon="tabler-info-circle"
            class="ml-2 mr-2"
            />
            <span class="mt-1" >{{ $t('General Info') }}</span>
        </VRow>
      </VTab>
      <VTab>
        <VRow value="tab-1">
            <VIcon
            icon="tabler-question-mark"
            class="ml-2 mr-2"
            />
            <span class="mt-1" >{{ $t('General Questions') }}</span>
        </VRow>
      </VTab>
      <VTab>
        <VRow value="tab-1">
            <VIcon
            icon="tabler-book"
            class="ml-2 mr-2"
            />
            <span class="mt-1" >{{ $t('Case Study') }}</span>
        </VRow>
      </VTab>
      <VTab>
        <VRow value="tab-1">
            <VIcon
            icon="tabler-book"
            class="ml-2 mr-2"
            />
            <span class="mt-1" >{{ $t('Psychological study') }}</span>
        </VRow>
      </VTab>
      
    </VTabs>

    <VWindow v-model="currentTab">
        <VWindowItem>
            <CaseViewDataTable :rowsData="data" />
        </VWindowItem>
        <VWindowItem>
          <CaseViewDataTable :rowsData="general_questions" />
        </VWindowItem>
        <VWindowItem>
            <VCard>
              <VCardText>
                <AppTextarea :label="$t('caseDetails')" auto-grow v-model="props.caseData.case_study.form.initFields.caseDetails" disabled />
                <AppTextarea :label="$t('caseBehavior')" auto-grow v-model="props.caseData.case_study.form.initFields.caseBehavior" disabled />
                <AppTextarea :label="$t('uninterruptedBehaviors')" auto-grow v-model="props.caseData.case_study.form.initFields.uninterruptedBehaviors" disabled />

                <h4 class="text-h4 mt-4 mb-2"> {{ $t('enhancement') }}</h4>
                <CaseViewDataTable :rowsData="enhancement" />

                <h4 class="text-h4 mt-4 mb-2"> {{ $t('afterBirth') }}</h4>
                <CaseViewDataTable :rowsData="afterBirth" />
                
                <h4 class="text-h4 mt-4 mb-2"> {{ $t('investigations') }}</h4>
                <CaseViewDataTable :rowsData="investigations" />

                <h4 class="text-h4 mt-4 mb-2"> {{ $t('associatedDisorder') }}</h4>
                <CaseViewDataTable :rowsData="associatedDisorder" />
<!-- -------------------------------------------------- -->
                <h4 class="text-h4 mt-4 mb-2"> {{ $t('capabilitiesSurrentStatus') }}</h4>
                <CaseSkillsViewTable :disabledEL="true" :rowsData="props.caseData.case_study.capabilitiesSurrentStatus ?? capabilitiesSurrentStatus"></CaseSkillsViewTable>

                <h4 class="text-h4 mt-4 mb-2"> {{ $t('sensoryAbilities') }}</h4>
                <CaseSkillsViewTable :disabledEL="true" :rowsData="props.caseData.case_study.sensoryAbilities ?? sensoryAbilities"></CaseSkillsViewTable>

                <h4 class="text-h4 mt-4 mb-2"> {{ $t('physicalMotorAbilities') }}</h4>
                <CaseSkillsViewTable :disabledEL="true" :rowsData="props.caseData.case_study.physicalMotorAbilities ?? physicalMotorAbilities"></CaseSkillsViewTable>

                <h4 class="text-h4 mt-4 mb-2"> {{ $t('languageAbilitie') }}</h4>
                <CaseSkillsViewTable :disabledEL="true" :rowsData="props.caseData.case_study.languageAbilitie ?? languageAbilitie"></CaseSkillsViewTable>

                <h4 class="text-h4 mt-4 mb-2"> {{ $t('adaptiveBehaviorSkills') }}</h4>
                <CaseSkillsViewTable :disabledEL="true" :rowsData="props.caseData.case_study.adaptiveBehaviorSkills ?? adaptiveBehaviorSkills"></CaseSkillsViewTable>

                <h4 class="text-h4 mt-4 mb-2"> {{ $t('dailySkills') }}</h4>
                <CaseSkillsViewTable :disabledEL="true" :rowsData="props.caseData.case_study.dailySkills ?? dailySkills"></CaseSkillsViewTable>
                
                <h4 class="text-h4 mt-4 mb-2"> {{ $t('academicSkills') }}</h4>
                <CaseSkillsViewTable :disabledEL="true" :rowsData="props.caseData.case_study.academicSkills ?? academicSkills"></CaseSkillsViewTable>

                <h4 class="text-h4 mt-4 mb-2"> {{ $t('generalHealthCondition') }}</h4>
                <CaseSkillsViewTable :disabledEL="true" :rowsData="props.caseData.case_study.generalHealthCondition ?? generalHealthCondition"></CaseSkillsViewTable>
<!-- -------------------------------------------------- -->

                <div v-for="(item, index) in tables" :key="index">

                  <h4 class="text-h4 mt-4 mb-2"> {{ $t(index) }}</h4>
                  <CaseViewDataTable :rowsData="item" />

                </div>

              </VCardText>              
            </VCard>
        </VWindowItem>
        <VWindowItem>
          <VCard>
          <VCardText>              
              <AppTextarea :label="$t('diagnosisChild')" auto-grow v-model="props.caseData.psychological_study.initFields.diagnosisChild" disabled />
              <AppTextarea :label="$t('description')" auto-grow v-model="props.caseData.psychological_study.initFields.description" disabled />
              <AppTextarea :label="$t('reasons')" auto-grow v-model="props.caseData.psychological_study.initFields.reasons" disabled />

              <div v-for="(item, index) in tablesTab4" :key="index">
                <h4 class="text-h4 mt-4 mb-2"> {{ $t(index) }}</h4>
                <CaseViewDataTable :rowsData="item" />
              </div>


            </VCardText>              
          </VCard>
        </VWindowItem>
    </VWindow>


    </VCol>
  </VRow>
</template>
