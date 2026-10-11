<script setup>
import { useRoute } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import { questionnairesApi } from "@/plugins/apis/questionnairesRequest";
import { questionnaireRespondPath } from '@/utils/questionnaireShare'
import { VDataTable } from 'vuetify/labs/VDataTable'
import VueApexCharts from 'vue3-apexcharts'
import { useTheme } from 'vuetify'
import { getDonutChartConfig } from '@core/libs/apex-chart/apexCharConfig'
import { downloadExportFile } from '@core/utils/exportDownload'
import { can } from '@layouts/plugins/casl'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'

const vuetifyTheme = useTheme()
const expenseRationChartConfig = computed(() => getDonutChartConfig(vuetifyTheme.current.value))

const donutChartConfigForQuestion = data => {
  const base = expenseRationChartConfig.value
  if (data?.chart_labels?.length)
    return { ...base, labels: data.chart_labels }

  return base
}

const choiceStatsHeaders = () => [
  { title: i18n.global.t('Questionnaire Answers'), key: 'answer' },
  { title: i18n.global.t('Count'), key: 'count' },
  { title: i18n.global.t('questionnaires.option_percent'), key: 'percent' },
]

const questionnaireListStore = questionnairesApi()
const route = useRoute()
const questionnaire = ref()
const questionsList = ref([])
const kpis = ref({
  targeted_users: 0,
  completed_submissions: 0,
})
const submissionsPage = ref({
  data: [],
  total: 0,
  per_page: 10,
  current_page: 1,
})
const questionsLink = ref()
const exportLoading = ref(false)
const pdfExportLoading = ref(false)
const analyticsLoading = ref(false)
const snackbarRef = ref(null)

const filterDateFrom = ref(null)
const filterDateTo = ref(null)
const filterRespondent = ref(null)
const respondentSearch = ref('')
const respondentOptions = ref([])
const respondentSearchLoading = ref(false)

const datePickerConfig = { enableTime: false, dateFormat: 'Y-m-d' }

const canExportResponses = () => can('show_questionnaires', 'show_questionnaires')
  || can('admin_questionnaires', 'admin_questionnaires')

const unavailableLabel = () => i18n.global.t('questionnaires.metadata_unavailable')

const formatAccountName = row => {
  if (! row?.account_available)
    return unavailableLabel()

  return row.account_name || unavailableLabel()
}

const formatAccountEmail = row => {
  if (! row?.account_available)
    return unavailableLabel()

  return row.account_email || unavailableLabel()
}

const formatSubmissionDate = row => {
  if (! row?.submitted_at_available)
    return unavailableLabel()

  return row.submitted_date || unavailableLabel()
}

const formatSubmissionTime = row => {
  if (! row?.submitted_at_available)
    return unavailableLabel()

  return row.submitted_time || unavailableLabel()
}

const formatAnswerDisplay = answer => {
  if (answer === null || answer === undefined || answer === '')
    return '—'

  return String(answer)
}

const buildAnalyticsPayload = (page = submissionsPage.value.current_page) => {
  const payload = {
    task_id: Number(route.params.id),
    page,
    per_page: submissionsPage.value.per_page,
  }
  if (filterDateFrom.value)
    payload.date_from = filterDateFrom.value
  if (filterDateTo.value)
    payload.date_to = filterDateTo.value
  if (filterRespondent.value?.id)
    payload.respondent_user_id = Number(filterRespondent.value.id)

  return payload
}

const buildExportParams = () => {
  const params = {}
  if (filterDateFrom.value)
    params.date_from = filterDateFrom.value
  if (filterDateTo.value)
    params.date_to = filterDateTo.value
  if (filterRespondent.value?.id)
    params.respondent_user_id = Number(filterRespondent.value.id)

  return params
}

const applyAnalyticsPayload = payload => {
  if (payload?.kpis)
    kpis.value = payload.kpis
  questionsList.value = Array.isArray(payload?.questions) ? payload.questions : []
  if (payload?.submissions)
    submissionsPage.value = payload.submissions
}

const loadAnalytics = async (page = 1) => {
  analyticsLoading.value = true
  try {
    const response = await questionnaireListStore.fetchQuestionsWithAnswers(buildAnalyticsPayload(page))
    applyAnalyticsPayload(response.data.data)
  }
  catch {
    snackbarRef.value?.exposevisibleSnackbar(i18n.global.t('questionnaires.analytics_load_failed'), 'error')
  }
  finally {
    analyticsLoading.value = false
  }
}

