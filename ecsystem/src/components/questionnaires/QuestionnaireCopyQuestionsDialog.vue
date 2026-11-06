<script setup>
import i18n from '@/plugins/i18n'
import { questionnairesApi } from '@/plugins/apis/questionnairesRequest'
import { formatQuestionnaireBoundaryDate } from '@/utils/questionnaireDates'
import { can } from '@layouts/plugins/casl'
import { requiredValidator } from '@validators'
import { useRouter } from 'vue-router'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  terms: {
    type: Array,
    default: () => [],
  },
  initialDestination: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['update:modelValue', 'copied'])

const router = useRouter()
const store = questionnairesApi()

const STEP_SOURCE = 1
const STEP_DESTINATION = 2
const STEP_CONFIRM = 3
const STEP_DONE = 4

const step = ref(STEP_SOURCE)
const questionnaires = ref([])
const questionnairesLoading = ref(false)
const sourceSearch = ref('')
const sourceTask = ref(null)
const sourceSections = ref([])
const sourceLoading = ref(false)
const selectedIds = ref([])

const destinationType = ref('new')
const destinationTaskId = ref(null)
const refNewForm = ref()
const newTitle = ref('')
const newAudienceType = ref('parent')
const newTermId = ref(null)
const newRoleId = ref(null)
const newStartsAt = ref('')
const newEndsAt = ref('')
const newAllowParentEdit = ref(0)
const platformRoles = ref([])

const preview = ref(null)
const previewLoading = ref(false)
const skipDuplicates = ref(true)
const copying = ref(false)
const errorMessage = ref('')
const result = ref(null)

const datePickerConfig = { enableTime: false, dateFormat: 'Y-m-d' }

const isOpen = computed({
  get: () => props.modelValue,
  set: value => emit('update:modelValue', value),
})

const filteredSources = computed(() => {
  const needle = sourceSearch.value?.trim().toLowerCase()
  if (! needle)
    return questionnaires.value

  return questionnaires.value.filter(item => [item.title, item.term_title, item.target_role_name]
    .some(value => String(value ?? '').toLowerCase().includes(needle)))
})

const sourceQuestions = computed(() => sourceSections.value.flatMap(section => section.questions ?? []))

const allSelected = computed(() => sourceQuestions.value.length > 0 && selectedIds.value.length === sourceQuestions.value.length)

const someSelected = computed(() => selectedIds.value.length > 0 && ! allSelected.value)

const destinationOptions = computed(() => questionnaires.value
  .filter(item => sourceTask.value && Number(item.questionnaire_id) !== Number(sourceTask.value.questionnaire_id))
  .map(item => ({
    ...item,
    label: item.term_title ? `${item.title} — ${item.term_title}` : item.title,
  })))

const destinationTask = computed(() => questionnaires.value.find(item => Number(item.id) === Number(destinationTaskId.value)) ?? null)

const destinationTitle = computed(() => destinationType.value === 'new'
  ? newTitle.value
  : (preview.value?.destination?.title ?? destinationTask.value?.title ?? ''))

const duplicateIds = computed(() => new Set((preview.value?.duplicates ?? []).map(row => Number(row.source_question_id))))

const effectiveCopyCount = computed(() => {
  if (destinationType.value === 'existing' && skipDuplicates.value)
    return selectedIds.value.filter(id => ! duplicateIds.value.has(Number(id))).length

  return selectedIds.value.length
})

const canOpenDestination = computed(() => can('show_questionnaires', 'show_questionnaires'))

const audienceTypeOptions = computed(() => [
  { value: 'parent', title: i18n.global.t('questionnaires.target_audience_parent') },
  { value: 'role', title: i18n.global.t('questionnaires.target_audience_role') },
])

const questionTypeLabel = question => {
  if (Number(question.type) !== 2)
    return i18n.global.t('questionnaires.question_type_short_text')

  return question.options?.length
    ? i18n.global.t('questionnaires.question_type_multiple_choice')
    : i18n.global.t('questionnaires.copy_questions_type_yes_no')
}

const questionOptionLabels = question => {
  if (Number(question.type) !== 2)
    return []

  return question.options?.length
    ? question.options
    : [i18n.global.t('Yes'), i18n.global.t('No')]
}

const extractErrorMessage = error => {
  const errors = error?.response?.data?.errors
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors).flat().find(Boolean)
    if (first)
      return typeof first === 'string' ? first : (first.title ?? '')
  }

  return error?.response?.data?.message || i18n.global.t('questionnaires.copy_questions_failed')
}

