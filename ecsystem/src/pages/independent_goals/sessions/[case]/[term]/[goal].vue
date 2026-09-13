<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { useRoute, useRouter } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import MessageSend from '@/views/messages/MessageSend.vue';
import Message from '@/views/messages/Message.vue';
import { can, canDoes } from '@layouts/plugins/casl'
import { returnIdUserIfNotAdmin, isUser } from "@core/utils/helper";
import {
  independentAssistanceTypeItems,
  performanceEvaluationItems,
  performanceEvaluationItemsSearch,
  termItems,
  reinforcementItems
} from '@core/utils/generalItems';
import {
integerValidator,
requiredValidator
} from '@validators';

import { useUserListStore } from '@/views/apps/user/useUserListStore'
import {goalsApi} from "@/plugins/apis/goalsReqest"
import {casesApi} from "@/plugins/apis/casesReqest"
import {termsApi} from "@/plugins/apis/termsRequest"
import {messagesApi} from "@/plugins/apis/messagesReqest"
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import { useTheme } from 'vuetify'

const route = useRoute()
const router = useRouter()
const casesReqest = casesApi()
const termsReqest = termsApi()
const messagesReqest = messagesApi()
const userListStore = useUserListStore()
const goalsReqest = goalsApi()
const vuetifyTheme = useTheme()

const today = new Date();
const tomorrow = new Date(today);
tomorrow.setDate(today.getDate() + 1);
const formattedTomorrow = tomorrow.toLocaleDateString('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
});

const refVForm = ref()
const snackbarRef = ref(null);
const searchQuery = ref('')
const selectedStep = ref(null)
const totalPage = ref(1)
const evaluationValue = ref(null)
const answerDate = ref(null)
const serviceType = ref(null)
const performance_evaluation = ref(null)
const reinforcement = ref(null)
const selectedGoal = ref(null)
const goals = ref([])
const messages = ref([])
const adding = ref(false)
const addAnother = ref(true);
const isDialogVisible = ref(false)
const dialogTitle = ref('')
const search = ref()
const casesItem = ref([]);
const selectedCase = ref(null);
const selectedTerm = ref(null);
const itemsTerm = ref([]);
const selectedFrom = ref(null);
const selectedTo = ref(null);
const teacherItems = ref([])
const teacher = ref(returnIdUserIfNotAdmin() ?? null)
const date = ref(null)
const time = ref(null)
const isEndedSession = ref(true);
const uploadPercentage = ref(0);
const isDraggable = ref(false);
const evaluations = ref([])
const totalEvaluations = ref(1)
const changedGoals = ref({});
const changedEvaluationGoals = ref({});
const evaluationID = ref(0);
const actionDialogID = ref()
const actionDialogAction = ref()
const actionDialogMessage = ref();
const actionDialogButton = ref();
const isActionDialogVisible = ref(false);

const errorsMessage = ref({
  file: undefined,
  image: undefined,
})
const loading = ref({
  users: true,
  cases: false,
  feilds:false,
  goals:false,
  evaluations:false,
})

const updatingGoal = ref(false);

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

const fetchMessages = () => {
  messagesReqest.fetchAll({
    q: searchQuery.value,
    goal_id: route.params.goal,
    options: { itemsPerPage: 50 },
    page: 1,

  }).then(response => {
    messages.value = response.data.data;
  }).catch(error => {
    console.error(error)
  })
}

const fetchGoal = () => {
  goalsReqest.fetchGoal(route.params.goal).then(response => {
    selectedGoal.value = response.data.data
  }).catch(error => {
    console.error(error)
  })
}

const fetchTerm = () => {
  termsReqest.fetchTerm(route.params.term).then(response => {
    selectedTerm.value = response.data.data
  }).catch(error => {
    console.error(error)
  })
}

const fetchCase = () => {
  loading.value.cases = true;
  casesReqest.fetchCase(route.params.case).then(response => {
    loading.value.cases = false;
    selectedCase.value = response.data.data
    casesItem.value = [{ id: response.data.data.id, name: response.data.data.name }]
    fetchMessages()
  }).catch(error => {
    console.error(error)
  })
}

