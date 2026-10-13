<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useRoute, useRouter } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { questionnairesApi } from "@/plugins/apis/questionnairesRequest";
import { termsApi } from "@/plugins/apis/termsRequest";
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import { copyQuestionnaireRespondLink } from '@/utils/questionnaireShare'
import { downloadPdfFromAxiosResponse } from '@core/utils/exportDownload'
import QuestionnaireCustomSectionsEditor from '@/components/questionnaires/QuestionnaireCustomSectionsEditor.vue'
import { formatQuestionnaireBoundaryDate } from '@/utils/questionnaireDates'
import { can } from '@layouts/plugins/casl'
import {
emailValidator,
integerValidator,
lengthValidator,
requiredValidator
} from '@validators';

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const refCustomForm = ref()
const questionnaireListStore = questionnairesApi()
const createModalTab = ref('current')
const customTitle = ref('')
const customTerm = ref('')
const customFromDate = ref('')
const customToDate = ref('')
const customSections = ref([])
let customSectionUid = 0
let customQuestionUid = 0
let customOptionUid = 0
const termListStore = termsApi()
const searchQuery = ref('')
const selectedFromDate = ref()
const selectedToDate = ref()
const selectedStatus = ref({title: 'Active_user', value: 'active'})
const totalPage = ref(1)
const totalQuestionnaires = ref(0)
const questionnaires = ref([])
const terms = ref([])
const putQuestionnaireDialog = ref(false);
const taskID = ref()
const newTerm = ref()
const newFromDate = ref()
const newToDate = ref()
const snackbarRef = ref(null);
const templatePdfExportTaskId = ref(null)
const editQuestionnaireLoading = ref(false);
const allowParentEditAfterSubmit = ref(false)
const targetAudienceType = ref('parent')
const targetRoleId = ref(null)
const platformRoles = ref([])

const audienceTypeOptions = computed(() => [
  { value: 'parent', title: i18n.global.t('questionnaires.target_audience_parent') },
  { value: 'role', title: i18n.global.t('questionnaires.target_audience_role') },
])

const isParentAudience = computed(() => targetAudienceType.value === 'parent')

const loadPlatformRoles = () => {
  questionnaireListStore.fetchTargetAudienceRoles().then(response => {
    platformRoles.value = (response.data?.data ?? []).map(role => ({
      ...role,
      title: role.default_name ? i18n.global.t(role.default_name) : role.name,
    }))
  }).catch(() => {
    platformRoles.value = []
  })
}

watch(putQuestionnaireDialog, open => {
  if (open)
    loadPlatformRoles()
})

watch(targetAudienceType, type => {
  if (type === 'role') {
    customTerm.value = ''
    newTerm.value = ''
  }
  else {
    targetRoleId.value = null
  }
})

const questionnaireDatePickerConfig = { enableTime: false, dateFormat: 'Y-m-d' }

const todayInRiyadh = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(new Date())

const maxDateString = (left, right) => (left >= right ? left : right)

const findTermById = termId => terms.value.find(term => Number(term.id) === Number(termId))

const applySelectedTermDates = (termId, fromRef, toRef) => {
  const term = findTermById(termId)
  if (! term)
    return

  fromRef.value = formatQuestionnaireBoundaryDate(term.starts_at)
  let endDate = formatQuestionnaireBoundaryDate(term.ends_at)
  endDate = maxDateString(endDate, todayInRiyadh())
  toRef.value = endDate
}

const warnIfQuestionnaireNotOpen = payload => {
  if (payload?.is_open !== false)
    return

  const availability = payload?.availability
  const hint = availability?.starts_at && availability?.ends_at
    ? `${availability.starts_at} — ${availability.ends_at} (${availability.today})`
    : ''
  const message = hint
    ? `${i18n.global.t('questionnaires.save_success_not_open_hint')} ${hint}`
    : i18n.global.t('questionnaires.save_success_not_open')

  snackbarRef.value.exposevisibleSnackbar(message, 'warning')
}

watch(customTerm, termId => {
  if (taskID.value > 0 || createModalTab.value !== 'custom' || ! termId)
    return
  applySelectedTermDates(termId, customFromDate, customToDate)
})