const resetState = () => {
  step.value = STEP_SOURCE
  sourceSearch.value = ''
  sourceTask.value = null
  sourceSections.value = []
  selectedIds.value = []
  destinationType.value = props.initialDestination ? 'existing' : 'new'
  destinationTaskId.value = props.initialDestination?.id ?? null
  newTitle.value = ''
  newAudienceType.value = 'parent'
  newTermId.value = null
  newRoleId.value = null
  newStartsAt.value = ''
  newEndsAt.value = ''
  newAllowParentEdit.value = 0
  preview.value = null
  skipDuplicates.value = true
  errorMessage.value = ''
  result.value = null
}

const loadQuestionnaires = () => {
  questionnairesLoading.value = true
  store.fetchQuestionnaires({
    status: 'active',
    page: 1,
    options: { itemsPerPage: 100 },
  }).then(response => {
    questionnaires.value = response.data?.data ?? []
  }).catch(() => {
    questionnaires.value = []
  }).finally(() => {
    questionnairesLoading.value = false
  })
}

const loadRoles = () => {
  store.fetchTargetAudienceRoles().then(response => {
    platformRoles.value = (response.data?.data ?? []).map(role => ({
      ...role,
      title: role.default_name ? i18n.global.t(role.default_name) : role.name,
    }))
  }).catch(() => {
    platformRoles.value = []
  })
}

const selectSource = item => {
  if (Number(sourceTask.value?.id) === Number(item.id))
    return

  sourceTask.value = item
  sourceSections.value = []
  selectedIds.value = []
  preview.value = null
  errorMessage.value = ''
  if (Number(destinationTask.value?.questionnaire_id) === Number(item.questionnaire_id))
    destinationTaskId.value = null

  sourceLoading.value = true
  store.fetchQuestionnaire(item.id).then(response => {
    const data = response.data?.data

    sourceSections.value = data?.sections?.length
      ? data.sections
      : [{ id: null, title: '', questions: data?.questions ?? [] }]
    selectedIds.value = sourceQuestions.value.map(question => question.id)
  }).catch(error => {
    errorMessage.value = extractErrorMessage(error)
  }).finally(() => {
    sourceLoading.value = false
  })
}

const toggleAll = value => {
  selectedIds.value = value ? sourceQuestions.value.map(question => question.id) : []
}

const sectionQuestionIds = section => (section.questions ?? []).map(question => question.id)

const isSectionFullySelected = section => {
  const ids = sectionQuestionIds(section)

  return ids.length > 0 && ids.every(id => selectedIds.value.includes(id))
}

const isSectionPartiallySelected = section => {
  const ids = sectionQuestionIds(section)

  return ! isSectionFullySelected(section) && ids.some(id => selectedIds.value.includes(id))
}

const toggleSection = (section, value) => {
  const ids = sectionQuestionIds(section)
  const others = selectedIds.value.filter(id => ! ids.includes(id))

  selectedIds.value = value ? [...others, ...ids] : others
}

const basePayload = () => ({
  question_ids: sourceQuestions.value.map(question => question.id).filter(id => selectedIds.value.includes(id)),
  destination_type: destinationType.value,
  destination_task_id: destinationType.value === 'existing' ? destinationTaskId.value : null,
})

const goToDestination = () => {
  errorMessage.value = ''
  step.value = STEP_DESTINATION
}

const goToConfirm = async () => {
  errorMessage.value = ''
  if (destinationType.value === 'new') {
    const { valid } = await refNewForm.value.validate()
    if (! valid)
      return
  }
  else if (! destinationTaskId.value) {
    return
  }

  previewLoading.value = true
  try {
    const response = await store.previewCopyQuestions(sourceTask.value.id, basePayload())

    preview.value = response.data?.data ?? null
    skipDuplicates.value = (preview.value?.duplicate_count ?? 0) > 0
    step.value = STEP_CONFIRM
  }
  catch (error) {
    errorMessage.value = extractErrorMessage(error)
  }
  finally {
    previewLoading.value = false
  }
}

