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
const sections = ref([])
const answers = ref([])
const snackbarRef = ref(null);
const taskID = ref(true);
const questionnaireTitle = ref();
const isOpen = ref(true);
const isReadOnly = ref(false);
const canEditSubmission = ref(false);
const isSubmitting = ref(false);
const loadError = ref('');
const availabilityHint = ref('');

const resolveQuestionnaireApiMessage = message => {
  if (! message)
    return 'questionnaires.respond_load_failed'

  const markerMatch = String(message).match(/##([^&]+)&&/)
  if (markerMatch)
    return markerMatch[1]

  return message
}

// 👉 Fetch Questions
const applyQuestionnaireQuestions = data => {
  questions.value = data.questions ?? []
  sections.value = data.sections?.length
    ? data.sections
    : [{ id: null, title: '', questions: questions.value }]
  taskID.value = data.taskID
  questionnaireTitle.value = data.questionnaire_title
  canEditSubmission.value = Boolean(data.can_edit_submission)
  isReadOnly.value = Boolean(data.read_only)

  answers.value = []
  questions.value.forEach(question => {
    if (question.answer !== undefined && question.answer !== null)
      answers.value[question.id] = question.answer
  })
}

const sectionShowsTitle = section => Boolean(section?.title?.trim())

const fetchQuestions = () => {
  isOpen.value = true
  isReadOnly.value = false
  loadError.value = ''
  availabilityHint.value = ''
  questionnaireListStore.fetchQuestions({
    task_id: Number(route.params.id),
    publicAccess: true,
  }).then(response => {
    const data = response.data.data
    if (data?.questions) {
      applyQuestionnaireQuestions(data)

      return
    }

    isOpen.value = false
    loadError.value = 'questionnaires.Questionnaire closed'
  }).catch(error => {
    const apiMessage = error?.response?.data?.errors?.error?.[0]
    const status = error?.response?.status
    if ((status === 422 || status === 403) && apiMessage) {
      isOpen.value = false
      loadError.value = resolveQuestionnaireApiMessage(apiMessage)
      if (status === 422) {
        const availability = error?.response?.data?.availability
        if (availability?.starts_at && availability?.ends_at) {
          availabilityHint.value = `${availability.starts_at} — ${availability.ends_at} (${availability.today})`
        }
      }

      return
    }
    isOpen.value = false
    loadError.value = 'questionnaires.respond_load_failed'
  })
}

const legacyYesNoItems = () => {
  const items = [
    { title: 'Yes', value: 'yes' },
    { title: 'No', value: 'no' },
  ]

  return items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))
}

const normalizeQuestionOptions = question => {
  const raw = question?.options
  if (! Array.isArray(raw) || raw.length === 0)
    return null

  const labels = raw
    .map(option => (typeof option === 'string' ? option : (option?.title ?? option?.value ?? '')).trim())
    .filter(Boolean)

  return labels.length > 0 ? labels : null
}

const questionChoiceItems = question => {
  const labels = normalizeQuestionOptions(question)
  if (labels)
    return labels.map(label => ({ title: label, value: label }))

  return legacyYesNoItems()
}

const onSubmit = () => {
  if (isReadOnly.value || isSubmitting.value)
    return

  refVForm.value?.validate().then(({ valid: isValid }) => {
    if (! isValid)
      return

    const formData = new FormData()
    questions.value.forEach(question => {
      const value = answers.value[question.id]
      if (value !== undefined && value !== null && value !== '')
        formData.append(String(question.id), value)
    })

    isSubmitting.value = true
    questionnaireListStore.addAnswers(taskID.value, formData, { publicAccess: true }).then(response => {
      if (response.data['status']) {
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success')
        if (canEditSubmission.value || response.data.message === 'Updated successfully.') {
          fetchQuestions()
        }
        else {
          isReadOnly.value = true
          fetchQuestions()
          setTimeout(() => {
            router.push(route.query.to ? String(route.query.to) : '/dashboards/parent')
          }, window.timeOutAfterSubmit)
        }
      }
    }).catch(error => {
      const apiMessage = error?.response?.data?.errors?.error?.[0]
      if (error?.response?.status === 422 && apiMessage) {
        const resolved = resolveQuestionnaireApiMessage(apiMessage)
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t(resolved), 'error')
        if (resolved === 'questionnaires.Questionnaire already submitted') {
          isReadOnly.value = true
          fetchQuestions()
        }

        return
      }
    }).finally(() => {
      isSubmitting.value = false
    })
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
                {{ loadError ? $t(loadError) : $t('questionnaires.Questionnaire closed') }}
              </h4>
              <p
                v-if="availabilityHint"
                class="text-body-2 text-medium-emphasis mt-3 mb-0 text-center"
              >
                {{ availabilityHint }}
              </p>
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
                <VBtn
                  v-if="! isReadOnly"
                  type="submit"
                  :loading="isSubmitting"
                  :disabled="isSubmitting"
                >
                  {{ canEditSubmission ? $t('questionnaires.save_submission_changes') : $t('Save') }}
                </VBtn>
              </VCol>
            </VRow>
            <VAlert
              v-if="isReadOnly"
              type="info"
              variant="tonal"
              class="mb-4"
            >
              {{ $t('questionnaires.questionnaire_already_submitted_readonly') }}
            </VAlert>
            <VAlert
              v-else-if="canEditSubmission"
              type="info"
              variant="tonal"
              class="mb-4"
            >
              {{ $t('questionnaires.submission_editable_hint') }}
            </VAlert>
            <div
              v-for="(section, sectionIndex) in sections"
              :key="section.id ?? `section-${sectionIndex}`"
              class="mb-4"
            >
              <VCard
                v-if="sectionShowsTitle(section)"
                class="mb-3 questionnaire-respond-section-heading"
                variant="tonal"
                color="primary"
              >
                <VCardText class="py-3">
                  <h5 class="text-h6 font-weight-bold mb-0">
                    {{ section.title }}
                  </h5>
                </VCardText>
              </VCard>
              <VCard class="border-1p">
                <VCardText>
                  <VRow>
                    <VCol
                      v-for="question in section.questions"
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
                        :rules="isReadOnly ? [] : [question.required ? requiredValidator : null]"
                        :readonly="isReadOnly"
                        :disabled="isReadOnly"
                        class="pa-1"
                      />
                      <AppSelect
                        v-else-if="(question.type==2)"
                        v-model="answers[question.id]"
                        :items="questionChoiceItems(question)"
                        item-title="title"
                        item-value="value"
                        :menu-props="{ height: '400' }"
                        :rules="isReadOnly ? [] : [question.required ? requiredValidator : null]"
                        :disabled="isReadOnly"
                        :clearable="! isReadOnly"
                        clear-icon="tabler-x"
                        class="pa-1"
                      />
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </div>
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