<script setup>
import add_case from '@images/svg/add_case.svg'
import show_cases from '@images/svg/show_cases.svg'
import questionnaire_icon from '@images/svg/questionnaire_icon.svg'
import { questionnairesApi } from "@/plugins/apis/questionnairesRequest";

const route = useRoute()
const router = useRouter()
const questionnaireListStore = questionnairesApi()
const questionnaireTask = ref()

// 👉 is Questionnaire Open
questionnaireListStore.isQuestionnaireOpen({}).then(response => {
  questionnaireTask.value = response.data.data
}).catch(error => {
  console.error(error)
})

</script>

<template>
  <VRow>
    <VCol       
        cols="12"
        md="12">
          <VRow>
            <VCol md="4">
              <VCard @click="router.push('/cases/list')" class="d-flex " height="250">
                <v-card-text class="d-flex justify-center align-center">
                  <div class="align-center text-center">
                    <VImg :src="show_cases" width="120" height="120" />
                    <div class="align-center"><h3>{{ $t('List Cases') }}</h3></div>
                  </div>
                </v-card-text>
              </VCard>
            </VCol>
            
            <VCol md="4" v-if="questionnaireTask">
              <VCard @click="router.push(`/questionnaires/questions/${questionnaireTask.id}`)" class="d-flex " height="250">
                <v-card-text class="d-flex justify-center align-center">
                  <div class="align-center text-center">
                    <VImg style="margin-right: 65px" :src="questionnaire_icon" width="120" height="120" />
                    <div class="align-center"><h3> الساده اولياء الأمور اضغط للاستبيان </h3></div>
                  </div>
                </v-card-text>
              </VCard>
            </VCol>
          </VRow>
      </VCol>
  </VRow>
</template>
<route lang="yaml">
  meta:
    action: parent
    subject: parent
</route>