fetchCase()
fetchTerm()
fetchGoal()

const fetchEvaluationsSteps = () => {
  loading.value.evaluations = true
  goalsReqest.fetchEvaluationsSteps({
    q: searchQuery.value,
    goal_id: route.params.goal,
    options: options.value,
    page: options.value.page,

  }).then(response => {

    evaluations.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalEvaluations.value = response.data.total
    options.value.page = response.data.currentPage
    loading.value.evaluations = false
  }).catch(error => {
    console.error(error)
  })
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchEvaluationsSteps, {
  search: searchQuery,
  options,
})
if(!canDoes('parent')) {
  isDraggable.value = true
}

const translatedHeaders = () => {
  let headers = [
    {
      title: 'Assessment',
      key: 'assessment',
      width: '25%',
      sortable: false,
    },
    {
      title: 'independent.service_type',
      key: 'service_type',
      width: '30%',
      sortable: false,
    },
    {
      title: 'date',
      key: 'date',
      width: '14%',
      sortable: false,
    },
    {
      title: 'goals.session_day',
      key: 'day',
      width: '12%',
      sortable: false,
    },
    {
      title: 'goals.session_time',
      key: 'time',
      width: '12%',
      sortable: false,
    },
  ]

  if(!canDoes('parent')) {
    headers.push({
      title: 'Actions',
      key: 'actions',
      width: '15%',
      sortable: false,
    })
  }

  let translatedHeaders = headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }));

  return translatedHeaders;
}

const sendMeesage = (data, callback) => {
  data['goal_id'] = route.params.goal
  messagesReqest.put(data, (progress)=>{
      uploadPercentage.value = progress
  }).then(() =>{
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success');
    fetchMessages();
    callback()
  }).catch(error => {
    callback()
    errorsMessage.value = error.response.data.errors
  })
}

const deleteMessage = (id, callback) => {
  messagesReqest.delete(id).then(() =>{
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('Deleted successfully.'), 'success');
    fetchMessages();
    callback()
  }).catch(error => {
    callback()
    errorsMessage.value = error.response.data.errors
  })
}

const evaluationStepDialog = (evaluation=null) => {
  window.scrollTo({
    top: 0,
  });
  addAnother.value = true;
  isDialogVisible.value = true;
  adding.value = true;

  evaluationID.value = 0;
  evaluationValue.value = null;
  answerDate.value = null;
  time.value = null;
  serviceType.value = null;
  dialogTitle.value = i18n.global.t('independent.add_assessment');

  if(evaluation) {
    evaluationID.value = evaluation.id;
    evaluationValue.value = String(evaluation.value);
    answerDate.value = evaluation.date;
    time.value = evaluation.time ? String(evaluation.time).slice(0, 5) : null;
    serviceType.value = String(evaluation.service_type);
    dialogTitle.value = i18n.global.t('independent.edit_assessment');
    addAnother.value = false;
  }
}

const putEvaluationStep = (closeDialog = false) => {

  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){

      goalsReqest.putEvaluationStep({
        id: evaluationID.value,
        goal_id: route.params.goal,
        service_type: serviceType.value,
        date: answerDate.value,
        time: time.value,
        value: evaluationValue.value,
      }).then(() =>{
        
        fetchEvaluationsSteps();
        isDialogVisible.value = closeDialog;

        if(closeDialog == true) {

          evaluationValue.value = null;
          answerDate.value = null;
          time.value = null;
          serviceType.value = null;
        }

        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('updated_successfully'), 'success');
      })
    }
  });
}

const getEvaluationMethod = () => {

  let itemsString = goals.value.assessment_evaluation_method?.items;
  if(!itemsString){
    itemsString = `[{"title": "Able", "value": "1", "ability": "power"}, {"title": "Able with help", "value": "2", "ability": "weak"}, {"title": "Need Training", "value": "3", "ability": "weak"}]`
  }
  let items = JSON.parse(itemsString);

  let translated = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }));

  return translated;
}

