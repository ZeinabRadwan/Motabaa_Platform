<script setup>
import { casesApi } from "@/plugins/apis/casesReqest";
import i18n from '@/plugins/i18n/index.js';
import CaseGeneralInfo from '@/views/case/CaseGeneralInfo.vue';
import CaseGeneralQuestions from '@/views/case/CaseGeneralQuestions.vue';
import CasePsychologicalStudy from '@/views/case/CasePsychologicalStudy.vue';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';

// import CaseCaseStudy from '@/views/case/CaseCaseStudy.vue';
import {
  academicSkills,
  adaptiveBehaviorSkills,
  capabilitiesSurrentStatus,
  case_study,
  dailySkills,
  generalHealthCondition,
  general_questions,
  languageAbilitie,
  physicalMotorAbilities,
  psychological_study,
  sensoryAbilities
} from '@/views/case/fields/refFiledsTabs';

import { defineAsyncComponent } from 'vue';
const CaseCaseStudy = defineAsyncComponent(() => import('@/views/case/CaseCaseStudy.vue'))

import { useRoute, useRouter } from 'vue-router';

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const casesReqest = casesApi()
const snackbarRef = ref(null);

const CaseCaseStudyRef = ref(null)
const currentTab = ref(0)
const CaseGeneralInfoRef = ref(null)
const CaseGeneralQuestionsRef = ref(null)
const CasePsychologicalStudyRef = ref(null)
const loaded = ref(false)

const caseUser = ref([])

casesReqest.fetchCase(Number(route.params.id)).then(response => {
  let data = response.data.data;
  caseUser.value = data

  CaseGeneralInfoRef.value.center_id = data.center_id;
  CaseGeneralInfoRef.value.chooseImage = data.picture;
  CaseGeneralInfoRef.value.disability = data.disability_type_ids;
  CaseGeneralInfoRef.value.period = `${data.period}`;
  CaseGeneralInfoRef.value.serviceVal = data.services_provided;
  CaseGeneralInfoRef.value.insuranceNumber = data.beneficiary_number != null && data.beneficiary_number != 'null'? data.beneficiary_number : '';
  CaseGeneralInfoRef.value.caseName = data.name;
  CaseGeneralInfoRef.value.file = {}
  CaseGeneralInfoRef.value.teacher = data.teacher_id;
  CaseGeneralInfoRef.value.physiotherapist = data.physiotherapist_id;
  CaseGeneralInfoRef.value.occupational = data.occupational_therapy_id;
  CaseGeneralInfoRef.value.emergency_contact = data.emergency_contact;
  CaseGeneralInfoRef.value.psychotherapist = data.psychotherapist_id;
  CaseGeneralInfoRef.value.pronunciationSpeech = data.pronunciation_speech_specialist_id;
  CaseGeneralInfoRef.value.idNumber = data.id_or_residence_number;
  CaseGeneralInfoRef.value.nationality = data.nationality;
  CaseGeneralInfoRef.value.brithdate = data.birthdate;
  CaseGeneralInfoRef.value.parent = data.parents_id;
  CaseGeneralInfoRef.value.phoneNumber = data.phone != null && data.phone != 'null' ? data.phone : '';
  CaseGeneralInfoRef.value.bloodtypes = data.blood_type != [] && data.blood_type != '[]' ? data.blood_type : '';
  CaseGeneralInfoRef.value.buildNumber = data.address_building != null && data.address_building != 'null' ? data.address_building : '';
  CaseGeneralInfoRef.value.street = data.address_street != null && data.address_street != 'null' ? data.address_street : '';
  CaseGeneralInfoRef.value.alhay = data.address_area != null && data.address_area != 'null' ? data.address_area : '';
  CaseGeneralInfoRef.value.city = data.address_city != null && data.address_city != 'null' ? data.address_city : '';
  CaseGeneralInfoRef.value.zip = data.address_zipcode != null && data.address_zipcode != 'null' ? data.address_zipcode : '';
  CaseGeneralInfoRef.value.zip2 = data.address_number != null && data.address_number != 'null' ? data.address_number : '';
  CaseGeneralInfoRef.value.unitNumber = data.address_unit != null && data.address_unit != 'null' ? data.address_unit : '';
  CaseGeneralInfoRef.value.insuranceVal = data.beneficiary_number != null && data.beneficiary_number != 'null'  ? '1' : '0'

})


const CaseGeneralQuestionsMounted = () => {
  if(caseUser.value.general_questions == null || caseUser.value.general_questions == '' || caseUser.value.general_questions == 'null'){
    CaseGeneralQuestionsRef.value.form = general_questions;
  }else{
    CaseGeneralQuestionsRef.value.form = mergeObjects(caseUser.value.general_questions, general_questions) 
  }
}

