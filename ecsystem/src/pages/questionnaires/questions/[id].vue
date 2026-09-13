<script setup>
import { useRoute, useRouter } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import {isLogin} from "@core/utils/helper";
import { questionnairesApi } from "@/plugins/apis/questionnairesRequest";
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import {
requiredValidator
} from '@validators';

const route = useRoute()
const router = useRouter()
const questionnaireListStore = questionnairesApi()
const refVForm = ref()
const questions = ref([])
const answers = ref([])
const snackbarRef = ref(null);
const isNew = ref(true);
const taskID = ref(true);
const questionnaireTitle = ref();
const isOpen = ref(true);

// 👉 Fetch Questions
const fetchQuestions = () => {
  questionnaireListStore.fetchQuestions({
    task_id: Number(route.params.id)
  }).then(response => {
    var data = response.data.data
    if(data.questions) {

      questions.value = data.questions
      taskID.value = data.taskID
      questionnaireTitle.value = data.questionnaire_title
    }else {
      isOpen.value = false
    }
    questions.value.forEach(element => {
      if(element.answer) {
        isNew.value = false
        answers.value[element.id] = element.answer
      }
    })
  }).catch(() => {
    isOpen.value = false
  })
}

const secondType = () => {
  let items = [
    {
      title: 'Yes',
      value: 'yes',
    },
    {
      title: 'No',
      value: 'no',
    },
  ]
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))

  return translatedItems;
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){
      const formData = new FormData();
      answers.value.map(function(value, key) {
        formData.append(key, value);
      });

      questionnaireListStore.addAnswers(taskID.value, formData).then(response => {
        if(response.data['status']){
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
          setTimeout(() => {router.push(route.query.to ? String(route.query.to) : '/dashboards/parent')}, window.timeOutAfterSubmit);
        }
      }).catch((e=>{
        const { errors: formErrors } = e.response.data
        errors.value = formErrors
      }))
    }
  })
}

watchEffect(fetchQuestions)
</script>

<template>
  <div>
    <VRow>
      <VCol cols="12" v-if="!isOpen">
        <VCard class="padding-30p"> 
          <VCardText class="">
            <div>
              <h4 class="font-weight-bold text-capitalize text-h4" color="primary" style="text-align: center;">
                {{ $t('questionnaires.Questionnaire closed') }}
              </h4>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" v-else>
        <!-- 👉 Multiple Column -->
        <VForm 
          ref="refVForm"
          @submit.prevent="onSubmit"
        >
          <VCard class="padding-30p"> 
            <VRow class="mb-2">
              <VCol
                cols="8"
                class="d-flex gap-4"
              >
                <span class="text-h5">{{ questionnaireTitle }}</span>
              </VCol>
              <VCol
                cols="4"
                class="d-flex gap-4 justify-end"
              >
                <VBtn type="submit" v-if="isNew">
                  {{ $t('Save') }}
                </VBtn>
                <VBtn type="submit" v-if="!isNew">
                  {{ $t('Update') }}
                </VBtn>
              </VCol>
            </VRow>
            <VCard class="border-1p">
              <VCardText>
                <VRow>
                  <VCol
                    v-for="question in questions"
                    :key="question.id"
                    cols="12"
                    sm="12"
                  >
                  <VLabel
                    :for="answers[question.id]"
                    class="mb-1 text-body-2 text-high-emphasis"
                    style="white-space: pre-wrap;"
                    :text="question.title"
                  />
                    <AppTextarea
                      v-if="(question.type==1)"
                      v-model="answers[question.id]"
                      :rules="[question.required ? requiredValidator : null]"
                      class="pa-1"
                    />
                    <AppSelect
                      v-else-if="(question.type==2)"
                      v-model="answers[question.id]"
                      :items="secondType()"
                      :menu-props="{ height: '400' }"
                      :rules="[question.required ? requiredValidator : null]"
                      clearable
                      clear-icon="tabler-x"
                      class="pa-1"
                    />
                  </VCol>
                </VRow>
              </VCardText>
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
  layout: blank
  action: read
  subject: Auth
</route>