const getEvaluation = (value) => {

  let method = getEvaluationMethod()
  if(method && value)
    return method.find(item => item.value === String(value)).title

  return '';
}

const actionDialog = (id, action) =>{

  actionDialogID.value = id;
  actionDialogAction.value = action;
  if(action == 'delete_step') {
    actionDialogMessage.value = i18n.global.t('independent.Are you sure you want to delete this assessment?');
    actionDialogButton.value = i18n.global.t('delete');
  }
  isActionDialogVisible.value = true;
}

const actionDialogFunction = () =>{
  if(actionDialogAction.value == 'delete_step') {
    goalsReqest.deleteEvaluationStep(actionDialogID.value).then(() =>{
      fetchEvaluationsSteps();
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('Deleted successfully.'), 'success');
    }).catch(error => {
      errorsMessage.value = error.response.data.errors
    })
  }
  isActionDialogVisible.value = false;
}

const getAssistanceType = (value) => {

  let assistanceTypes = independentAssistanceTypeItems()
  if(assistanceTypes && value)
    return assistanceTypes.find(item => item.value === String(value)).title

  return '';
}

const sessionDayName = value => {
  if (!value)
    return ''

  const locale = i18n.global.locale.value === 'ar' ? 'ar' : 'en-US'

  return new Date(`${value}T00:00:00`).toLocaleDateString(locale, { weekday: 'long' })
}

const sessionTimeLabel = value => {
  if (!value)
    return ''

  return String(value).slice(0, 5)
}

</script>