const CasePsychologicalStudyMounted = () => {
  if(caseUser.value.psychological_study == null || caseUser.value.psychological_study == '' || caseUser.value.psychological_study == 'null'){
    CasePsychologicalStudyRef.value.form = psychological_study;
  }else{
    let data = {};
    Object.keys(psychological_study)
                            .map(key => (  data[key] = mergeObjects(caseUser.value.psychological_study[key], psychological_study[key]) ))

    CasePsychologicalStudyRef.value.form = data
  }
}

const childMounted = () => {

  if(caseUser.value.case_study == null || caseUser.value.case_study == '' || caseUser.value.case_study == 'null'){
    CaseCaseStudyRef.value.form = case_study
    CaseCaseStudyRef.value.sensoryAbilitiesRef.rows = sensoryAbilities
    CaseCaseStudyRef.value.dailySkillsRef.rows = dailySkills
    CaseCaseStudyRef.value.academicSkillsRef.rows = academicSkills
    CaseCaseStudyRef.value.generalHealthConditionRef.rows = generalHealthCondition
    CaseCaseStudyRef.value.adaptiveBehaviorSkillsRef.rows = adaptiveBehaviorSkills
    CaseCaseStudyRef.value.physicalMotorAbilitiesRef.rows = physicalMotorAbilities
    CaseCaseStudyRef.value.languageAbilitieRef.rows = languageAbilitie
    CaseCaseStudyRef.value.capabilitiesSurrentStatusRef.rows = capabilitiesSurrentStatus
  }else{
    let data = {};
    Object.keys(case_study).map(key => (  data[key] = mergeObjects(caseUser.value.case_study['form'][key], case_study[key]) ))

    CaseCaseStudyRef.value.form = data
    CaseCaseStudyRef.value.sensoryAbilitiesRef.rows = mergeObjects(caseUser.value.case_study.sensoryAbilities, sensoryAbilities) 
    CaseCaseStudyRef.value.dailySkillsRef.rows = mergeObjects(caseUser.value.case_study.dailySkills, dailySkills) 
    CaseCaseStudyRef.value.academicSkillsRef.rows = mergeObjects(caseUser.value.case_study.academicSkills, academicSkills) 
    CaseCaseStudyRef.value.generalHealthConditionRef.rows = mergeObjects(caseUser.value.case_study.generalHealthCondition, generalHealthCondition) 
    CaseCaseStudyRef.value.adaptiveBehaviorSkillsRef.rows = mergeObjects(caseUser.value.case_study.adaptiveBehaviorSkills, adaptiveBehaviorSkills) 
    CaseCaseStudyRef.value.physicalMotorAbilitiesRef.rows = mergeObjects(caseUser.value.case_study.physicalMotorAbilities, physicalMotorAbilities) 
    CaseCaseStudyRef.value.languageAbilitieRef.rows = mergeObjects(caseUser.value.case_study.languageAbilitie, languageAbilitie) 
    CaseCaseStudyRef.value.capabilitiesSurrentStatusRef.rows =  mergeObjects(caseUser.value.case_study.capabilitiesSurrentStatus, capabilitiesSurrentStatus) 
  }


  loaded.value = true
}

