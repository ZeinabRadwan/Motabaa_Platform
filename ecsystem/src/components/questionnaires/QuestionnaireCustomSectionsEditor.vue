<script setup>
import i18n from '@/plugins/i18n/index.js'
import { requiredValidator } from '@validators'

const props = defineProps({
  modelValue: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(['update:modelValue'])

const touchSections = () => {
  emit('update:modelValue', [...props.modelValue])
}

let optionUid = 0

const questionTypeItems = () => [
  { title: i18n.global.t('questionnaires.question_type_short_text'), value: 1 },
  { title: i18n.global.t('questionnaires.question_type_multiple_choice'), value: 2 },
]

const sectionHeading = (sectionIndex, title) => {
  const n = sectionIndex + 1
  const trimmed = (title || '').trim()
  if (trimmed)
    return i18n.global.t('questionnaires.section_heading_with_title', { n, title: trimmed })

  return i18n.global.t('questionnaires.section_heading_number', { n })
}

const ensureQuestionOptions = question => {
  if (! question.options?.length)
    question.options = [{ uid: ++optionUid, text: '' }]
}

const onQuestionTypeChange = (question, type) => {
  question.type = Number(type)
  if (Number(type) === 2)
    ensureQuestionOptions(question)
}

const cloneQuestion = source => {
  const row = {
    uid: Date.now() + Math.random(),
    id: null,
    title: source.title,
    type: Number(source.type),
    required: source.required,
    answerCount: 0,
    options: [],
  }
  if (Number(source.type) === 2 && source.options?.length) {
    row.options = source.options.map(opt => ({
      uid: ++optionUid,
      text: opt.text ?? '',
    }))
  }

  return row
}

const addSection = () => {
  props.modelValue.push({
    uid: Date.now(),
    id: null,
    title: '',
    collapsed: false,
    questions: [{
      uid: Date.now() + 1,
      id: null,
      title: '',
      type: 1,
      options: [],
      answerCount: 0,
    }],
  })
  touchSections()
}

const removeSection = sectionIndex => {
  if (props.modelValue.length <= 1)
    return
  const section = props.modelValue[sectionIndex]
  if (section.questions?.some(q => q.answerCount > 0))
    return
  props.modelValue.splice(sectionIndex, 1)
  touchSections()
}

const moveSection = (sectionIndex, direction) => {
  const target = sectionIndex + direction
  if (target < 0 || target >= props.modelValue.length)
    return
  const list = [...props.modelValue]
  const [item] = list.splice(sectionIndex, 1)
  list.splice(target, 0, item)
  emit('update:modelValue', list)
}

const addQuestion = sectionIndex => {
  props.modelValue[sectionIndex].questions.push({
    uid: Date.now(),
    id: null,
    title: '',
    type: 1,
    options: [],
    answerCount: 0,
  })
  touchSections()
}

const duplicateQuestion = (sectionIndex, questionIndex) => {
  const section = props.modelValue[sectionIndex]
  const source = section.questions[questionIndex]
  if (! source)
    return
  section.questions.splice(questionIndex + 1, 0, cloneQuestion(source))
  touchSections()
}

const removeQuestion = (sectionIndex, questionIndex) => {
  const section = props.modelValue[sectionIndex]
  if (section.questions.length <= 1)
    return
  if (section.questions[questionIndex]?.answerCount > 0)
    return
  section.questions.splice(questionIndex, 1)
  touchSections()
}

const moveQuestion = (sectionIndex, questionIndex, direction) => {
  const section = props.modelValue[sectionIndex]
  const target = questionIndex + direction
  if (target < 0 || target >= section.questions.length)
    return
  const list = [...section.questions]
  const [item] = list.splice(questionIndex, 1)
  list.splice(target, 0, item)
  section.questions = list
  touchSections()
}

const moveQuestionToSection = (fromSectionIndex, questionIndex, toSectionIndex) => {
  if (fromSectionIndex === toSectionIndex)
    return
  const fromSection = props.modelValue[fromSectionIndex]
  const toSection = props.modelValue[toSectionIndex]
  if (! fromSection || ! toSection)
    return
  const [question] = fromSection.questions.splice(questionIndex, 1)
  toSection.questions.push(question)
  touchSections()
}

const sectionMoveTargets = currentIndex => props.modelValue
  .map((section, index) => ({
    title: section.title?.trim() || `${i18n.global.t('questionnaires.section_label')} ${index + 1}`,
    value: index,
  }))
  .filter((_, index) => index !== currentIndex)

const addQuestionOption = question => {
  ensureQuestionOptions(question)
  question.options.push({ uid: ++optionUid, text: '' })
}

const removeQuestionOption = (question, optionIndex) => {
  if (! question.options || question.options.length <= 1)
    return
  question.options.splice(optionIndex, 1)
}

const moveQuestionOption = (question, optionIndex, direction) => {
  if (! question.options)
    return
  const target = optionIndex + direction
  if (target < 0 || target >= question.options.length)
    return
  const list = [...question.options]
  const [item] = list.splice(optionIndex, 1)
  list.splice(target, 0, item)
  question.options = list
}

const toggleSection = section => {
  section.collapsed = ! section.collapsed
}

const isSingleSectionMode = computed(() => props.modelValue.length === 1)
</script>

<template>
  <div class="questionnaire-sections-editor">
    <div class="questionnaire-panel questionnaire-panel--questions pa-4">
      <div class="questionnaire-sections-editor__header">
        <div class="questionnaire-panel__heading">
          <VAvatar
            color="primary"
            variant="tonal"
            size="36"
            rounded
            class="me-3"
          >
            <VIcon
              icon="tabler-list-details"
              size="20"
            />
          </VAvatar>
          <div class="flex-grow-1">
            <div class="text-subtitle-1 font-weight-bold">
              {{ $t('questionnaires.questions_and_sections_heading') }}
            </div>
            <div class="text-body-2 text-medium-emphasis">
              {{ $t('questionnaires.questions_section_subtitle') }}
            </div>
          </div>
          <VBtn
            color="primary"
            variant="tonal"
            prepend-icon="tabler-plus"
            @click="addSection"
          >
            {{ $t('questionnaires.add_section') }}
          </VBtn>
        </div>
      </div>

      <div class="questionnaire-sections-editor__list">
        <VCard
          v-for="(section, sectionIndex) in modelValue"
          :key="section.uid"
          class="questionnaire-section-card mb-3"
          variant="flat"
        >
          <div class="questionnaire-section-card__header">
            <VIcon
              icon="tabler-grip-vertical"
              size="18"
              class="text-disabled questionnaire-section-card__grip"
            />
            <div class="flex-grow-1 min-w-0">
              <div
                v-if="! isSingleSectionMode"
                class="text-body-1 font-weight-bold mb-1"
              >
                {{ sectionHeading(sectionIndex, section.title) }}
              </div>
              <AppTextField
                v-if="isSingleSectionMode"
                v-model="section.title"
                :label="$t('questionnaires.section_title_optional_label')"
                :placeholder="$t('questionnaires.section_title_optional_placeholder')"
                density="compact"
                clearable
                hide-details
              />
              <AppTextField
                v-else
                v-model="section.title"
                :label="$t('questionnaires.section_title_label')"
                :placeholder="$t('questionnaires.section_title_optional_placeholder')"
                density="compact"
                hide-details
              />
            </div>
            <VChip
              v-if="! isSingleSectionMode"
              size="small"
              color="primary"
              variant="tonal"
            >
              {{ $t('questionnaires.section_question_count', { count: section.questions.length }) }}
            </VChip>
            <div
              v-if="! isSingleSectionMode"
              class="d-flex align-center"
            >
              <IconBtn
                size="small"
                color="error"
                variant="text"
                :disabled="modelValue.length <= 1 || section.questions.some(q => q.answerCount > 0)"
                @click="removeSection(sectionIndex)"
              >
                <VIcon icon="tabler-trash" />
              </IconBtn>
              <IconBtn
                size="small"
                :disabled="sectionIndex === modelValue.length - 1"
                @click="moveSection(sectionIndex, 1)"
              >
                <VIcon icon="tabler-arrow-down" />
              </IconBtn>
              <IconBtn
                size="small"
                :disabled="sectionIndex === 0"
                @click="moveSection(sectionIndex, -1)"
              >
                <VIcon icon="tabler-arrow-up" />
              </IconBtn>
              <IconBtn
                size="small"
                @click="toggleSection(section)"
              >
                <VIcon :icon="section.collapsed ? 'tabler-chevron-down' : 'tabler-chevron-up'" />
              </IconBtn>
            </div>
          </div>

          <VExpandTransition>
            <div
              v-show="isSingleSectionMode || ! section.collapsed"
              class="questionnaire-section-card__body pa-4 pt-2"
            >
              <div
                v-for="(question, questionIndex) in section.questions"
                :key="question.uid"
                class="questionnaire-question-row mb-3"
              >
                <div class="questionnaire-question-row__main">
                  <VIcon
                    icon="tabler-grip-vertical"
                    size="16"
                    class="text-disabled questionnaire-question-row__grip"
                  />
                  <span class="questionnaire-question-row__index">{{ questionIndex + 1 }}</span>
                  <AppTextField
                    v-model="question.title"
                    class="flex-grow-1"
                    :placeholder="$t('questionnaires.write_question_placeholder')"
                    :rules="[requiredValidator]"
                    density="compact"
                    hide-details
                  />
                  <AppSelect
                    :model-value="question.type"
                    :items="questionTypeItems()"
                    item-title="title"
                    item-value="value"
                    :label="$t('questionnaires.answer_type')"
                    :disabled="question.answerCount > 0"
                    density="compact"
                    style="min-width: 140px; max-width: 180px;"
                    hide-details
                    @update:model-value="onQuestionTypeChange(question, $event)"
                  />
                  <div class="questionnaire-question-row__actions">
                    <IconBtn
                      size="small"
                      color="error"
                      variant="text"
                      :disabled="section.questions.length <= 1 || question.answerCount > 0"
                      @click="removeQuestion(sectionIndex, questionIndex)"
                    >
                      <VIcon icon="tabler-trash" />
                    </IconBtn>
                    <IconBtn
                      size="small"
                      variant="text"
                      @click="duplicateQuestion(sectionIndex, questionIndex)"
                    >
                      <VIcon icon="tabler-copy" />
                    </IconBtn>
                    <IconBtn
                      size="small"
                      :disabled="questionIndex === 0"
                      @click="moveQuestion(sectionIndex, questionIndex, -1)"
                    >
                      <VIcon icon="tabler-arrow-up" />
                    </IconBtn>
                    <IconBtn
                      size="small"
                      :disabled="questionIndex === section.questions.length - 1"
                      @click="moveQuestion(sectionIndex, questionIndex, 1)"
                    >
                      <VIcon icon="tabler-arrow-down" />
                    </IconBtn>
                  </div>
                </div>

                <VChip
                  v-if="question.answerCount > 0"
                  class="mt-2"
                  size="x-small"
                  color="secondary"
                  variant="tonal"
                >
                  {{ $t('questionnaires.question_has_responses') }}
                </VChip>

                <AppSelect
                  v-if="modelValue.length > 1"
                  :model-value="sectionIndex"
                  :items="sectionMoveTargets(sectionIndex)"
                  item-title="title"
                  item-value="value"
                  density="compact"
                  class="mt-2"
                  style="max-width: 260px;"
                  :label="$t('questionnaires.move_question_to_section')"
                  hide-details
                  @update:model-value="moveQuestionToSection(sectionIndex, questionIndex, $event)"
                />

                <div
                  v-if="Number(question.type) === 2"
                  class="questionnaire-options-block mt-3 ms-8"
                >
                  <div class="text-body-2 font-weight-medium mb-2">
                    {{ $t('questionnaires.question_options') }}
                  </div>
                  <div
                    v-for="(option, optionIndex) in question.options"
                    :key="option.uid"
                    class="d-flex align-center gap-2 mb-2"
                  >
                    <span class="text-caption text-medium-emphasis">{{ optionIndex + 1 }}</span>
                    <AppTextField
                      v-model="option.text"
                      class="flex-grow-1"
                      :placeholder="$t('questionnaires.write_option_placeholder')"
                      :rules="[requiredValidator]"
                      density="compact"
                      hide-details
                    />
                    <IconBtn
                      size="x-small"
                      color="error"
                      variant="text"
                      :disabled="question.options.length <= 1"
                      @click="removeQuestionOption(question, optionIndex)"
                    >
                      <VIcon icon="tabler-trash" />
                    </IconBtn>
                  </div>
                  <VBtn
                    size="small"
                    variant="text"
                    prepend-icon="tabler-plus"
                    @click="addQuestionOption(question)"
                  >
                    {{ $t('questionnaires.add_option') }}
                  </VBtn>
                </div>
              </div>

              <VBtn
                block
                variant="outlined"
                color="primary"
                class="questionnaire-add-question-dashed"
                prepend-icon="tabler-plus"
                @click="addQuestion(sectionIndex)"
              >
                {{ $t('questionnaires.add_question_to_section') }}
              </VBtn>
            </div>
          </VExpandTransition>
        </VCard>
      </div>
    </div>
  </div>
</template>

<style scoped>
.questionnaire-sections-editor {
  padding: 0 1.5rem 1rem;
}

.questionnaire-panel {
  border-radius: 12px;
  border: 1px solid rgba(var(--v-theme-primary), 0.14);
  background: rgba(var(--v-theme-primary), 0.04);
}

.questionnaire-panel__heading {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 0.75rem;
}

.questionnaire-sections-editor__header {
  margin-bottom: 1rem;
}

.questionnaire-section-card {
  border: 1px solid rgba(var(--v-theme-primary), 0.12);
  border-radius: 10px;
  background: rgb(var(--v-theme-surface));
  overflow: hidden;
}

.questionnaire-section-card__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 0.5rem 0.75rem;
  padding: 0.875rem 1rem;
  background: rgba(var(--v-theme-primary), 0.06);
  border-bottom: 1px solid rgba(var(--v-theme-primary), 0.1);
}

.questionnaire-section-card__grip {
  margin-top: 0.35rem;
  cursor: grab;
}

.questionnaire-question-row {
  padding: 0.75rem;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.02);
  border: 1px solid rgba(var(--v-border-color), calc(var(--v-border-opacity) * 0.75));
}

.questionnaire-question-row__main {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.questionnaire-question-row__grip {
  cursor: grab;
  flex-shrink: 0;
}

.questionnaire-question-row__index {
  font-weight: 600;
  min-width: 1.25rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.questionnaire-question-row__actions {
  display: flex;
  align-items: center;
  flex-shrink: 0;
}

.questionnaire-add-question-dashed {
  border-style: dashed !important;
  margin-top: 0.25rem;
}

.questionnaire-options-block {
  padding-top: 0.5rem;
  border-top: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
}

@media (max-width: 600px) {
  .questionnaire-sections-editor {
    padding-inline: 1rem;
  }

  .questionnaire-question-row__main {
    flex-direction: column;
    align-items: stretch;
  }

  .questionnaire-question-row__actions {
    justify-content: flex-end;
  }
}
</style>
