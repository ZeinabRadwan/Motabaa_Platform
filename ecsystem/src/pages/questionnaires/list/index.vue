<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useRoute, useRouter } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { questionnairesApi } from "@/plugins/apis/questionnairesRequest";
import { termsApi } from "@/plugins/apis/termsRequest";
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
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
const questionnaireListStore = questionnairesApi()
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

const questionnaireDialog = (questionnaire) =>{
  putQuestionnaireDialog.value = true;
  taskID.value = questionnaire ? questionnaire.id : 0;
  newTerm.value = questionnaire ? Number(questionnaire.term_id) : '';
  newFromDate.value = questionnaire ? questionnaire.starts_at : '';
  newToDate.value = questionnaire ? questionnaire.ends_at : '';
}

const putQuestionnaire = () =>{
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){

      questionnaireListStore.putQuestionnaire({
        id: taskID.value,
        term_id: newTerm.value,
        starts_at: newFromDate.value, 
        ends_at: newToDate.value
      }).then(response => {
        if(response.status == 200){
          if(response.data.status) {
            snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data.message), 'success');
            fetchQuestionnaires()
          }
          else {
              snackbarRef.value.exposevisibleSnackbar(i18n.global.t("questionnaires."+response.data.message), 'error');
          }
          putQuestionnaireDialog.value = false;
        }
      }).catch((e) => {
          putQuestionnaireDialog.value = false;
      })
    }
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
        class="v-dialog-sm"
    >
      <VForm 
        ref="refVForm"
        @submit.prevent="putQuestionnaire"
      >
        <!-- Dialog close btn -->
        <DialogCloseBtn @click="putQuestionnaireDialog = !putQuestionnaireDialog" />

        <VCard :title="$t('Questionnaire')">
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                sm="12"
              >
                <VRow>
                  <VCol
                    cols="12"
                    sm="12"
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
                    >
                    </AppSelect>
                  </VCol>
                  <VCol
                    cols="12"
                    sm="6"
                  >
                    <AppDateTimePicker
                      v-model="newFromDate"
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
                      :placeholder="$t('to')"
                      :rules="[requiredValidator]"
                    />
                  </VCol>
                </VRow>
              </VCol>
            </VRow>
          </VCardText>
          <VCardText class="d-flex justify-end gap-3 flex-wrap">
            <VBtn type="submit" color="success">
                {{ $t('Save') }}
            </VBtn>
            <VBtn
            color="secondary"
            variant="tonal"
            @click="putQuestionnaireDialog = !putQuestionnaireDialog"
            >
                {{ $t('Close') }}
            </VBtn>
          </VCardText>
        </VCard>
      </VForm>
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
</style>

<route lang="yaml">
  meta:
    action: access_questionnaires
    subject: access_questionnaires
</route>