watch(newTerm, termId => {
  if (taskID.value > 0 || createModalTab.value !== 'current' || ! termId)
    return
  applySelectedTermDates(termId, newFromDate, newToDate)
})

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

// Headers
const translatedHeaders = () => {
  const headers = [
    {
      title: 'Address',
      key: 'title',
    },
    {
      title: 'Qualifying class',
      key: 'term',
    },
    {
      title: 'goals.date_from',
      key: 'from',
    },
    {
      title: 'goals.date_to',
      key: 'to',
    },
    {
      title: 'Active',
      key: 'active',
      sortable: false,
    },
    {
      title: 'questionnaires.respond_availability',
      key: 'respond_open',
      sortable: false,
    },
    {
      title: 'Actions',
      key: 'actions',
      sortable: false,
    },
  ]
  let translatedHeaders = headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }))

  return translatedHeaders;
}

const statusItems = () => {
  let items = [
    {
      title: 'All',
      value: 'all',
    },
    {
      title: 'Active_user',
      value: 'active',
    },
    {
      title: 'Inactive',
      value: 'inactive',
    },
  ]
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))

  return translatedItems;
}

const resolveQuestionnaireStatusVariant = stat => {
  const statLowerCase = stat.toLowerCase()
  if (statLowerCase === 'pending')
    return 'warning'
  if (statLowerCase === 'active')
    return 'success'
  if (statLowerCase === 'inactive')
    return 'secondary'
  
  return 'primary'
}


// 👉 Fetching Questionnaires
const fetchQuestionnaires = () => {
  questionnaireListStore.fetchQuestionnaires({
    q: searchQuery.value,
    from_date: selectedFromDate.value,
    to_date: selectedToDate.value,
    status: selectedStatus.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    questionnaires.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalQuestionnaires.value = response.data.total
    options.value.page = response.data.currentPage
  }).catch(error => {
    console.error(error)
  })
}

const exportQuestionnaireTemplate = async item => {
  const taskId = Number(item?.id ?? item?.raw?.id)
  if (! taskId || templatePdfExportTaskId.value)
    return

  templatePdfExportTaskId.value = taskId
  try {
    const response = await questionnaireListStore.exportQuestionnaireTemplatePdf(taskId)
    const title = item?.title ?? item?.raw?.title ?? 'questionnaire'
    const safeTitle = String(title).replace(/[^\w\u0600-\u06FF\s-]+/g, '').trim() || 'questionnaire'
    await downloadPdfFromAxiosResponse(response, `${safeTitle}-template.pdf`)
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('questionnaires.export_template_success'), 'success')
  }
  catch (error) {
    const message = error?.message && !String(error.message).startsWith('%PDF-')
      ? error.message
      : i18n.global.t('questionnaires.export_template_failed')
    snackbarRef.value.exposevisibleSnackbar(message, 'error')
  }
  finally {
    templatePdfExportTaskId.value = null
  }
}

const deleteQuestionnaire = id => {
  questionnaireListStore.deleteQuestionnaire(id).then(response => {
    if(response.data['status']){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      fetchQuestionnaires()
    }
  })
}

const restoreQuestionnaire = id => {
  questionnaireListStore.restoreQuestionnaire(id).then(response => {
    if(response.data['status']){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      fetchQuestionnaires()
    }
  })
}

const buildEmptyQuestion = () => ({
  uid: ++customQuestionUid,
  id: null,
  title: '',
  type: 1,
  options: [],
  answerCount: 0,
})

const buildEmptySection = () => ({
  uid: ++customSectionUid,
  id: null,
  title: '',
  collapsed: false,
  questions: [buildEmptyQuestion()],
})

const resetCustomBuilder = () => {
  customTitle.value = ''
  customTerm.value = ''
  customFromDate.value = ''
  customToDate.value = ''
  targetAudienceType.value = 'parent'
  targetRoleId.value = null
  customSections.value = [buildEmptySection()]
}

const mapApiQuestionToCustom = question => {
  const row = {
    uid: ++customQuestionUid,
    id: question.id,
    title: question.title,
    type: Number(question.type),
    required: question.required,
    answerCount: Number(question.answer_count ?? 0),
    options: [],
  }

  if (Number(question.type) === 2 && Array.isArray(question.options)) {
    row.options = question.options.map(label => ({
      uid: ++customOptionUid,
      text: label,
    }))
  }

  return row
}

