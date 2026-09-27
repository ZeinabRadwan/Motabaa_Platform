<script setup>
import add_case from '@images/svg/add_case.svg'
import show_cases from '@images/svg/show_cases.svg'
import questionnaire_icon from '@images/svg/questionnaire_icon.svg'
import { questionnairesApi } from "@/plugins/apis/questionnairesRequest";
import { can } from '@layouts/plugins/casl'

const route = useRoute()
const router = useRouter()
const questionnaireListStore = questionnairesApi()
const questionnaireTask = ref()
const canCheckQuestionnaire = can('access_questionnaires', 'access_questionnaires')
  || can('show_questionnaires', 'show_questionnaires')
  || can('edit_questionnaires', 'edit_questionnaires')
  || can('admin_questionnaires', 'admin_questionnaires')

if (canCheckQuestionnaire) {
  questionnaireListStore.isQuestionnaireOpen({}).then(response => {
    questionnaireTask.value = response.data.data
  }).catch(() => {
    questionnaireTask.value = null
  })
}

</script>

<template>
  <VRow>
    <VCol       
        cols="12"
        md="12">
          <VRow>
            <VCol md="4">
              <VCard @click="router.push('/parent/center-activities')" class="d-flex " height="250">
                <v-card-text class="d-flex justify-center align-center">
                  <div class="align-center text-center">
                    <VIcon icon="tabler-calendar-event" size="80" color="primary" class="mb-3" />
                    <div class="align-center"><h3>{{ $t('center_activities.parent_title') }}</h3></div>
                  </div>
                </v-card-text>
              </VCard>
            </VCol>
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