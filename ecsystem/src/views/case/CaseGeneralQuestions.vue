<script setup>
import i18n from '@/plugins/i18n/index.js';
import {general_questions} from '@/views/case/fields/refFiledsTabs';

import { useRoute, useRouter } from 'vue-router';

const emit = defineEmits();
const route = useRoute()
const router = useRouter()
const refVForm = ref()
import {
  economicFamily,
  familyStatus
} from './fields/selectsItems';

const form = ref(general_questions)

defineExpose({
  form
})



const birthTypeItems = () => {
  let types = [
    {
      title: 'Vaginal delivery',
      value: 'Vaginal delivery',
    },
    {
      title: 'Cesarean birth',
      value: 'Cesarean birth',
    },
  ]
  let translated = types.map(type => ({
    ...type,
    title: i18n.global.t(type.title),
  }));

  return translated;
}

onMounted(() => {
  emit('child-mounted');
});

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){

    }
  })
}
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
            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  md="4"
                >
                <AppSelect
                    :items="familyStatus()"
                    v-model="form.familyStatus"
                    :label="$t('The social status of the family')"
                />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                <AppSelect
                    :items="economicFamily()"
                    v-model="form.economicFamilyStatus"
                    :label="$t('The economic condition of the family')"
                />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="form.momHealthChildbirth"
                    :label="$t('Maternal health during childbirth')"
                    :placeholder="$t('Maternal health during childbirth')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="form.motherAgeAtBirth"
                    :label="$t('Mother\'s age at birth')"
                    :placeholder="$t('Mother\'s age at birth')"
                  />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                <AppSelect
                    :items="birthTypeItems()"
                    v-model="form.birthType"
                    :label="$t('Birth type')"
                />
                </VCol>
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="form.babyWeigh"
                    :label="$t('Baby weight at birth')"
                    :placeholder="$t('Baby weight at birth')"
                  />
                </VCol>
                
                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="form.growthDuringBirth"
                    :label="$t('The child\'s growth during birth')"
                    :placeholder="$t('The child\'s growth during birth')"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                <AppSelect
                    :items="economicFamily()"
                    v-model="form.motorGrowth"
                    :label="$t('Motor growth')"
                />
                </VCol>

                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="form.surgeriesChild"
                    :label="$t('The surgeries that the child underwent?')"
                    :placeholder="$t('The surgeries that the child underwent?')"
                  />
                </VCol>


                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="form.diseasesChildSuffer"
                    :label="$t('What diseases does the child suffer from?')"
                    :placeholder="$t('What diseases does the child suffer from?')"
                  />
                </VCol>


                <VCol
                  cols="12"
                  md="4"
                >
                  <AppTextField
                    v-model="form.usedMedicines"
                    :label="$t('Used medicines')"
                    :placeholder="$t('Used medicines')"
                  />
                </VCol>



              </VRow>
            </VCardText>
      </VForm>
      </VCol>
    </VRow>


  </div>
</template>