const mapApiSectionToCustom = section => ({
  uid: ++customSectionUid,
  id: section.id || null,
  title: section.title || '',
  collapsed: false,
  questions: section.questions?.length
    ? section.questions.map(mapApiQuestionToCustom)
    : [buildEmptyQuestion()],
})

const loadQuestionnaireForEdit = taskId => {
  editQuestionnaireLoading.value = true
  questionnaireListStore.fetchQuestionnaire(taskId).then(response => {
    const data = response.data.data
    if (! data)
      return

    customTitle.value = data.title || data.questionnaire_title || ''
    targetAudienceType.value = data.target_audience_type || 'parent'
    targetRoleId.value = data.target_role_id ? Number(data.target_role_id) : null
    customTerm.value = data.term_id ? Number(data.term_id) : ''
    customFromDate.value = formatQuestionnaireBoundaryDate(data.starts_at)
    customToDate.value = formatQuestionnaireBoundaryDate(data.ends_at)
    allowParentEditAfterSubmit.value = Boolean(data.allow_parent_edit_after_submit)
    if (data.sections?.length) {
      customSections.value = data.sections.map(mapApiSectionToCustom)
    }
    else if (data.questions?.length) {
      customSections.value = [{
        uid: ++customSectionUid,
        id: null,
        title: '',
        collapsed: false,
        questions: data.questions.map(mapApiQuestionToCustom),
      }]
    }
    else {
      customSections.value = [buildEmptySection()]
    }
  }).catch(e => {
    showQuestionnaireApiError(e)
  }).finally(() => {
    editQuestionnaireLoading.value = false
  })
}

const shareQuestionnaire = item => {
  copyQuestionnaireRespondLink(item.id).then(() => {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('questionnaires.share_link_copied'), 'success')
  }).catch(() => {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('questionnaires.share_link_failed'), 'error')
  })
}

const closeQuestionnaireDialog = () => {
  putQuestionnaireDialog.value = false
  createModalTab.value = 'current'
  allowParentEditAfterSubmit.value = false
  targetAudienceType.value = 'parent'
  targetRoleId.value = null
}

const questionnaireDialog = questionnaire => {
  putQuestionnaireDialog.value = true
  createModalTab.value = 'custom'
  taskID.value = questionnaire ? questionnaire.id : 0
  newTerm.value = questionnaire && questionnaire.term_id ? Number(questionnaire.term_id) : ''
  newFromDate.value = questionnaire ? questionnaire.starts_at : ''
  newToDate.value = questionnaire ? questionnaire.ends_at : ''
  if (questionnaire) {
    targetAudienceType.value = questionnaire.target_audience_type || 'parent'
    targetRoleId.value = questionnaire.target_role_id ? Number(questionnaire.target_role_id) : null
    loadQuestionnaireForEdit(questionnaire.id)
  }
  else {
    allowParentEditAfterSubmit.value = false
    targetAudienceType.value = 'parent'
    targetRoleId.value = null
    resetCustomBuilder()
  }
}

const showQuestionnaireApiError = error => {
  const message = error?.response?.data?.errors?.error?.[0]
  if (message)
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t(message), 'error')
}

const putQuestionnaire = () =>{
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){

      questionnaireListStore.putQuestionnaire({
        id: taskID.value,
        target_audience_type: targetAudienceType.value,
        target_role_id: targetAudienceType.value === 'role' ? targetRoleId.value : '',
        term_id: isParentAudience.value ? newTerm.value : '',
        starts_at: formatQuestionnaireBoundaryDate(newFromDate.value),
        ends_at: formatQuestionnaireBoundaryDate(newToDate.value),
        allow_parent_edit_after_submit: allowParentEditAfterSubmit.value ? 1 : 0,
      }).then(response => {
        if(response.status == 200){
          if(response.data.status) {
            snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data.message), 'success');
            warnIfQuestionnaireNotOpen(response.data.data)
            fetchQuestionnaires()
          }
          else {
              snackbarRef.value.exposevisibleSnackbar(i18n.global.t("questionnaires."+response.data.message), 'error');
          }
          closeQuestionnaireDialog()
        }
      }).catch(e => {
        showQuestionnaireApiError(e)
      })
    }
  })
}

