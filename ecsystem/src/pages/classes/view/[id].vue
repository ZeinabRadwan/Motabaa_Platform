<script setup>
import { termsApi } from "@/plugins/apis/termsRequest";
import { useRoute } from 'vue-router';

const termListStore = termsApi()
const route = useRoute()
const termData = ref()

// 👉 fetchterm
termListStore.fetchTerm(Number(route.params.id)).then(response => {
  termData.value = response.data.data
}).catch(() => {
})

const evaluationDates = computed(() => {
  if (!termData.value)
    return []

  if (termData.value.evaluation_dates?.length)
    return termData.value.evaluation_dates

  return [
    termData.value.first_evaluation_at,
    termData.value.second_evaluation_at,
    termData.value.third_evaluation_at,
    termData.value.final_evaluation_at,
  ].filter(Boolean)
})
</script>

<template>
  <section v-if="termData">
    <VRow>
      <VCol
        cols="12"
        md="9"
      >
        <VCard>
          <!-- SECTION Header -->
          <VCardText class="d-flex flex-wrap justify-space-between flex-column flex-sm-row print-row">
            <div>
              <div class="d-flex align-center mb-6">
                <!-- 👉 Title -->
                <h4 class="font-weight-bold text-capitalize text-h4" color="primary">
                  {{ termData.title }}
                </h4>
              </div>

              <p class="mb-6">
                {{ $t('date') }}
              </p>
              
              <p class="mb-0">
                  <VChip color="primary" size="x-large">
                    <VIcon icon="tabler-calendar" />
                  </VChip>
                  {{ $t('from') }} {{ termData.starts_at }} {{ $t('to') }} {{ termData.ends_at }}
              </p>
            </div>
          </VCardText>
          <!-- !SECTION -->
        </VCard>
      </VCol>

      <VCol
        v-if="termData.users && termData.users.length"
        cols="12"
        md="9"
      >
        <VCard>
          <VCardText>
            <h5 class="text-h5 mb-4">
              {{ $t('Class staff') }}
            </h5>
            <div class="d-flex flex-wrap gap-2">
              <VChip
                v-for="user in termData.users"
                :key="user.id"
                label
              >
                {{ user.title || user.name }}
              </VChip>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="9"
      >
        <VCard>
          <!-- 👉 Table -->
          <VTable class="term-preview-table">
            <thead>
              <tr style="height: 80px;">
                <th style="font-size: 18px;">
                  {{ $t('goals.periods') }}
                </th>
                <th style="font-size: 18px;">
                  {{ $t('goals.period_date') }}
                </th>
              </tr>
              <tr
                v-for="(date, index) in evaluationDates"
                :key="index"
              >
                <td class="text-no-wrap">
                  {{ index === evaluationDates.length - 1 ? $t('goals.final_period') : $t('goals.period_n', { n: index + 1 }) }}
                </td>
                <td class="text-no-wrap">
                  {{ date }}
                </td>
              </tr>
            </thead>
          </VTable>
        </VCard>
      </VCol>
    </VRow>
  </section>
</template>

<style lang="scss">
.term-preview-table {
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
    action: show_qualifying-classes
    subject: show_qualifying-classes
</route>