<template>
  <section v-if="selectedCase && selectedTerm && selectedGoal">
    <VRow class="mb-4">
      <VCol cols="12">
        <VCard>
          <!-- 👉 Filters -->
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                sm="6"
              >
                <AppAutocomplete
                  v-model="selectedCase"
                  :label="$t('Cases')"
                  :item-title="'name'"
                  :item-value="'id'"
                  :loading="loading.cases"
                  :items="casesItem"
                  :disabled="true"
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedTerm"
                  :label="$t('term')"
                  :items="itemsTerm"
                  :disabled="true"
                />
              </VCol>
              <VCol
                cols="12"
                sm="8"
              >
                <AppAutocomplete
                  v-model="selectedGoal"
                  :label="$t('goals.skills')"
                  :items="goals"
                  :loading="loading.goals"
                  :item-title="'title'"
                  :item-value="'id'"
                  :disabled="true"
                >
                </AppAutocomplete>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="mb-4">

          <VCardText class="d-flex flex-wrap py-4 gap-4">
            <div class="me-3 d-flex gap-3">
              <AppSelect
                :model-value="options.itemsPerPage"
                :items="[
                  { value: 10, title: '10' },
                  { value: 25, title: '25' },
                  { value: 50, title: '50' },
                ]"
                style="width: 6.25rem;"
                @update:model-value="options.itemsPerPage = parseInt($event, 10)"
              />
            </div>
            <VSpacer />

            <div class="justify-end d-flex align-center flex-wrap gap-4">
              <VBtn  v-if="can('edit_independent-sessions','edit_independent-sessions')" @click="evaluationStepDialog()">
                {{ $t('independent.add_assessment') }}
              </VBtn>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="evaluations"
            :loading="loading.evaluations"
            :items-length="totalEvaluations"
            :headers="translatedHeaders()"
            class="dataTable-hidescroller-y dataTable"
            hide-default-footer
            @update:options="onTableOptions"
          >          
            <!-- User -->
            <template #item.assessment="{ item }">
              <div class="d-flex align-center" style="width: 130px; white-space: pre-wrap;">
                {{ getEvaluation(item.raw.value) }}
              </div>
            </template>

            <template #item.date="{ item }">
              <div class="align-center" style="width: 250px; white-space: pre-wrap;">
                {{ item.raw.date }}
              </div>
            </template>

            <template #item.day="{ item }">
              <div class="align-center">
                {{ sessionDayName(item.raw.date) }}
              </div>
            </template>

            <template #item.time="{ item }">
              <div class="align-center">
                {{ sessionTimeLabel(item.raw.time) }}
              </div>
            </template>

            <template #item.service_type="{ item }">
              <div class="align-center">
                  {{ getAssistanceType(item.raw.service_type) }}
              </div>
            </template>

            <!-- Actions -->
            <template #item.actions="{ item }">

              <IconBtn v-if="can('edit_independent-sessions','edit_independent-sessions')" :title="$t('Edit')" @click="evaluationStepDialog(item.raw)">
                <VIcon icon="tabler-edit" />
              </IconBtn>

              <IconBtn v-if="can('admin_independent-sessions','admin_independent-sessions')" :title="$t('Delete')" @click="actionDialog(item.raw.id, 'delete_step')">
                <VIcon icon="tabler-trash" />
              </IconBtn>

            </template>

            <!-- pagination -->
            <template #bottom>
              <VDivider />
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalEvaluations) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :disabled="loading.evaluations"
                  :length="Math.ceil(totalEvaluations / options.itemsPerPage)"
                  :total-visible="5"
                >
                  <template #prev="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      {{ $t('$vuetify.pagination.ariaLabel.previous') }}
                    </VBtn>
                  </template>

                  <template #next="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                    {{ $t('$vuetify.pagination.ariaLabel.next') }}
                    </VBtn>
                  </template>
                </VPagination>
              </div>
            </template>
          </VDataTableServer>
          <!-- SECTION -->
    </VCard>
    <MessageSend v-if="selectedGoal != null && selectedGoal != '' && (can('edit_independent-sessions','edit_independent-sessions') || canDoes('parent'))" :isMeeting="false" class="mb-4" :errors="errorsMessage" @send-meesage="sendMeesage" :uploadPercentage="uploadPercentage"></MessageSend>
    <div>
      <Message 
        v-for="message in messages" :message="message" :key="message.id" :canDelete="can('admin_independent-sessions','admin_independent-sessions') || isUser(message.user_id)" @delete-message="deleteMessage" class="mb-4"></Message>
    </div>
    <VDialog
      persistent
      v-model="isDialogVisible"
      width="500"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isDialogVisible = !isDialogVisible" persistent />

      <!-- Dialog Content -->
      <VCard :title="dialogTitle">
        <VCardText>
          <VForm 
              ref="refVForm"
              lazy-validation
              @submit.prevent="saveStep"
          >
            <VRow>
                <VCol
                  cols="12"
                  sm="12"
                > 
                  <AppSelect
                    v-model="evaluationValue"
                    :label="$t('Assessment')"
                    :items="getEvaluationMethod()"
                  />
                </VCol>
                <VCol
                    cols="12"
                    md="12"
                  >
                    <SessionDateTimeFields
                      v-model:date="answerDate"
                      v-model:time="time"
                      stacked
                      :disable-from="formattedTomorrow"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="12"
                  >
                    <AppSelect
                      v-model="serviceType"
                      :label="$t('independent.service_type')"
                      :items="independentAssistanceTypeItems()"
                      :rules="[requiredValidator]"
                      clear-icon="tabler-x"
                      clearable
                    />
                  </VCol>
              </VRow>
            </VForm>
          </VCardText>

        <VCardText class="d-flex justify-end gap-4">
          <VBtn v-if="addAnother" @click="putEvaluationStep(true)" color="info">
            {{ $t('save_add_another') }}
          </VBtn>
          <VBtn @click="putEvaluationStep()">
            {{ $t('Save') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isActionDialogVisible"
      persistent
      class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isActionDialogVisible = !isActionDialogVisible" />

      <!-- Dialog Content -->
      <VCard>
        <VCardText>
          {{ actionDialogMessage }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn @click="actionDialogFunction">
            {{ actionDialogButton }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isActionDialogVisible = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
    <SnackbarComponent ref="snackbarRef" />
  </section>
</template>

<style lang="scss">
.app-user-search-filter {
  inline-size: 31.6rem;
}

.text-capitalize {
  text-transform: capitalize;
}

.user-list-name:not(:hover) {
  color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
}
</style>
<route lang="yaml">
  meta:
    action: access_independent-sessions
    subject: access_independent-sessions
</route>