const putCustomQuestionnaire = () => {
  refCustomForm.value?.validate().then(({ valid: isValid }) => {
    if (! isValid)
      return

    const formData = new FormData()
    if (taskID.value > 0)
      formData.append('id', taskID.value)
    formData.append('title', customTitle.value)
    formData.append('target_audience_type', targetAudienceType.value)
    if (targetAudienceType.value === 'role')
      formData.append('target_role_id', targetRoleId.value ?? '')
    else
      formData.append('term_id', customTerm.value)
    formData.append('starts_at', formatQuestionnaireBoundaryDate(customFromDate.value))
    formData.append('ends_at', formatQuestionnaireBoundaryDate(customToDate.value))
    formData.append('allow_parent_edit_after_submit', allowParentEditAfterSubmit.value ? '1' : '0')
    customSections.value.forEach((section, sectionIndex) => {
      if (section.id)
        formData.append(`sections[${sectionIndex}][id]`, section.id)
      formData.append(`sections[${sectionIndex}][title]`, section.title)
      section.questions.forEach((question, questionIndex) => {
        if (question.id)
          formData.append(`sections[${sectionIndex}][questions][${questionIndex}][id]`, question.id)
        formData.append(`sections[${sectionIndex}][questions][${questionIndex}][title]`, question.title)
        formData.append(`sections[${sectionIndex}][questions][${questionIndex}][type]`, question.type)
        if (Number(question.type) === 2 && question.options?.length) {
          question.options.forEach((option, optionIndex) => {
            formData.append(`sections[${sectionIndex}][questions][${questionIndex}][options][${optionIndex}]`, option.text ?? '')
          })
        }
      })
    })

    questionnaireListStore.putCustomQuestionnaire(formData).then(response => {
      if (response.status === 200 && response.data.status) {
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data.message), 'success')
        warnIfQuestionnaireNotOpen(response.data.data)
        fetchQuestionnaires()
        closeQuestionnaireDialog()
      }
    }).catch(e => {
      showQuestionnaireApiError(e)
    })
  })
}

// 👉 Fetch Terms
termListStore.items().then(response => {
  terms.value = response.data.data
}).catch(() => {
})

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchQuestionnaires, {
  search: searchQuery,
  filters: () => [selectedFromDate.value, selectedToDate.value, selectedStatus.value],
  options,
})
</script>

