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


const childMounted = () => {
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

  const data ={
    center_id: Number(localStorage.getItem('center')),
    disability_type_ids: CaseGeneralInfoRef.value.disability,
    period: CaseGeneralInfoRef.value.period,
    services_provided: CaseGeneralInfoRef.value.serviceVal,
    beneficiary_number: CaseGeneralInfoRef.value.insuranceNumber == null ? '' : CaseGeneralInfoRef.value.insuranceNumber,
    name: CaseGeneralInfoRef.value.caseName,
    // image: CaseGeneralInfoRef.value.file[0],
    teacher_id: CaseGeneralInfoRef.value.teacher,
    physiotherapist_id: CaseGeneralInfoRef.value.physiotherapist == null ? '' : CaseGeneralInfoRef.value.physiotherapist,
    occupational_therapy_id: CaseGeneralInfoRef.value.occupational == null ? '' : CaseGeneralInfoRef.value.occupational,
    emergency_contact: CaseGeneralInfoRef.value.emergency_contact,
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
    general_questions: CaseGeneralQuestionsRef.value?.form ? CaseGeneralQuestionsRef.value?.form : general_questions,
    case_study: {
      form : CaseCaseStudyRef.value?.form ? CaseCaseStudyRef.value?.form : case_study, 
      sensoryAbilities: CaseCaseStudyRef.value?.sensoryAbilitiesRef.rows ? CaseCaseStudyRef.value?.sensoryAbilitiesRef.rows : sensoryAbilities,
      dailySkills: CaseCaseStudyRef.value?.dailySkillsRef.rows ? CaseCaseStudyRef.value?.dailySkillsRef.rows : dailySkills,
      academicSkills: CaseCaseStudyRef.value?.academicSkillsRef.rows ? CaseCaseStudyRef.value?.academicSkillsRef.rows : academicSkills,
      generalHealthCondition: CaseCaseStudyRef.value?.generalHealthConditionRef.rows ? CaseCaseStudyRef.value?.generalHealthConditionRef.rows : generalHealthCondition,
      adaptiveBehaviorSkills: CaseCaseStudyRef.value?.adaptiveBehaviorSkillsRef.rows ? CaseCaseStudyRef.value?.adaptiveBehaviorSkillsRef.rows : adaptiveBehaviorSkills, 
      physicalMotorAbilities: CaseCaseStudyRef.value?.physicalMotorAbilitiesRef.rows ? CaseCaseStudyRef.value?.physicalMotorAbilitiesRef.rows : physicalMotorAbilities,
      languageAbilitie: CaseCaseStudyRef.value?.languageAbilitieRef.rows ? CaseCaseStudyRef.value?.languageAbilitieRef.rows : languageAbilitie, 
      capabilitiesSurrentStatus: CaseCaseStudyRef.value?.capabilitiesSurrentStatusRef.rows ? CaseCaseStudyRef.value?.capabilitiesSurrentStatusRef.rows : capabilitiesSurrentStatus,
    },
    psychological_study: CasePsychologicalStudyRef.value?.form ? CasePsychologicalStudyRef.value?.form : psychological_study,
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
  }

  casesReqest.add(formData).then(response => {
    if(response.data.success == true){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('case_created'), 'success');
      setTimeout(() => {router.push(route.query.to ? String(route.query.to) : '/cases/list')}, 2000);
    }


  }).catch((e=>{
    const { errors: formErrors } = e.response.data
    if(formErrors.image){
      CaseGeneralInfoRef.value.imageValidMessage = formErrors.image[0]
    }
  }))
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
                <CaseGeneralQuestions ref="CaseGeneralQuestionsRef"></CaseGeneralQuestions>
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
                <CasePsychologicalStudy ref="CasePsychologicalStudyRef"></CasePsychologicalStudy>
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