const onApplyFilters = () => {
  submissionsPage.value.current_page = 1
  loadAnalytics(1)
}

const onClearFilters = () => {
  filterDateFrom.value = null
  filterDateTo.value = null
  filterRespondent.value = null
  respondentSearch.value = ''
  onApplyFilters()
}

const onSubmissionsPageChange = page => {
  submissionsPage.value.current_page = page
  loadAnalytics(page)
}

let respondentSearchTimer = null
const onRespondentSearch = query => {
  respondentSearch.value = query
  if (respondentSearchTimer)
    clearTimeout(respondentSearchTimer)

  respondentSearchTimer = setTimeout(async () => {
    respondentSearchLoading.value = true
    try {
      const response = await questionnaireListStore.searchQuestionnaireRespondents(
        Number(route.params.id),
        { q: query || '' },
      )
      respondentOptions.value = response.data.data ?? []
    }
    catch {
      respondentOptions.value = []
    }
    finally {
      respondentSearchLoading.value = false
    }
  }, 300)
}

questionnaireListStore.fetchQuestionnaire(Number(route.params.id)).then(response => {
  questionnaire.value = response.data.data
  questionsLink.value = questionnaireRespondPath(Number(route.params.id));
  loadAnalytics(1)
}).catch(() => {
})

onRespondentSearch('')

const translatedHeaders = () => {
  const headers = [
    { title: '#', key: 'id' },
    { title: 'questionnaires.respondent_account_name', key: 'account_name' },
    { title: 'questionnaires.respondent_account_email', key: 'account_email' },
    { title: 'questionnaires.submission_date', key: 'submitted_date' },
    { title: 'questionnaires.submission_time', key: 'submitted_time' },
    { title: 'Questionnaire Answers', key: 'answer' },
  ]

  return headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }))
}

const textAnswerItems = rows => rows.map(row => ({
  ...row,
  account_name: formatAccountName(row),
  account_email: formatAccountEmail(row),
  submitted_date: formatSubmissionDate(row),
  submitted_time: formatSubmissionTime(row),
}))

const exportResponsesExcel = async () => {
  exportLoading.value = true
  try {
    const response = await questionnaireListStore.exportQuestionnaireResponses(
      Number(route.params.id),
      buildExportParams(),
    )
    const url = response.data?.data?.url
    if (! url) {
      snackbarRef.value?.exposevisibleSnackbar(i18n.global.t('questionnaires.export_excel_failed'), 'error')

      return
    }

    await downloadExportFile(url)
    snackbarRef.value?.exposevisibleSnackbar(
      i18n.global.t(submissionsPage.value.total
        ? 'questionnaires.export_excel_success'
        : 'questionnaires.export_excel_empty'),
      submissionsPage.value.total ? 'success' : 'info',
    )
  }
  catch {
    snackbarRef.value?.exposevisibleSnackbar(i18n.global.t('questionnaires.export_excel_failed'), 'error')
  }
  finally {
    exportLoading.value = false
  }
}

const exportAnalyticsPdf = async () => {
  pdfExportLoading.value = true
  try {
    const response = await questionnaireListStore.exportQuestionnaireAnalyticsPdf(
      Number(route.params.id),
      buildExportParams(),
    )
    const url = response.data?.data?.url
    if (! url) {
      snackbarRef.value?.exposevisibleSnackbar(i18n.global.t('questionnaires.export_pdf_failed'), 'error')

      return
    }

    await downloadExportFile(url)
    snackbarRef.value?.exposevisibleSnackbar(i18n.global.t('questionnaires.export_pdf_success'), 'success')
  }
  catch {
    snackbarRef.value?.exposevisibleSnackbar(i18n.global.t('questionnaires.export_pdf_failed'), 'error')
  }
  finally {
    pdfExportLoading.value = false
  }
}

const formatPercent = value => `${Number(value ?? 0).toFixed(2)}%`
</script>