<template>
  <section>
    <VRow>

      <VCol cols="12">
        <VCard>
          <!-- 👉 Filters -->
          <VCardText>
            <VRow>

              <!-- 👉 Select Start Date -->
              <VCol
                cols="12"
                sm="4"
              >
                <div class="pa-1">
                  {{ $t('date') }}
                </div>
                <VRow>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppDateTimePicker
                      v-model="selectedFromDate"
                      :placeholder="$t('from')"
                    />
                  </VCol>
                  
                  <!-- 👉 Select End Date -->
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppDateTimePicker
                      v-model="selectedToDate"
                      :placeholder="$t('to')"
                    />
                  </VCol>
                </VRow>
              </VCol>

              <!-- 👉 Select Status -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedStatus"
                  :label="$t('Active')"
                  :items="statusItems()"
                  clearable
                  clear-icon="tabler-x"
                  class="pa-1"
                >
                </AppSelect>
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />

          <VCardText class="d-flex flex-wrap py-4 gap-4">
            <div class="me-3 d-flex gap-3">
              <AppSelect
                :model-value="options.itemsPerPage"
                :items="[
                  { value: 10, title: '10' },
                  { value: 25, title: '25' },
                  { value: 50, title: '50' },
                  { value: 100, title: 'All' },
                ]"
                style="width: 6.25rem;"
                @update:model-value="options.itemsPerPage = parseInt($event, 10)"
              />
            </div>
            <VSpacer />

            <div class="app-Questionnaire-search-filter justify-end d-flex align-center flex-wrap gap-4">
              <!-- 👉 Search  -->
              <div style="inline-size: 20rem;">
                <AppTextField
                  v-model="searchQuery"
                  :placeholder="$t('Search')"
                  density="compact"
                />
              </div>
              
              <!-- 👉 Add Questionnaire button -->
              <VBtn
                v-if="can('edit_questionnaires', 'edit_questionnaires')"
                prepend-icon="tabler-plus"
                @click="questionnaireDialog(null)"
              >
                {{ $t('Add Questionnaire') }}
              </VBtn>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="questionnaires"
            :items-length="totalQuestionnaires"
            :headers="translatedHeaders()"
            class="dataTable-hidescroller-y"
            @update:options="onTableOptions"
          >
          
          <!-- From Date -->
          <template #item.term="{ item }">
            <RouterLink
              :to="{ name: 'classes-view-id', params: { id: item.raw.term_id } }"
              class="font-weight-medium name-route"
            >
              {{ item.raw.term_title }}
            </RouterLink>
          </template>
          
          <!-- From Date -->
          <template #item.from="{ item }">
            {{ item.raw.starts_at }}
          </template>
          
          <!-- To Date -->
          <template #item.to="{ item }">
            {{ item.raw.ends_at }}
          </template>
          
          <!-- Active -->
          <template #item.active="{ item }">
              <VChip
                :color="resolveQuestionnaireStatusVariant(item.raw.deleted_at ? 'inactive' : 'active' )"
                size="small"
                label
                class="text-capitalize"
              >
                {{ item.raw.deleted_at ? $t('Inactive') : $t('Active_user') }}
              </VChip>
            </template>

          <template #item.respond_open="{ item }">
            <VChip
              v-if="! item.raw.deleted_at"
              :color="item.raw.is_open ? 'success' : 'warning'"
              size="small"
              label
              class="text-capitalize"
            >
              {{ item.raw.is_open ? $t('questionnaires.respond_open') : $t('questionnaires.respond_closed') }}
            </VChip>
            <span v-else>—</span>
          </template>

            <!-- Actions -->
            <template #item.actions="{ item }">

              <IconBtn v-if="!item.raw.deleted_at && can('show_questionnaires', 'show_questionnaires')" @click="()=> router.push('/questionnaires/view/'+item.raw.id)">
                <VIcon icon="tabler-eye" />
              </IconBtn>

              <IconBtn v-if="can('edit_questionnaires', 'edit_questionnaires')" @click="questionnaireDialog(item.raw)">
                <VIcon icon="tabler-edit" />
              </IconBtn>

              <VBtn
                icon
                variant="text"
                size="small"
                color="medium-emphasis"
              >
                <VIcon
                  size="24"
                  icon="tabler-dots-vertical"
                />
                <VMenu activator="parent">
                  <VList>
                    <VListItem
                      v-if="!item.raw.deleted_at && can('show_questionnaires', 'show_questionnaires')"
                      @click="shareQuestionnaire(item.raw)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-share" />
                      </template>
                      <VListItemTitle>{{ $t('questionnaires.share_questionnaire') }}</VListItemTitle>
                    </VListItem>

                    <VListItem
                      v-if="!item.raw.deleted_at && can('show_questionnaires', 'show_questionnaires')"
                      :disabled="templatePdfExportTaskId === item.raw.id"
                      @click="exportQuestionnaireTemplate(item.raw)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-file-type-pdf" />
                      </template>
                      <VListItemTitle>{{ $t('questionnaires.export_template') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="!item.raw.deleted_at && can('admin_questionnaires', 'admin_questionnaires')" 
                      @click="deleteQuestionnaire(item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-trash" />
                      </template>
                      <VListItemTitle>{{ $t('delete') }}</VListItemTitle>
                    </VListItem>

                    <VListItem 
                      v-if="item.raw.deleted_at && can('admin_questionnaires', 'admin_questionnaires')" 
                      @click="restoreQuestionnaire(item.raw.id)"
                    >
                      <template #prepend>
                        <VIcon icon="tabler-refresh" />
                      </template>
                      <VListItemTitle>{{ $t('restore') }}</VListItemTitle>
                    </VListItem>
                  </VList>
                </VMenu>
              </VBtn>
            </template>

            <!-- pagination -->
            <template #bottom>
              <VDivider />
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalQuestionnaires) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalQuestionnaires / options.itemsPerPage)"
                  :total-visible="$vuetify.display.xs ? 1 : Math.ceil(totalQuestionnaires / options.itemsPerPage)"
                >
                  <template #prev="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      Previous
                    </VBtn>
                  </template>

                  <template #next="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      Next
                    </VBtn>
                  </template>
                </VPagination>
              </div>
            </template>
          </VDataTableServer>
          <!-- SECTION -->
        </VCard>
      </vcol>
    </vrow>
    
    <VDialog
      v-model="putQuestionnaireDialog"
      persistent
      class="questionnaire-add-dialog-width"
      :max-width="960"
      width="960"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="closeQuestionnaireDialog" />

      <VCard class="questionnaire-add-dialog-card">
        <VCardItem class="questionnaire-add-dialog-card__header pb-2">
          <template #prepend>
            <VAvatar
              color="primary"
              variant="tonal"
              size="40"
              rounded
            >
              <VIcon
                icon="tabler-clipboard-list"
                size="22"
              />
            </VAvatar>
          </template>
          <VCardTitle class="text-h5 font-weight-bold">
            {{ $t('Questionnaire') }}
          </VCardTitle>
        </VCardItem>

        <VProgressLinear
          v-if="editQuestionnaireLoading && taskID > 0"
          indeterminate
        />
        <div class="questionnaire-add-dialog-body">
          <div
            class="questionnaire-add-dialog-shell"
            :class="{ 'questionnaire-add-dialog-shell--custom': taskID > 0 || createModalTab === 'custom' }"
          >
          <VTabs
            v-if="taskID === 0"
            v-model="createModalTab"
            class="questionnaire-add-dialog-tabs px-4"
          >
            <VTab value="current">
              {{ $t('questionnaires.current_questionnaire_tab') }}
            </VTab>
            <VTab value="custom">
              {{ $t('questionnaires.create_new_questionnaire_tab') }}
            </VTab>
          </VTabs>

          <div
            v-if="taskID > 0 || createModalTab === 'custom'"
            class="questionnaire-add-dialog-panes"
          >
            <div class="questionnaire-add-dialog-pane questionnaire-custom-tab">
              <VForm
                ref="refCustomForm"
                class="questionnaire-custom-form"
                @submit.prevent="putCustomQuestionnaire"
              >
                <div class="questionnaire-custom-form-top px-6 pt-4 pb-3">
                  <AppTextField
                    v-model="customTitle"
                    :label="$t('questionnaires.questionnaire_name')"
                    :rules="[requiredValidator]"
                  />
                </div>

                <div class="questionnaire-custom-form-middle px-6 pb-3">
                  <div class="questionnaire-panel questionnaire-panel--settings pa-4">
                    <div class="questionnaire-panel__heading mb-4">
                      <VAvatar
                        color="primary"
                        variant="tonal"
                        size="36"
                        rounded
                        class="me-3"
                      >
                        <VIcon
                          icon="tabler-settings"
                          size="20"
                        />
                      </VAvatar>
                      <div>
                        <div class="text-subtitle-1 font-weight-bold">
                          {{ $t('questionnaires.questionnaire_settings_heading') }}
                        </div>
                        <div class="text-body-2 text-medium-emphasis">
                          {{ $t('questionnaires.questionnaire_settings_subtitle') }}
                        </div>
                      </div>
                    </div>

                    <VRow dense>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <div class="text-body-2 font-weight-medium mb-2">
                          {{ $t('questionnaires.target_audience_label') }}
                        </div>
                        <VRadioGroup
                          v-model="targetAudienceType"
                          inline
                          hide-details
                          class="mt-0"
                        >
                          <VRadio
                            v-for="opt in audienceTypeOptions"
                            :key="opt.value"
                            :label="opt.title"
                            :value="opt.value"
                          />
                        </VRadioGroup>
                      </VCol>
                      <VCol
                        v-if="isParentAudience"
                        cols="12"
                        md="6"
                      >
                        <AppSelect
                          v-model="customTerm"
                          :items="terms"
                          item-title="title"
                          item-value="id"
                          clearable
                          clear-icon="tabler-x"
                          :label="$t('choose term')"
                          :rules="[requiredValidator]"
                        />
                      </VCol>
                      <VCol
                        v-else
                        cols="12"
                        md="6"
                      >
                        <AppAutocomplete
                          v-model="targetRoleId"
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
                        <div class="text-body-2 font-weight-medium mb-2">
                          {{ $t('questionnaires.allow_parent_edit_after_submit') }}
                        </div>
                        <VRadioGroup
                          v-model="allowParentEditAfterSubmit"
                          inline
                          hide-details
                        >
                          <VRadio
                            :label="$t('questionnaires.allow_parent_edit_yes')"
                            :value="true"
                          />
                          <VRadio
                            :label="$t('questionnaires.allow_parent_edit_no')"
                            :value="false"
                          />
                        </VRadioGroup>
                      </VCol>
                      <VCol
                        cols="12"
                        md="4"
                      >
                        <AppDateTimePicker
                          v-model="customFromDate"
                          :config="questionnaireDatePickerConfig"
                          :label="$t('questionnaires.availability_start_label')"
                          :placeholder="$t('from')"
                          :rules="[requiredValidator]"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="4"
                      >
                        <AppDateTimePicker
                          v-model="customToDate"
                          :config="questionnaireDatePickerConfig"
                          :label="$t('questionnaires.availability_end_label')"
                          :placeholder="$t('to')"
                          :rules="[requiredValidator]"
                        />
                      </VCol>
                    </VRow>
                  </div>
                </div>

                <div class="questionnaire-questions-scroll-region">
                  <QuestionnaireCustomSectionsEditor v-model="customSections" />
                </div>

                <div class="questionnaire-custom-form-footer px-6 py-4">
                  <div class="d-flex align-center justify-space-between flex-wrap gap-3">
                    <VBtn
                      color="secondary"
                      variant="outlined"
                      @click="closeQuestionnaireDialog"
                    >
                      {{ $t('Cancel') }}
                    </VBtn>
                    <VBtn
                      type="submit"
                      color="primary"
                      size="large"
                      prepend-icon="tabler-device-floppy"
                    >
                      {{ taskID > 0 ? $t('Save') : $t('questionnaires.save_questionnaire') }}
                    </VBtn>
                  </div>
                </div>
              </VForm>
            </div>
          </div>

          <div
            v-else
            class="questionnaire-add-dialog-panes"
          >
            <div class="questionnaire-add-dialog-pane">
              <VForm
                ref="refVForm"
                @submit.prevent="putQuestionnaire"
              >
                <VCardText>
                  <VRow>
                    <VCol cols="12">
                      <div class="text-body-2 font-weight-medium mb-2">
                        {{ $t('questionnaires.target_audience_label') }}
                      </div>
                      <VRadioGroup
                        v-model="targetAudienceType"
                        inline
                        class="mb-2"
                      >
                        <VRadio
                          v-for="opt in audienceTypeOptions"
                          :key="opt.value"
                          :label="opt.title"
                          :value="opt.value"
                        />
                      </VRadioGroup>
                    </VCol>
                    <VCol
                      v-if="isParentAudience"
                      cols="12"
                    >
                      <AppSelect
                        v-model="newTerm"
                        :items="terms"
                        item-title="title"
                        item-value="id"
                        clearable
                        clear-icon="tabler-x"
                        class="pa-1"
                        :label="$t('choose term')"
                        :rules="[requiredValidator]"
                      />
                    </VCol>
                    <VCol
                      v-else
                      cols="12"
                    >
                      <AppAutocomplete
                        v-model="targetRoleId"
                        class="pa-1"
                        :items="platformRoles"
                        item-title="title"
                        item-value="id"
                        :label="$t('questionnaires.target_role_label')"
                        :rules="[requiredValidator]"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppDateTimePicker
                        v-model="newFromDate"
                        :config="questionnaireDatePickerConfig"
                        :placeholder="$t('from')"
                        :rules="[requiredValidator]"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppDateTimePicker
                        v-model="newToDate"
                        :config="questionnaireDatePickerConfig"
                        :placeholder="$t('to')"
                        :rules="[requiredValidator]"
                      />
                    </VCol>
                    <VCol cols="12">
                      <div class="text-body-2 font-weight-medium mb-2">
                        {{ $t('questionnaires.allow_parent_edit_after_submit') }}
                      </div>
                      <VRadioGroup
                        v-model="allowParentEditAfterSubmit"
                        inline
                      >
                        <VRadio
                          :label="$t('questionnaires.allow_parent_edit_no')"
                          :value="false"
                        />
                        <VRadio
                          :label="$t('questionnaires.allow_parent_edit_yes')"
                          :value="true"
                        />
                      </VRadioGroup>
                    </VCol>
                  </VRow>
                </VCardText>
                <VCardText class="d-flex justify-end gap-3 flex-wrap">
                  <VBtn
                    type="submit"
                    color="success"
                  >
                    {{ $t('Save') }}
                  </VBtn>
                  <VBtn
                    color="secondary"
                    variant="tonal"
                    @click="closeQuestionnaireDialog"
                  >
                    {{ $t('Close') }}
                  </VBtn>
                </VCardText>
              </VForm>
            </div>
          </div>
        </div>
        </div>
      </VCard>
    </VDialog>
    <SnackbarComponent ref="snackbarRef" />
  </section>