const submitCopy = async () => {
  errorMessage.value = ''
  copying.value = true

  const payload = { ...basePayload(), skip_duplicates: skipDuplicates.value }
  if (destinationType.value === 'new') {
    Object.assign(payload, {
      title: newTitle.value,
      target_audience_type: newAudienceType.value,
      term_id: newAudienceType.value === 'parent' ? newTermId.value : null,
      target_role_id: newAudienceType.value === 'role' ? newRoleId.value : null,
      starts_at: formatQuestionnaireBoundaryDate(newStartsAt.value),
      ends_at: formatQuestionnaireBoundaryDate(newEndsAt.value),
      allow_parent_edit_after_submit: newAllowParentEdit.value ? 1 : 0,
    })
  }

  try {
    const response = await store.copyQuestions(sourceTask.value.id, payload)

    result.value = response.data?.data ?? null
    step.value = STEP_DONE
    emit('copied', result.value)
  }
  catch (error) {
    errorMessage.value = extractErrorMessage(error)
  }
  finally {
    copying.value = false
  }
}

const openDestination = () => {
  if (! result.value?.task_id)
    return

  isOpen.value = false
  router.push(`/questionnaires/view/${result.value.task_id}`)
}

watch(() => props.modelValue, open => {
  if (! open)
    return

  resetState()
  loadQuestionnaires()
  loadRoles()
})

watch(destinationTaskId, () => {
  preview.value = null
})

watch(destinationType, () => {
  preview.value = null
  errorMessage.value = ''
})

watch(newAudienceType, type => {
  if (type === 'role')
    newTermId.value = null
  else
    newRoleId.value = null
})
</script>