<template>
  <section v-if="questionnaire">
    <VRow>
      <VCol
        cols="12"
        md="12"
      >
        <VCard>
          <VCardText class="d-flex flex-wrap justify-space-between flex-column flex-sm-row print-row">
            <div class="flex-grow-1">
              <div class="d-flex align-center">
                <VRow>
                  <VCol
                    cols="12"
                    md="12"
                  >
                    <h4 class="font-weight-bold text-capitalize text-h4" color="primary">
                      {{ questionnaire.title }}
                    </h4>
                  </VCol>
                  <VCol
                    cols="12"
                    md="12"
                  >
                    <h7 class="font-weight-bold text-capitalize text-h7" color="primary">
                      <a
                        target="_blank"
                        :href="questionsLink"
                      >{{ $t('questionnaires.Questionnaire Link') }}</a>
                    </h7>
                  </VCol>
                </VRow>
              </div>
            </div>
            <div
              v-if="canExportResponses()"
              class="d-flex flex-wrap gap-2 align-center mt-4 mt-sm-0"
            >
              <VBtn
                color="primary"
                prepend-icon="tabler-file-spreadsheet"
                :loading="exportLoading"
                :disabled="exportLoading || analyticsLoading"
                @click="exportResponsesExcel"
              >
                {{ $t('questionnaires.export_excel') }}
              </VBtn>
              <VBtn
                color="secondary"
                prepend-icon="tabler-file-type-pdf"
                :loading="pdfExportLoading"
                :disabled="pdfExportLoading || analyticsLoading"
                @click="exportAnalyticsPdf"
              >
                {{ $t('questionnaires.export_pdf') }}
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12">
        <VCard :loading="analyticsLoading">
          <VCardText>
            <h5 class="text-h5 font-weight-bold mb-4">
              {{ $t('questionnaires.analytics_filters_heading') }}
            </h5>
            <VRow>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="filterDateFrom"
                  :label="$t('questionnaires.filter_date_from')"
                  :config="datePickerConfig"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppDateTimePicker
                  v-model="filterDateTo"
                  :label="$t('questionnaires.filter_date_to')"
                  :config="datePickerConfig"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <VAutocomplete
                  v-model="filterRespondent"
                  :items="respondentOptions"
                  item-title="name"
                  item-value="id"
                  return-object
                  clearable
                  :loading="respondentSearchLoading"
                  :label="$t('questionnaires.filter_respondent')"
                  @update:search="onRespondentSearch"
                />
              </VCol>
              <VCol
                cols="12"
                class="d-flex gap-2"
              >
                <VBtn
                  color="primary"
                  :loading="analyticsLoading"
                  @click="onApplyFilters"
                >
                  {{ $t('questionnaires.apply_filters') }}
                </VBtn>
                <VBtn
                  variant="tonal"
                  :disabled="analyticsLoading"
                  @click="onClearFilters"
                >
                  {{ $t('questionnaires.clear_filters') }}
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12">
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VCard>
              <VCardText>
                <div class="text-body-2 text-medium-emphasis">
                  {{ $t('questionnaires.kpi_targeted_users') }}
                </div>
                <div class="text-h4 font-weight-bold">
                  {{ kpis.targeted_users }}
                </div>
              </VCardText>
            </VCard>
          </VCol>
          <VCol
            cols="12"
            md="6"
          >
            <VCard>
              <VCardText>
                <div class="text-body-2 text-medium-emphasis">
                  {{ $t('questionnaires.kpi_completed') }}
                </div>
                <div class="text-h4 font-weight-bold">
                  {{ kpis.completed_submissions }}
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCol>

      <VCol cols="12">
        <VCard :loading="analyticsLoading">
          <VCardText>
            <h5 class="text-h5 font-weight-bold mb-4">
              {{ $t('questionnaires.submissions_heading') }}
            </h5>
            <div
              v-if="!submissionsPage.total"
              class="text-body-1 text-medium-emphasis py-6 text-center"
            >
              {{ $t('questionnaires.no_matching_submissions') }}
            </div>
            <template v-else>
              <VExpansionPanels variant="accordion">
                <VExpansionPanel
                  v-for="(submission, index) in submissionsPage.data"
                  :key="submission.id ?? `submission-${submission.user_id ?? 0}-${index}`"
                >
                  <VExpansionPanelTitle>
                    <div class="d-flex flex-column gap-1">
                      <span>{{ $t('questionnaires.respondent_account_name') }}: {{ formatAccountName(submission) }}</span>
                      <span class="text-body-2 text-medium-emphasis">
                        {{ $t('questionnaires.submission_date') }}: {{ formatSubmissionDate(submission) }}
                        —
                        {{ $t('questionnaires.submission_time') }}: {{ formatSubmissionTime(submission) }}
                        —
                        {{ $t('questionnaires.filter_completion') }}:
                        {{
                          submission.completion_status === 'completed'
                            ? $t('questionnaires.filter_completion_completed')
                            : $t('questionnaires.filter_completion_incomplete')
                        }}
                      </span>
                    </div>
                  </VExpansionPanelTitle>
                  <VExpansionPanelText>
                    <div
                      v-for="answerRow in submission.answers"
                      :key="`${submission.id}-${answerRow.question_id}`"
                      class="mb-3"
                    >
                      <div class="font-weight-medium">
                        {{ answerRow.question_title }}
                      </div>
                      <div class="text-body-2">
                        {{ formatAnswerDisplay(answerRow.answer) }}
                      </div>
                    </div>
                  </VExpansionPanelText>
                </VExpansionPanel>
              </VExpansionPanels>
              <div
                v-if="submissionsPage.total > submissionsPage.per_page"
                class="d-flex justify-center mt-4"
              >
                <VPagination
                  :model-value="submissionsPage.current_page"
                  :length="Math.ceil(submissionsPage.total / submissionsPage.per_page)"
                  @update:model-value="onSubmissionsPageChange"
                />
              </div>
            </template>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12">
        <template
          v-for="data in questionsList"
          :key="data.question_id"
        >
          <VCol>
            <VCard>
              <VCardText class="d-flex flex-wrap justify-space-between flex-column flex-sm-row print-row">
                <VCol
                  cols="12"
                  sm="10"
                >
                  <div class="d-flex align-center">
                    <h5 class="font-weight-bold text-capitalize text-h5" color="primary">
                      {{ $t('Question') }} {{ data.question_number }}: {{ data.title }}
                    </h5>
                  </div>
                </VCol>

                <VCol
                  cols="12"
                  sm="2"
                >
                  <VChip color="success">
                    {{ data.answered }} {{ $t('Answered') }}
                  </VChip>
                  &nbsp;
                  <VChip color="error">
                    {{ data.skipped }} {{ $t('Skipped') }}
                  </VChip>
                </VCol>
              </VCardText>

              <VCardText class="d-flex flex-wrap justify-space-between flex-column flex-sm-row print-row">
                <VDataTable
                  v-if="data.answers && (data.type == 1)"
                  :headers="translatedHeaders()"
                  :items="textAnswerItems(data.answers)"
                  :items-per-page="10"
                  class="mb-5 dataTable-hidescroller-y"
                />
                <VDataTable
                  v-if="data.option_stats"
                  :headers="choiceStatsHeaders()"
                  :items="data.option_stats.map(row => ({ ...row, percent: formatPercent(row.percent) }))"
                  :items-per-page="10"
                  class="mb-5 dataTable-hidescroller-y"
                />
                <VCol
                  v-if="data.option_stats && data.chart_series?.some(value => value > 0)"
                  cols="12"
                  sm="12"
                >
                  <VueApexCharts
                    type="donut"
                    height="250"
                    :options="donutChartConfigForQuestion(data)"
                    :series="data.chart_series"
                  />
                </VCol>
                <VCol
                  v-else-if="(data.type == 2) && data.chart_series?.some(value => value > 0)"
                  cols="12"
                  sm="12"
                >
                  <VueApexCharts
                    type="donut"
                    height="250"
                    :options="{ ...expenseRationChartConfig, labels: [$t('Yes'), $t('No')] }"
                    :series="data.chart_series"
                  />
                </VCol>
              </VCardText>
            </VCard>
          </VCol>
        </template>
      </VCol>
    </VRow>
    <SnackbarComponent ref="snackbarRef" />
  </section>
</template>

<style lang="scss">
.questionnaire-preview-table {
  --v-table-row-height: 80px !important;
}

@media print {
  .v-application {
    background: none !important;
  }

  @page { margin: 0; size: auto; }

  .layout-page-content,
  .v-row,
  .v-col-md-9 {
    padding: 0;
    margin: 0;
  }

  .product-buy-now {
    display: none;
  }

  .v-navigation-drawer,
  .layout-vertical-nav,
  .app-customizer-toggler,
  .layout-footer,
  .layout-navbar,
  .layout-navbar-and-nav-container {
    display: none;
  }

  .v-card {
    box-shadow: none !important;

    .print-row {
      flex-direction: row !important;
    }
  }

  .layout-content-wrapper {
    padding-inline-start: 0 !important;
  }
}
</style>
<route lang="yaml">
  meta:
    action: admin_questionnaires
    subject: admin_questionnaires
</route>