</template>

<style lang="scss">
  .text-capitalize {
    text-transform: capitalize;
  }

  .name-route:not(:hover) {
    color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
  }

  /* Questionnaire add/edit modal — 960px on desktop (override theme v-dialog-sm 565px) */
  @media (min-width: 600px) {
    .v-dialog.questionnaire-add-dialog-width > .v-overlay__content {
      inline-size: min(960px, calc(100vw - 32px)) !important;
      max-inline-size: 960px !important;
    }
  }

  .v-dialog > .v-overlay__content > .v-card.questionnaire-add-dialog-card {
    display: flex;
    flex-direction: column;
    max-height: calc(100vh - 32px);
    max-height: calc(100dvh - 32px);
    min-height: 0;
    overflow: hidden;
  }

  .questionnaire-add-dialog-card.v-card > .v-card-text {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
    padding: 0;
  }

  .questionnaire-add-dialog-card > .v-card-item {
    flex-shrink: 0;
  }

  .questionnaire-add-dialog-body {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }

  .questionnaire-add-dialog-shell {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }

  .questionnaire-add-dialog-tabs {
    flex-shrink: 0;
  }

  .questionnaire-add-dialog-shell--custom .questionnaire-add-dialog-panes {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }

  .questionnaire-add-dialog-shell--custom .questionnaire-custom-tab {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }

  .questionnaire-add-dialog-shell--custom .questionnaire-custom-form {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }

  .questionnaire-add-dialog-shell--custom .questionnaire-custom-form-top,
  .questionnaire-add-dialog-shell--custom .questionnaire-custom-form-middle {
    flex-shrink: 0;
  }

  .questionnaire-add-dialog-card__header {
    flex-shrink: 0;
  }

  .questionnaire-add-dialog-shell--custom .questionnaire-panel {
    border-radius: 12px;
    border: 1px solid rgba(var(--v-theme-primary), 0.14);
    background: rgba(var(--v-theme-primary), 0.04);
  }

  .questionnaire-add-dialog-shell--custom .questionnaire-panel__heading {
    display: flex;
    align-items: flex-start;
  }

  .questionnaire-add-dialog-shell--custom .questionnaire-questions-scroll-region {
    flex: 1 1 auto;
    min-height: 180px;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-gutter: stable;
    padding-top: 0.25rem;
  }

  .questionnaire-custom-form-footer {
    flex-shrink: 0;
    border-block-start: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
    background: rgb(var(--v-theme-surface));
  }

  @media (max-width: 600px) {
    .v-dialog > .v-overlay__content > .v-card.questionnaire-add-dialog-card {
      max-height: calc(100dvh - 16px);
    }

    .questionnaire-add-dialog-shell--custom .questionnaire-questions-scroll-region {
      min-height: 120px;
    }
  }
</style>

<route lang="yaml">
  meta:
    action: access_questionnaires
    subject: access_questionnaires
</route>