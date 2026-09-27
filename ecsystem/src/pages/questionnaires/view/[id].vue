<script setup>
import { useRoute, useRouter } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { questionnairesApi } from "@/plugins/apis/questionnairesRequest";
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { VDataTable } from 'vuetify/labs/VDataTable'
import data from '@/views/demos/forms/tables/data-table/datatable'
import VueApexCharts from 'vue3-apexcharts'
import { useTheme } from 'vuetify'
import { getDonutChartConfig } from '@core/libs/apex-chart/apexCharConfig'

const vuetifyTheme = useTheme()
const expenseRationChartConfig = computed(() => getDonutChartConfig(vuetifyTheme.current.value))

const questionnaireListStore = questionnairesApi()
const route = useRoute()
const questionnaire = ref()
const questionsData = ref([])
const questionsLink = ref()

// 👉 Fetch Questionnaire
questionnaireListStore.fetchQuestionnaire(Number(route.params.id)).then(response => {
  questionnaire.value = response.data.data
  questionsLink.value = `/questionnaires/questions/${Number(route.params.id)}`;
}).catch(() => {
})

// 👉 Fetch Questions
const fetchQuestionsWithAnswers = () => {
  questionnaireListStore.fetchQuestionsWithAnswers({
    task_id: Number(route.params.id)
  }).then(response => {
    questionsData.value = response.data.data
  }).catch(() => {
  })
}

const translatedHeaders = () => {
  const headers = [
    {
      title: '#',
      key: 'id',
    },
    {
      title: 'Questionnaire Answers',
      key: 'answer',
    },
  ]
  let translatedHeaders = headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }))
  return translatedHeaders;
}

watchEffect(fetchQuestionsWithAnswers)
</script>

<template>
  <section v-if="questionnaire">
    <VRow>
      <VCol
        cols="12"
        md="12"
      >
        <VCard>
          <!-- SECTION Header -->
          <VCardText class="d-flex flex-wrap justify-space-between flex-column flex-sm-row print-row">
            <div>
              <div class="d-flex align-center">
                <!-- 👉 Title -->
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
                      <a target="_blank" :href="questionsLink">{{ $t('questionnaires.Questionnaire Link') }}</a> 
                    </h7>
                  </VCol>
                </VRow>
              </div>
            </div>
          </VCardText>
          <!-- !SECTION -->
        </VCard>
      </VCol>

      <VCol>
        <template
          v-if="questionsData"
          v-for="(data, question) in questionsData"
          :key="question"
        >
          <VCol>
            <VCard>
              <VCardText class="d-flex flex-wrap justify-space-between flex-column flex-sm-row print-row">
                <VCol
                    cols="12"
                    sm="10"
                  >
                  <div class="d-flex align-center">
                    <!-- 👉 Title -->
                    <h5 class="font-weight-bold text-capitalize text-h5" color="primary">
                      {{ $t('Question') }} {{ question }}
                    </h5>
                  </div>
                </VCol>
                  
                <VCol
                  cols="12"
                  sm="2"
                >
                  <VChip
                    color="success"
                  >
                  {{ data['answered'] }} {{ $t('Answered') }}
                  </VChip>
                  &nbsp;
                  <VChip
                    color="error"
                  >
                  {{ data['skipped'] }} {{ $t('Skipped') }}
                  </VChip>
                </VCol>
              </VCardText>
              
              <VCardText class="d-flex flex-wrap justify-space-between flex-column flex-sm-row print-row">
                <VDataTable
                  v-if="data['answers'] && (data['type'] == 1)"
                  :headers="translatedHeaders()"
                  :items="data['answers']"
                  :items-per-page="5"
                  class="mb-5 dataTable-hidescroller-y"
                />
                <VCol
                    v-if="(data['type'] == 2) && (data['n_yes'] ||  data['n_no'])"
                    cols="12"
                    sm="12"
                  >
                  <VueApexCharts
                    type="donut"
                    height="250"
                    :options="expenseRationChartConfig"
                    :series="[data['n_yes'], data['n_no']]"
                  />
                </VCol>
              </VCardText>
            </VCard>
          </VCol>
        </template>
      </VCol>
    </VRow>
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