const save = async () => {
  const valid = await CaseGeneralInfoRef.value.refVForm?.validate()
  if(valid.valid == false){
    // const imageValid = await CaseGeneralInfoRef.value.refVForm.items[10].validate();
    // CaseGeneralInfoRef.value.imageValidMessage = imageValid[0]
    currentTab.value = 0;
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.missing_field'), 'error');

    return;
  }

  let data ={
    center_id: CaseGeneralInfoRef.value.center_id,
    disability_type_ids: CaseGeneralInfoRef.value.disability,
    period: CaseGeneralInfoRef.value.period,
    services_provided: CaseGeneralInfoRef.value.serviceVal,
    beneficiary_number: CaseGeneralInfoRef.value.insuranceNumber,
    name: CaseGeneralInfoRef.value.caseName,
    image: CaseGeneralInfoRef.value.file[0] ?? false,
    teacher_id: CaseGeneralInfoRef.value.teacher,
    physiotherapist_id: CaseGeneralInfoRef.value.physiotherapist == null ? '' : CaseGeneralInfoRef.value.physiotherapist,
    occupational_therapy_id: CaseGeneralInfoRef.value.occupational == null ? '' : CaseGeneralInfoRef.value.occupational,
    emergency_contact:  CaseGeneralInfoRef.value.emergency_contact == null ? '' : CaseGeneralInfoRef.value.emergency_contact,
    psychotherapist_id: CaseGeneralInfoRef.value.psychotherapist == null ? '' : CaseGeneralInfoRef.value.psychotherapist,
    pronunciation_speech_specialist_id: CaseGeneralInfoRef.value.pronunciationSpeech == null ? '' : CaseGeneralInfoRef.value.pronunciationSpeech,
    id_or_residence_number: CaseGeneralInfoRef.value.idNumber,
    nationality: CaseGeneralInfoRef.value.nationality,
    birthdate: CaseGeneralInfoRef.value.brithdate,
    parent_id: CaseGeneralInfoRef.value.parent,
    phone: CaseGeneralInfoRef.value.phoneNumber,
    blood_type: CaseGeneralInfoRef.value.bloodtypes,
    address_building: CaseGeneralInfoRef.value.buildNumber,
    address_street: CaseGeneralInfoRef.value.street,
    address_area: CaseGeneralInfoRef.value.alhay,
    address_city: CaseGeneralInfoRef.value.city,
    address_zipcode: CaseGeneralInfoRef.value.zip,
    address_number: CaseGeneralInfoRef.value.zip2,
    address_unit: CaseGeneralInfoRef.value.unitNumber,
    general_questions: CaseGeneralQuestionsRef.value?.form ? CaseGeneralQuestionsRef.value?.form : caseUser.value.general_questions,
    // case_study: {
    //   form : CaseCaseStudyRef.value?.form ? CaseCaseStudyRef.value?.form : caseUser.value.case_study.form,
    //   sensoryAbilities: CaseCaseStudyRef.value?.sensoryAbilitiesRef.rows ? CaseCaseStudyRef.value?.sensoryAbilitiesRef.rows : caseUser.value.case_study.sensoryAbilities,
    //   dailySkills: CaseCaseStudyRef.value?.dailySkillsRef.rows ? CaseCaseStudyRef.value?.dailySkillsRef.rows : caseUser.value.case_study.dailySkills,
    //   academicSkills: CaseCaseStudyRef.value?.academicSkillsRef.rows ? CaseCaseStudyRef.value?.academicSkillsRef.rows : caseUser.value.case_study.academicSkills,
    //   generalHealthCondition: CaseCaseStudyRef.value?.generalHealthConditionRef.rows ? CaseCaseStudyRef.value?.generalHealthConditionRef.rows : caseUser.value.case_study.generalHealthCondition,
    //   adaptiveBehaviorSkills: CaseCaseStudyRef.value?.adaptiveBehaviorSkillsRef.rows ? CaseCaseStudyRef.value?.adaptiveBehaviorSkillsRef.rows : caseUser.value.case_study.adaptiveBehaviorSkills,
    //   physicalMotorAbilities: CaseCaseStudyRef.value?.physicalMotorAbilitiesRef.rows ? CaseCaseStudyRef.value?.physicalMotorAbilitiesRef.rows : caseUser.value.case_study.physicalMotorAbilities,
    //   languageAbilitie: CaseCaseStudyRef.value?.languageAbilitieRef.rows ? CaseCaseStudyRef.value?.languageAbilitieRef.rows : caseUser.value.case_study.languageAbilitie,
    //   capabilitiesSurrentStatus: CaseCaseStudyRef.value?.capabilitiesSurrentStatusRef.rows ? CaseCaseStudyRef.value?.capabilitiesSurrentStatusRef.rows : caseUser.value.case_study.capabilitiesSurrentStatus,
    // },
    psychological_study: CasePsychologicalStudyRef.value?.form ? CasePsychologicalStudyRef.value?.form : caseUser.value.psychological_study,
  }

  if(CaseCaseStudyRef.value?.form || caseUser.value.case_study?.form){
    data['case_study'] = {
      form : CaseCaseStudyRef.value?.form ? CaseCaseStudyRef.value?.form : caseUser.value.case_study.form,
      sensoryAbilities: CaseCaseStudyRef.value?.sensoryAbilitiesRef.rows ? CaseCaseStudyRef.value?.sensoryAbilitiesRef.rows : caseUser.value.case_study.sensoryAbilities,
      dailySkills: CaseCaseStudyRef.value?.dailySkillsRef.rows ? CaseCaseStudyRef.value?.dailySkillsRef.rows : caseUser.value.case_study.dailySkills,
      academicSkills: CaseCaseStudyRef.value?.academicSkillsRef.rows ? CaseCaseStudyRef.value?.academicSkillsRef.rows : caseUser.value.case_study.academicSkills,
      generalHealthCondition: CaseCaseStudyRef.value?.generalHealthConditionRef.rows ? CaseCaseStudyRef.value?.generalHealthConditionRef.rows : caseUser.value.case_study.generalHealthCondition,
      adaptiveBehaviorSkills: CaseCaseStudyRef.value?.adaptiveBehaviorSkillsRef.rows ? CaseCaseStudyRef.value?.adaptiveBehaviorSkillsRef.rows : caseUser.value.case_study.adaptiveBehaviorSkills,
      physicalMotorAbilities: CaseCaseStudyRef.value?.physicalMotorAbilitiesRef.rows ? CaseCaseStudyRef.value?.physicalMotorAbilitiesRef.rows : caseUser.value.case_study.physicalMotorAbilities,
      languageAbilitie: CaseCaseStudyRef.value?.languageAbilitieRef.rows ? CaseCaseStudyRef.value?.languageAbilitieRef.rows : caseUser.value.case_study.languageAbilitie,
      capabilitiesSurrentStatus: CaseCaseStudyRef.value?.capabilitiesSurrentStatusRef.rows ? CaseCaseStudyRef.value?.capabilitiesSurrentStatusRef.rows : caseUser.value.case_study.capabilitiesSurrentStatus,
    };
  }else{
    data['case_study'] = null;
  }

  if(data.image == false){
    delete data.image
  }

  const formData = new FormData();

  for (const key in data) {
    if (data.hasOwnProperty(key)) {
      const value = data[key];
      if (Array.isArray(value) || typeof value === 'object') {
        formData.append(key, JSON.stringify(value));
      } else {
        formData.append(key, value);
      }
    }
  }

  if(CaseGeneralInfoRef.value.file[0]){
    formData.append('image', CaseGeneralInfoRef.value.file[0]);
    formData.append('current_image', CaseGeneralInfoRef.value.currentImage);
  }

  casesReqest.update(formData, Number(route.params.id)).then(response => {
    if(response.data.success == true){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('case_updated'), 'success');
      setTimeout(() => {router.push(route.query.to ? String(route.query.to) : '/cases/list')}, 2000);
    }


  }).catch((e=>{
    const { errors: formErrors } = e.response.data
    if(formErrors.image){
      CaseGeneralInfoRef.value.imageValidMessage = formErrors.image[0]
    }
  }))
}

