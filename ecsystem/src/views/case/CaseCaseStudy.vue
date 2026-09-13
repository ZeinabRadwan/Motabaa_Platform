<script setup>
import i18n from '@/plugins/i18n/index.js';
import { useRoute, useRouter } from 'vue-router';
import {fieldsUpperSection, fieldsBottomSection, formData, sensoryAbilities,dailySkills,academicSkills,generalHealthCondition,adaptiveBehaviorSkills, physicalMotorAbilities ,languageAbilitie , capabilitiesSurrentStatus} from '@/views/case/fields/caseStudyFields';
import CaseStudyTables from '@/views/case/components/CaseStudyTables.vue';

const emit = defineEmits();
const route = useRoute()
const router = useRouter()
const refVForm = ref()

const form = ref(formData)


//capabilitiesSurrentStatusRef.value.rows >> get data
const capabilitiesSurrentStatusRef = ref(null);
const sensoryAbilitiesRef = ref(null);
const languageAbilitieRef = ref(null);
const physicalMotorAbilitiesRef = ref(null);
const adaptiveBehaviorSkillsRef = ref(null);
const dailySkillsRef = ref(null);
const academicSkillsRef = ref(null);
const generalHealthConditionRef = ref(null);



onMounted(() => {
  emit('child-mounted');
});

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){

    }
  })
}

defineExpose({
      capabilitiesSurrentStatusRef,
      sensoryAbilitiesRef,
      languageAbilitieRef,
      physicalMotorAbilitiesRef,
      adaptiveBehaviorSkillsRef,
      dailySkillsRef,
      academicSkillsRef,
      generalHealthConditionRef,
      form
})
</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <!-- 👉 Multiple Column -->
        <VForm 
          ref="refVForm"
          @submit.prevent="onSubmit"
        >
          <div v-for="(fields, key) in fieldsUpperSection" :key="key" >
            <VCardText v-if="key != 'initFields'">
                <h4 class="text-h4"> {{ $t(key) }}</h4>
            </VCardText>
            <VDivider v-if="key != 'initFields'" />
            <VCardText>
              <VRow>
                <VCol 
                  v-for="field in fields" 
                  :key="field.vModel" 
                  :cols="field.cols"
                  :md="field.md"
                >
                  <component :is="field.componentType" :label="$t(field.label)" v-model="form[key][field.vModel]" :clearable="field.componentType.name==='AppSelect'" > </component>
                </VCol>
              </VRow>
            </VCardText>
          </div>

          <VCardText>
                <h4 class="text-h4"> {{ $t('capabilitiesSurrentStatus') }}</h4>
            </VCardText>
          <VDivider />
          <CaseStudyTables ref="capabilitiesSurrentStatusRef" :rowsData="capabilitiesSurrentStatus"></CaseStudyTables>

          <VCardText>
                <h4 class="text-h4"> {{ $t('sensoryAbilities') }}</h4>
            </VCardText>
          <VDivider />
          <CaseStudyTables ref="sensoryAbilitiesRef" :rowsData="sensoryAbilities"></CaseStudyTables>

          <VCardText>
                <h4 class="text-h4"> {{ $t('languageAbilitie') }}</h4>
            </VCardText>
          <VDivider />
          <CaseStudyTables ref="languageAbilitieRef" :rowsData="languageAbilitie"></CaseStudyTables>

          <VCardText>
                <h4 class="text-h4"> {{ $t('physicalMotorAbilities') }}</h4>
            </VCardText>
          <VDivider />
          <CaseStudyTables ref="physicalMotorAbilitiesRef" :rowsData="physicalMotorAbilities"></CaseStudyTables>

          <VCardText>
                <h4 class="text-h4"> {{ $t('adaptiveBehaviorSkills') }}</h4>
            </VCardText>
          <VDivider />
          <CaseStudyTables ref="adaptiveBehaviorSkillsRef" :rowsData="adaptiveBehaviorSkills"></CaseStudyTables>

          <VCardText>
                <h4 class="text-h4"> {{ $t('dailySkills') }}</h4>
            </VCardText>
          <VDivider />
          <CaseStudyTables ref="dailySkillsRef" :rowsData="dailySkills"></CaseStudyTables>

          <VCardText>
                <h4 class="text-h4"> {{ $t('academicSkills') }}</h4>
            </VCardText>
          <VDivider />
          <CaseStudyTables ref="academicSkillsRef" :rowsData="academicSkills"></CaseStudyTables>

          <VCardText>
                <h4 class="text-h4"> {{ $t('generalHealthCondition') }}</h4>
            </VCardText>
          <VDivider />
          <CaseStudyTables ref="generalHealthConditionRef" :rowsData="generalHealthCondition"></CaseStudyTables>

          <div v-for="(fields, key) in fieldsBottomSection" :key="key" >
            <VCardText>
                <h4 class="text-h4"> {{ $t(key) }}</h4>
            </VCardText>
            <VDivider />
            <VCardText>
              <VRow>
                <VCol 
                  v-for="field in fields" 
                  :key="field.vModel" 
                  :cols="field.cols"
                  :md="field.md"
                >
                  <component :is="field.componentType" v-bind="field.hasOwnProperty('binds') ? field.binds : {}" :items="field.hasOwnProperty('items') ? field.items() : []" :label="$t(field.label)" v-model="form[key][field.vModel]" :clearable="field.componentType.name==='AppSelect'" > </component>
                </VCol>
              </VRow>
            </VCardText>
          </div>
      </VForm>
      </VCol>
    </VRow>


  </div>
</template>