<template>
  <VDialog
    v-model="isOpen"
    persistent
    scrollable
    class="questionnaire-copy-dialog"
    :max-width="880"
    :fullscreen="$vuetify.display.xs"
  >
    <DialogCloseBtn @click="isOpen = false" />

    <VCard class="questionnaire-copy-dialog__card">
      <VCardItem class="pb-2">
        <template #prepend>
          <VAvatar
            color="primary"
            variant="tonal"
            size="40"
            rounded
          >
            <VIcon
              icon="tabler-copy"
              size="22"
            />
          </VAvatar>
        </template>
        <VCardTitle class="text-h5 font-weight-bold">
          {{ $t('questionnaires.copy_questions') }}
        </VCardTitle>
        <VCardSubtitle class="text-wrap">
          {{ $t('questionnaires.copy_questions_subtitle') }}
        </VCardSubtitle>
      </VCardItem>

      <div
        v-if="step !== STEP_DONE"
        class="d-flex flex-wrap gap-2 px-6 pb-3"
      >
        <VChip
          v-for="(label, index) in [
            $t('questionnaires.copy_questions_step_source'),
            $t('questionnaires.copy_questions_step_destination'),
            $t('questionnaires.copy_questions_step_confirm'),
          ]"
          :key="label"
          :color="step === index + 1 ? 'primary' : 'default'"
          :variant="step === index + 1 ? 'flat' : 'tonal'"
          size="small"
          label
        >
          {{ index + 1 }}. {{ label }}
        </VChip>
      </div>

      <VDivider />

      <VCardText class="questionnaire-copy-dialog__body">
        <VAlert
          v-if="errorMessage"
          type="error"
          variant="tonal"
          density="compact"
          class="mb-4"
          closable
          @click:close="errorMessage = ''"
        >
          {{ errorMessage }}
        </VAlert>

        <!-- Step 1: source + question selection -->
        <VRow v-if="step === STEP_SOURCE">
          <VCol
            cols="12"
            md="5"
          >
            <div class="text-subtitle-2 font-weight-bold mb-2">
              {{ $t('questionnaires.copy_questions_source') }}
            </div>
            <AppTextField
              v-model="sourceSearch"
              :placeholder="$t('questionnaires.copy_questions_search_source')"
              prepend-inner-icon="tabler-search"
              density="compact"
              clearable
              class="mb-2"
            />
            <VProgressLinear
              v-if="questionnairesLoading"
              indeterminate
            />
            <VList
              class="questionnaire-copy-dialog__list border rounded"
              density="compact"
            >
              <VListItem
                v-for="item in filteredSources"
                :key="item.id"
                :active="Number(sourceTask?.id) === Number(item.id)"
                color="primary"
                @click="selectSource(item)"
              >
                <VListItemTitle class="text-wrap">
                  {{ item.title }}
                </VListItemTitle>
                <VListItemSubtitle class="text-wrap">
                  {{ item.target_audience_type === 'role' ? item.target_role_name : item.term_title }}
                  <span v-if="item.starts_at"> · {{ item.starts_at }} — {{ item.ends_at }}</span>
                </VListItemSubtitle>
              </VListItem>
              <VListItem v-if="! questionnairesLoading && filteredSources.length === 0">
                <VListItemTitle class="text-medium-emphasis text-wrap">
                  {{ $t('questionnaires.copy_questions_no_sources') }}
                </VListItemTitle>
              </VListItem>
            </VList>
          </VCol>

          <VCol
            cols="12"
            md="7"
          >
            <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
              <div class="text-subtitle-2 font-weight-bold">
                {{ $t('questionnaires.questions_label') }}
              </div>
              <VChip
                v-if="sourceTask"
                size="small"
                color="primary"
                label
              >
                {{ $t('questionnaires.copy_questions_selected_count', { count: selectedIds.length, total: sourceQuestions.length }) }}
              </VChip>
            </div>

            <VProgressLinear
              v-if="sourceLoading"
              indeterminate
            />

            <div
              v-if="! sourceTask"
              class="questionnaire-copy-dialog__empty border rounded text-medium-emphasis"
            >
              {{ $t('questionnaires.copy_questions_pick_source_hint') }}
            </div>

            <div
              v-else-if="! sourceLoading"
              class="questionnaire-copy-dialog__list border rounded pa-2"
            >
              <template v-if="sourceQuestions.length">
                <div class="d-flex align-center flex-wrap gap-2 mb-1">
                  <VCheckbox
                    :model-value="allSelected"
                    :indeterminate="someSelected"
                    :label="$t('questionnaires.copy_questions_select_all')"
                    hide-details
                    density="compact"
                    @update:model-value="toggleAll"
                  />
                  <VSpacer />
                  <VBtn
                    variant="text"
                    size="small"
                    :disabled="selectedIds.length === 0"
                    @click="toggleAll(false)"
                  >
                    {{ $t('questionnaires.copy_questions_deselect_all') }}
                  </VBtn>
                </div>
                <VDivider class="mb-2" />

                <div
                  v-for="(section, sectionIndex) in sourceSections"
                  :key="section.id ?? `flat-${sectionIndex}`"
                  class="mb-2"
                >
                  <VCheckbox
                    v-if="section.title && sourceSections.length > 1"
                    :model-value="isSectionFullySelected(section)"
                    :indeterminate="isSectionPartiallySelected(section)"
                    hide-details
                    density="compact"
                    class="font-weight-bold"
                    @update:model-value="value => toggleSection(section, value)"
                  >
                    <template #label>
                      <span class="font-weight-bold">{{ section.title }}</span>
                    </template>
                  </VCheckbox>

                  <div
                    v-for="question in section.questions"
                    :key="question.id"
                    class="questionnaire-copy-dialog__question d-flex align-start gap-2 py-1"
                    :class="{ 'ps-6': section.title && sourceSections.length > 1 }"
                  >
                    <VCheckbox
                      v-model="selectedIds"
                      :value="question.id"
                      hide-details
                      density="compact"
                      class="flex-grow-0"
                    />
                    <div class="flex-grow-1 pt-2">
                      <div class="text-body-1 text-wrap">
                        {{ question.title }}
                      </div>
                      <div class="d-flex flex-wrap gap-1 mt-1">
                        <VChip
                          size="x-small"
                          label
                          variant="tonal"
                        >
                          {{ questionTypeLabel(question) }}
                        </VChip>
                        <VChip
                          v-if="Number(question.required)"
                          size="x-small"
                          label
                          color="error"
                          variant="tonal"
                        >
                          {{ $t('questionnaires.copy_questions_required') }}
                        </VChip>
                        <VChip
                          v-for="(option, optionIndex) in questionOptionLabels(question)"
                          :key="`${question.id}-${optionIndex}`"
                          size="x-small"
                          variant="outlined"
                        >
                          {{ option }}
                        </VChip>
                      </div>
                    </div>
                  </div>
                </div>
              </template>
              <div
                v-else
                class="text-medium-emphasis pa-4"
              >
                {{ $t('questionnaires.copy_questions_no_questions') }}
              </div>
            </div>
          </VCol>
        </VRow>

        <!-- Step 2: destination -->
        <div v-else-if="step === STEP_DESTINATION">
          <div class="text-subtitle-2 font-weight-bold mb-2">
            {{ $t('questionnaires.copy_questions_destination') }}
          </div>
          <VRadioGroup
            v-model="destinationType"
            inline
            hide-details
            class="mb-4"
          >
            <VRadio
              value="new"
              :label="$t('questionnaires.copy_questions_destination_new')"
            />
            <VRadio
              value="existing"
              :label="$t('questionnaires.copy_questions_destination_existing')"
            />
          </VRadioGroup>

          <VForm
            v-if="destinationType === 'new'"
            ref="refNewForm"
            @submit.prevent="goToConfirm"
          >
            <VRow dense>
              <VCol cols="12">
                <AppTextField
                  v-model="newTitle"
                  :label="$t('questionnaires.questionnaire_name')"
                  :rules="[requiredValidator]"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <div class="text-body-2 font-weight-medium mb-2">
                  {{ $t('questionnaires.target_audience_label') }}
                </div>
                <VRadioGroup
                  v-model="newAudienceType"
                  inline
                  hide-details
                >
                  <VRadio
                    v-for="option in audienceTypeOptions"
                    :key="option.value"
                    :label="option.title"
                    :value="option.value"
                  />
                </VRadioGroup>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <AppSelect
                  v-if="newAudienceType === 'parent'"
                  v-model="newTermId"
                  :items="terms"
                  item-title="title"
                  item-value="id"
                  clearable
                  clear-icon="tabler-x"
                  :label="$t('choose term')"
                  :rules="[requiredValidator]"
                />
                <AppAutocomplete
                  v-else
                  v-model="newRoleId"
                  :items="platformRoles"
                  item-title="title"
                  item-value="id"
                  :label="$t('questionnaires.target_role_label')"
                  :rules="[requiredValidator]"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="newStartsAt"
                  :config="datePickerConfig"
                  :label="$t('questionnaires.availability_start_label')"
                  :rules="[requiredValidator]"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="newEndsAt"
                  :config="datePickerConfig"
                  :label="$t('questionnaires.availability_end_label')"
                  :rules="[requiredValidator]"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <div class="text-body-2 font-weight-medium mb-2">
                  {{ $t('questionnaires.allow_parent_edit_after_submit') }}
                </div>
                <VRadioGroup
                  v-model="newAllowParentEdit"
                  inline
                  hide-details
                >
                  <VRadio
                    :label="$t('questionnaires.allow_parent_edit_yes')"
                    :value="1"
                  />
                  <VRadio
                    :label="$t('questionnaires.allow_parent_edit_no')"
                    :value="0"
                  />
                </VRadioGroup>
              </VCol>
            </VRow>
          </VForm>

          <div v-else>
            <AppAutocomplete
              v-model="destinationTaskId"
              :items="destinationOptions"
              item-title="label"
              item-value="id"
              :loading="questionnairesLoading"
              :label="$t('questionnaires.copy_questions_choose_destination')"
              :no-data-text="$t('questionnaires.copy_questions_no_destinations')"
              clearable
            />
            <div class="text-body-2 text-medium-emphasis mt-2">
              {{ $t('questionnaires.copy_questions_existing_hint') }}
            </div>
          </div>
        </div>

        <!-- Step 3: confirmation -->
        <div v-else-if="step === STEP_CONFIRM">
          <VList
            class="border rounded mb-4"
            density="compact"
          >
            <VListItem prepend-icon="tabler-file-export">
              <VListItemSubtitle>{{ $t('questionnaires.copy_questions_source') }}</VListItemSubtitle>
              <VListItemTitle class="text-wrap">
                {{ sourceTask?.title }}
              </VListItemTitle>
            </VListItem>
            <VListItem prepend-icon="tabler-file-import">
              <VListItemSubtitle>
                {{ destinationType === 'new' ? $t('questionnaires.copy_questions_destination_new') : $t('questionnaires.copy_questions_destination_existing') }}
              </VListItemSubtitle>
              <VListItemTitle class="text-wrap">
                {{ destinationTitle }}
              </VListItemTitle>
            </VListItem>
            <VListItem prepend-icon="tabler-list-check">
              <VListItemSubtitle>{{ $t('questionnaires.copy_questions_summary_count') }}</VListItemSubtitle>
              <VListItemTitle>
                {{ $t('questionnaires.copy_questions_summary_value', { count: effectiveCopyCount, total: selectedIds.length }) }}
              </VListItemTitle>
            </VListItem>
          </VList>

          <VAlert
            v-if="preview?.duplicate_count"
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            <div class="font-weight-medium mb-2">
              {{ $t('questionnaires.copy_questions_duplicates_found', { count: preview.duplicate_count }) }}
            </div>
            <ul class="ps-4 mb-2">
              <li
                v-for="row in preview.duplicates"
                :key="row.source_question_id"
                class="text-wrap"
              >
                {{ row.source_title }}
              </li>
            </ul>
            <VCheckbox
              v-model="skipDuplicates"
              :label="$t('questionnaires.copy_questions_skip_duplicates')"
              hide-details
              density="compact"
            />
          </VAlert>

          <VAlert
            v-if="preview?.destination?.has_responses"
            type="info"
            variant="tonal"
            density="compact"
            class="mb-3"
          >
            {{ $t('questionnaires.copy_questions_destination_has_responses') }}
          </VAlert>

          <VAlert
            v-if="(preview?.destination?.shared_task_count ?? 0) > 1"
            type="info"
            variant="tonal"
            density="compact"
            class="mb-3"
          >
            {{ $t('questionnaires.copy_questions_destination_shared_tasks', { count: preview.destination.shared_task_count }) }}
          </VAlert>

          <div class="text-body-2 text-medium-emphasis">
            {{ $t('questionnaires.copy_questions_responses_not_copied') }}
          </div>
        </div>

        <!-- Step 4: done -->
        <div
          v-else
          class="text-center py-6"
        >
          <VAvatar
            color="success"
            variant="tonal"
            size="64"
            class="mb-4"
          >
            <VIcon
              icon="tabler-circle-check"
              size="36"
            />
          </VAvatar>
          <div class="text-h6 mb-2">
            {{ $t('questionnaires.copy_questions_success', { count: result?.copied_count ?? 0 }) }}
          </div>
          <div
            v-if="result?.skipped_count"
            class="text-body-2 text-medium-emphasis"
          >
            {{ $t('questionnaires.copy_questions_skipped', { count: result.skipped_count }) }}
          </div>
        </div>
      </VCardText>

      <VDivider />

      <VCardActions class="questionnaire-copy-dialog__actions flex-wrap gap-2 pa-4">
        <template v-if="step === STEP_DONE">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isOpen = false"
          >
            {{ $t('Close') }}
          </VBtn>
          <VBtn
            v-if="canOpenDestination"
            color="primary"
            variant="flat"
            prepend-icon="tabler-external-link"
            @click="openDestination"
          >
            {{ $t('questionnaires.copy_questions_open_destination') }}
          </VBtn>
        </template>
        <template v-else>
          <VBtn
            v-if="step > STEP_SOURCE"
            color="secondary"
            variant="outlined"
            :disabled="copying || previewLoading"
            @click="step -= 1"
          >
            {{ $t('questionnaires.copy_questions_back') }}
          </VBtn>
          <VBtn
            v-else
            color="secondary"
            variant="outlined"
            @click="isOpen = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VSpacer />
          <VBtn
            v-if="step === STEP_SOURCE"
            color="primary"
            variant="flat"
            :disabled="selectedIds.length === 0"
            @click="goToDestination"
          >
            {{ $t('questionnaires.copy_questions_next') }}
          </VBtn>
          <VBtn
            v-else-if="step === STEP_DESTINATION"
            color="primary"
            variant="flat"
            :loading="previewLoading"
            :disabled="destinationType === 'existing' && ! destinationTaskId"
            @click="goToConfirm"
          >
            {{ $t('questionnaires.copy_questions_next') }}
          </VBtn>
          <VBtn
            v-else
            color="primary"
            variant="flat"
            prepend-icon="tabler-copy"
            :loading="copying"
            :disabled="effectiveCopyCount === 0"
            @click="submitCopy"
          >
            {{ $t('questionnaires.copy_questions_confirm', { count: effectiveCopyCount }) }}
          </VBtn>
        </template>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style lang="scss">
.questionnaire-copy-dialog__body {
  min-block-size: 320px;
}

.questionnaire-copy-dialog__list {
  max-block-size: 420px;
  overflow-y: auto;
}

.questionnaire-copy-dialog__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  min-block-size: 200px;
  padding: 1.5rem;
  text-align: center;
}

.questionnaire-copy-dialog__question + .questionnaire-copy-dialog__question {
  border-block-start: thin dashed rgba(var(--v-border-color), var(--v-border-opacity));
}

@media (max-width: 600px) {
  .questionnaire-copy-dialog__list {
    max-block-size: none;
  }
}
</style>