const mergeObjects = (oneArr, twoArr) => {
  const one = {...oneArr};
  const two = {...twoArr};

  for (let key in one) {
    if (
          !((Array.isArray(one[key]) && one[key].length === 0) ||
          (typeof one[key] === 'string' && one[key].trim() === ''))
      ) {
          two[key] = one[key];
      }
  }
  return two;
}

</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <VForm 
              ref="refVForm"
            >
        <VCard class="padding-30p">    
          <template v-slot:title>
            <VRow>
            <h3 class="v-col v-col-6 d-flex gap-4"> {{ $t('Register Case') }}</h3>

            <VCol
              cols="6"
              class="d-flex gap-4 justify-end"
            >
              <VBtn @click="save">
                {{ $t('Save') }}
              </VBtn>
            </VCol>
          </VRow>

        </template>
        <VCard class="border-1p">
          <v-card-text>
            <VTabs
              v-model="currentTab"
              class="v-tabs-pill"
            >
              <VTab>{{ $t('General Info') }}</VTab>
              <VTab>{{ $t('General Questions') }}</VTab>
              <VTab>{{ $t('Case Study') }}</VTab>
              <VTab>{{ $t('Psychological study') }}</VTab>
            </VTabs>
            </v-card-text>
            <VDivider />


            <VWindow class=""  v-model="currentTab">

              <VWindowItem>
                <CaseGeneralInfo ref="CaseGeneralInfoRef">
                </CaseGeneralInfo>
              </VWindowItem>
              
              <VWindowItem>
                <CaseGeneralQuestions @child-mounted="CaseGeneralQuestionsMounted" ref="CaseGeneralQuestionsRef"></CaseGeneralQuestions>
              </VWindowItem>

              <VWindowItem>
                <VCardText class="d-flex" v-if="!loaded">
                  <VProgressCircular
                    class="ma-auto"
                    :size="40"
                    color="primary"
                    indeterminate
                  />
                </VCardText>
                <CaseCaseStudy v-show="loaded" ref="CaseCaseStudyRef"  @child-mounted="childMounted"></CaseCaseStudy>
              </VWindowItem>

              <VWindowItem>
                <CasePsychologicalStudy @child-mounted="CasePsychologicalStudyMounted" ref="CasePsychologicalStudyRef"></CasePsychologicalStudy>
              </VWindowItem>
            </VWindow>
        </VCard>
        </VCard>
      </VForm>
      </VCol>
    </VRow>
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>
<route lang="yaml">
  meta:
    action: edit_cases
    subject: edit_cases
</route>