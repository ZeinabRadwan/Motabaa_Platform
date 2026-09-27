<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { useRoute, useRouter } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import MessageSend from '@/views/messages/MessageSend.vue';
import Message from '@/views/messages/Message.vue';
import { can } from '@layouts/plugins/casl'
import { returnIdUserIfNotAdmin, isUser } from "@core/utils/helper";
import {
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
import {messagesApi} from "@/plugins/apis/messagesReqest"
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import { canRunEducationSessions, goalAutocompleteTitle, isParentUser } from '@core/utils/staffSessionVisibility'
import { useTheme } from 'vuetify'


const route = useRoute()
const router = useRouter()
const casesReqest = casesApi()
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
const totalSteps = ref(1)
const procedural_objectives = ref(null)
const number_of_attempts = ref(null)
const successful_attempts = ref(null)
const performance_evaluation = ref(null)
const reinforcement = ref(null)
const selectedGoals = ref(null)
const selectedGoalData = ref(null)
const goals = ref([])
const steps = ref([])
const messages = ref([])
const adding = ref(false)
const addAnother = ref(true);
const isDialogVisible = ref(false)
const isDialogVisibleDate = ref(false)
const search = ref()
const casesItem = ref([]);
const selectedCase = ref(null);
const selectedTerm = ref(null);
const itemsTerm = ref(null);
const teacherItems = ref([])
const teacher = ref(returnIdUserIfNotAdmin() ?? null)
const date = ref(null)
const time = ref(null)
const isEndedSession = ref(true);
const uploadPercentage = ref(0);
const isDraggable = ref(false);

const errorsMessage = ref({
  file: undefined,
  image: undefined,
})
const loading = ref({
  users: true,
  cases: false,
  feilds:false,
  goals:false,
  steps:false,
})

const updatingGoal = ref(false);

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

onMounted(() => {
  termItems().then(data => {
    itemsTerm.value = data
  })
});


// A case belongs to a single teacher, so a previous pick may no longer be valid.
watch(teacher, () => {
  selectedCase.value = null
})

// Every other filter narrows the behavioural goal list, so one key drives both
// the dropdown refetch and dropping a goal that is no longer among the options.
const goalsFilterKey = computed(() => [
  teacher.value,
  selectedCase.value,
  selectedTerm.value,
].join('|'))

watch(goalsFilterKey, () => {
  selectedGoals.value = null
  selectedGoalData.value = null
})

watch(selectedGoals, query => {
  if(query == null){
    messages.value = []
  }else{
    query && fetchMessages()
  }
})

watch(isDialogVisibleDate, query => {
  window.scrollTo({
    top: 0,
  });
})

const fetchMessages = () => {
  if(selectedGoals.value != null && selectedGoals.value != ''){
    messagesReqest.fetchAll({
      q: searchQuery.value,
      goal_id: selectedGoals.value,
      options: { itemsPerPage: 50 },
      page: 1,

    }).then(response => {
      messages.value = response.data.data;
    }).catch(error => {
      console.error(error)
    })
  }
}

const fetchSteps = () => {
  steps.value = []
  if (isParentUser()) {
    return
  }
  if(selectedGoals.value != null && selectedGoals.value != ''){
    loading.value.steps = true
    goalsReqest.fetchSteps({
      q: searchQuery.value,
      goal_id: selectedGoals.value,
      options: options.value,
      page: options.value.page,

    }).then(response => {
      steps.value = response.data.data
      totalPage.value = Math.ceil(response.data.total / response.data.perPage)
      totalSteps.value = response.data.total
      options.value.page = response.data.currentPage
      loading.value.steps = false
    }).catch(error => {
      console.error(error)
    })
  }
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchSteps, {
  search: searchQuery,
  filters: () => [selectedGoals.value],
  options,
})

const searchTeachers = params => userListStore.searchItems({
  ...params,
  roles: ['teacher'],
})

const searchCases = params => casesReqest.selectItems({
  ...params,
  teacher_id: teacher.value,
}).then(response => {
  casesItem.value = response.data.data ?? []

  return response
})

const searchGoals = params => goalsReqest.selectItems({
  ...params,
  category: 'educational',
  teacher_id: teacher.value,
  case_id: selectedCase.value,
  term_id: selectedTerm.value,
}).then(response => {
  goals.value = response.data.data ?? []

  return response
})

if(!isParentUser()) {
  isDraggable.value = true
}

// Goals from several cases can be listed at once, so name the case as well.
const goalItemTitle = item => goalAutocompleteTitle(item, { selectedCase: selectedCase.value })

const translatedHeaders = () => {
  let headers = [
    {
      title: '#',
      key: 'order',
      width: '5%',
      sortable: false,
    },
    {
      title: 'goals.procedural_objectives',
      key: 'procedural_objectives',
      width: '40%',
      sortable: false,
    },
    {
      title: 'goals.number_of_attempts',
      key: 'number_of_attempts',
      width: '10%',
      sortable: false,
    },
    {
      title: 'goals.successful_attempts',
      key: 'successful_attempts',
      width: '10%',
      sortable: false,
    },
    {
      title: 'goals.performance_evaluation',
      key: 'performance_evaluation',
      width: '10%',
      sortable: false,
    },
    {
      title: 'goals.reinforcement',
      key: 'reinforcement',
      width: '10%',
      sortable: false,
    }
  ]

  if(!isParentUser()) {
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

const addStep = () => {
  window.scrollTo({
    top: 0,
  });
  addAnother.value = true;
  isDialogVisible.value = true;
  adding.value = true;
  procedural_objectives.value = null;
  number_of_attempts.value = null;
  successful_attempts.value = null;
  performance_evaluation.value =null;
  reinforcement.value =null;
  selectedStep.value =null;
}

const editStep = (data) => {
  window.scrollTo({
    top: 0,
  });
  addAnother.value = false;
  adding.value = false;
  isDialogVisible.value = true;
  procedural_objectives.value = data.procedural_objectives;
  number_of_attempts.value = data.attempts;
  successful_attempts.value = data.successful_attempts;
  performance_evaluation.value = data.performance_evaluation || `${data.performance_evaluation}` === '0' ? `${data.performance_evaluation}` : null ;
  reinforcement.value =data.reinforcement;
  selectedStep.value = data.id
}

const saveStep = (closeDialog = false) => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){
      goalsReqest.putSteps({
        goal_id: selectedGoals.value,
        procedural_objectives : procedural_objectives.value,
        attempts : number_of_attempts.value,
        successful_attempts : successful_attempts.value,
        performance_evaluation : performance_evaluation.value,
        reinforcement : reinforcement.value,
        total : totalSteps.value,
      }, selectedStep.value).then(() =>{
        isDialogVisible.value = closeDialog;
        procedural_objectives.value = ''
        fetchSteps()
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success');
        refVForm.value?.reset();
        refVForm.value?.resetValidation();
      })
    }
  });

}

const selectedGoal = (value) => {
  selectedGoalData.value = value ? (goals.value.find(item => item.id === value) ?? null) : null
  const goal = selectedGoalData.value
  if(goal){
    if(goal.started_session != '' && goal.started_session != null){
      isEndedSession.value = goal.is_ended_session;
    }else{
      isEndedSession.value = true;
    }
  }
}

const startSession = () => {
  if(!isEndedSession.value){
    goalsReqest.endSession(selectedGoals.value, {datetime: date.value + ' ' +time.value, type:"educational"}).then(() =>{
      fetchMessages();
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('goals.session_ended_successfully'), 'success');
      isDialogVisibleDate.value = false;
      // date.value = null;
      isEndedSession.value = true;
    })
  }else{
    goalsReqest.startSession(selectedGoals.value, {datetime: date.value + ' ' +time.value, type:"educational"}).then(() =>{
      fetchMessages();
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('goals.session_started_successfully'), 'success');
      isDialogVisibleDate.value = false;
      isEndedSession.value = false;
      // date.value = null;
    })
  }
}

const sendMeesage = (data, callback) => {
  data['goal_id'] = selectedGoals.value
  messagesReqest.put(data, (progress)=>{
      uploadPercentage.value = progress
  }).then(() =>{
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success');
    fetchMessages();
    callback()
  }).catch(error => {
    callback()
    errorsMessage.value = error.response?.data?.errors || {}
  })
}

const deleteMessage = (id, callback) => {
  messagesReqest.delete(id).then(() =>{
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('Deleted successfully.'), 'success');
    fetchMessages();
    callback()
  }).catch(error => {
    callback()
    errorsMessage.value = error.response?.data?.errors || {}
  })
}

const startEndSession = () =>{
  const today = new Date();
  const tomorrow = new Date(today);
  tomorrow.setDate(today.getDate() + 1);
  const formattedTomorrow = tomorrow.toLocaleDateString('en-CA', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
  });
  const hours = today.getHours();
  const minutes = today.getMinutes();
  const currentTimeInHiFormat = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
  const currentDateInYmdFormat = `${today.getFullYear()}-${(today.getMonth() + 1).toString().padStart(2, '0')}-${today.getDate().toString().padStart(2, '0')}`;
  time.value = currentTimeInHiFormat
  date.value = currentDateInYmdFormat
  isDialogVisibleDate.value = true
}

const deleteStep = id => {
  goalsReqest.deleteSteps(id).then(() =>{
    fetchSteps()
  })
}

const restoreStep = id => {
  goalsReqest.restoreSteps(id).then(() =>{
    fetchSteps()
  })
}

const startDarg = (e, item) => {
  if(!isParentUser()) {
    e.dataTransfer.setData('itemID', e.currentTarget.dataset.id)
    e.dataTransfer.setData('itemOrder', e.currentTarget.dataset.order)
  }
}

const onDrop = (e) => {
  if(!isParentUser()) {
    var itemID = e.dataTransfer.getData('itemID')
    var itemOrder = e.dataTransfer.getData('itemOrder')
    var itemNewOrder = e.currentTarget.dataset.order

    if(itemOrder < itemNewOrder)
      itemNewOrder -= 1;

    if(itemOrder != itemNewOrder) {
      goalsReqest.reOrderSteps(itemID, {
        new_order: itemNewOrder,
      }).then(response => {
          fetchSteps()
      }).catch(error => {
          console.error(error)
      })
    }
  }
}

</script>

<template>
  <section>
    <VRow class="mb-4">
      <VCol cols="12">
        <VCard>
          <!-- 👉 Filters -->
          <VCardText>
            <VRow>
              <VCol
                v-if="!returnIdUserIfNotAdmin() && !isParentUser()"
                cols="12"
                sm="4"
              >
                <AppAutocomplete
                  v-model="teacher"
                  :server-search="searchTeachers"
                  preload
                  :item-title="'name'"
                  :item-value="'id'"
                  :label="$t('teacher')"
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppAutocomplete
                  v-model="selectedCase"
                  :server-search="searchCases"
                  preload
                  :preload-key="teacher"
                  :label="$t('Cases')"
                  :item-title="'name'"
                  :item-value="'id'"
                  clearable
                  clear-icon="tabler-x"
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
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                cols="12"
                sm="8"
              >
                <AppAutocomplete
                  v-model="selectedGoals"
                  @update:modelValue="selectedGoal"
                  :label="$t('goals.goal_behavioral')"
                  :server-search="searchGoals"
                  preload
                  :preload-limit="200"
                  :preload-key="goalsFilterKey"
                  :item-title="goalItemTitle"
                  :item-value="'id'"
                  clearable
                  clear-icon="tabler-x"
                >
                </AppAutocomplete>
              </VCol>

            </VRow>
          </VCardText>
        </VCard>
      </vcol>
    </VRow>

    <VCard
      v-if="!isParentUser()"
      class="mb-4"
    >
      <v-card-title>
        <div class="d-flex flex-wrap py-4 gap-4">
          <div class="me-3 d-flex gap-3 align-center">
            <VIcon icon="tabler-analyze" />
            <h5 class="text-h5">
              {{$t('goals.goal_analysis')}} 
              <span v-if="selectedGoalData?.case">
                (<RouterLink
                  :to="{ name: 'cases-view-tab-id', params: { id: selectedGoalData.case.id, tab: 'info' } }"
                  class="font-weight-medium user-list-name"
                >
                  {{ selectedGoalData.case.name }}
                </RouterLink>)
            </span>
            </h5>
          </div>
          <VSpacer />

          <div class="justify-end d-flex align-center flex-wrap gap-4">
            <VBtn v-if="isEndedSession && canRunEducationSessions()" color="success" :disabled="selectedGoals == null || selectedGoals == ''" @click="startEndSession()">
              {{ $t('goals.start_session') }}
            </VBtn>
            <VBtn v-if="!isEndedSession && canRunEducationSessions()" color="error" :disabled="selectedGoals == null || selectedGoals == ''" @click="startEndSession()">
              {{ $t('goals.end_session') }}
            </VBtn>
            <VBtn  v-if="canRunEducationSessions()" :disabled="selectedGoals == null || selectedGoals == ''" @click="addStep">
              {{ $t('goals.add_procedural_goal') }}
            </VBtn>
          </div>
        </div>
      </v-card-title>

      <VDivider />

      <!-- SECTION datatable -->
      <VTable 
        id="stepsTable"
        class="dataTable-hidescroller-y"
        @dragenter.prevent
        @dragover.prevent
      >
        <thead>
          <tr>
            <th 
              v-for="(header, headerKey) in translatedHeaders()"
              :key="headerKey"
              :width="header.width"
            >
              {{ header.title }}
            </th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-if="steps.length>0"
            v-for="(step, stepKey) in steps"
            :key="stepKey"
            :class="isDraggable ? 'draggable_tr' : ''"
            :draggable="isDraggable"
            @dragstart="startDarg($event, step)"
            @drop="onDrop($event)"
            :data-id="step.id"
            :data-order="step.order"
            :id="'row'+step.order"
          >
              <td>
                {{step.order}}
              </td>
              
              <td>
                {{step.procedural_objectives}}
              </td>

              <td>
                {{step.attempts}}
              </td>

              <td>
                <div class="align-center">
                    <span>{{ step.successful_attempts }}</span>
                </div>
              </td>

              <td>
                <div class="align-center" v-if="step.performance_evaluation != null && performance_evaluation != ''">
                    <span>{{ performanceEvaluationItemsSearch(`${step.performance_evaluation}`)[0].title }}</span>
                </div>
                <div v-else></div>
              </td>

              <td>
                <div class="align-center">
                  <span v-if="step.reinforcement">{{ $t(step.reinforcement) }}</span>
                </div>
              </td>

              <td v-if="!isParentUser()">
                <IconBtn v-if="canRunEducationSessions()" :title="$t('Edit')" @click="editStep(step)">
                  <VIcon icon="tabler-edit" />
                </IconBtn>
                <IconBtn v-if="!step.deleted_at && (can('admin_education-sessions','admin_education-sessions') || (step.created_by && isUser(step.created_by?.id)))" :title="$t('delete')" @click="deleteStep(step.id)">
                  <VIcon icon="tabler-trash" />
                </IconBtn>
                <IconBtn v-if="step.deleted_at && (can('admin_education-sessions','admin_education-sessions') || (step.created_by && isUser(step.created_by?.id)))" :title="$t('restore')" @click="restoreStep(step.id)">
                  <VIcon icon="tabler-refresh" />
                </IconBtn>
              </td>
          </tr>
        </tbody>
      </VTable>
      <VDivider />
      <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
        <p class="text-sm text-disabled mb-0">
          {{ paginationMeta(options, totalSteps) }}
        </p>
        <div class="d-flex align-center flex-wrap gap-4">
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
          <VPagination
            v-model="options.page"
            :disabled="loading.steps"
            total-visible="5"
            :length="Math.ceil(totalSteps / options.itemsPerPage) || 1"
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
      </div>
    </VCard>
    <MessageSend v-if="selectedGoals != null && selectedGoals != '' && (canRunEducationSessions() || isParentUser())" :isMeeting="false" class="mb-4" :errors="errorsMessage" @send-meesage="sendMeesage" :uploadPercentage="uploadPercentage"></MessageSend>
    <div>
      <Message 
        v-for="message in messages" :message="message" :key="message.id" :canDelete="can('admin_education-sessions','admin_education-sessions') || isUser(message.user_id)" @delete-message="deleteMessage" class="mb-4"></Message>
    </div>
    <VDialog
      persistent
      v-model="isDialogVisible"
      width="500"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isDialogVisible = !isDialogVisible" persistent />

      <!-- Dialog Content -->
      <VCard :title="$t('goals.procedural_goal')">
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
                <AppTextarea
                  auto-grow
                  v-model="procedural_objectives"
                  :label="$t('goals.procedural_objectives')"
                  :rules="[requiredValidator]"
                  required
                  rows="2"
                />
                </VCol>
                <VCol
                    cols="12"
                    md="6"
                  >
                    <AppTextField
                      v-model="number_of_attempts"
                      :label="$t('goals.number_of_attempts')"
                      :rules="[integerValidator]"
                      persistent-placeholder
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                    v-if="!adding"
                  >
                    <AppTextField
                      v-model="successful_attempts"
                      :label="$t('goals.successful_attempts')"
                      :rules="[integerValidator]"
                      persistent-placeholder
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                    v-if="!adding"
                  >
                    <AppSelect
                      v-model="performance_evaluation"
                      :label="$t('goals.performance_evaluation')"
                      :items="performanceEvaluationItems()"
                      clearable
                      clear-icon="tabler-x"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="6"
                  >
                    <AppSelect
                      v-model="reinforcement"
                      :items="reinforcementItems()"
                      :label="$t('goals.reinforcement')"
                      clearable
                      persistent-placeholder
                    />
                  </VCol>
              </VRow>
            </VForm>
          </VCardText>

        <VCardText class="d-flex justify-end gap-4">
          <VBtn v-if="addAnother" @click="saveStep(true)" color="info">
            {{ $t('save_add_another') }}
          </VBtn>
          <VBtn @click="saveStep()">
            {{ $t('Save') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isDialogVisibleDate"
      width="500"
      persistent
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isDialogVisibleDate = !isDialogVisibleDate" persistent />

      <!-- Dialog Content -->
      <VCard :title="isEndedSession ? $t('goals.start_session') : $t('goals.end_session')">
        <VCardText>
          <VForm >
            <SessionDateTimeFields
              v-model:date="date"
              v-model:time="time"
              stacked
              :disable-from="formattedTomorrow"
            />
          </VForm>
          </VCardText>

        <VCardText class="d-flex justify-end">
          <VBtn @click="startSession">
            {{ $t('Save') }}
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

.draggable_tr:hover {
  background: rgba(var(--v-theme-on-hover), var(--v-medium-emphasis-opacity));
  cursor: move; /* fallback if grab cursor is unsupported */
  cursor: grab;
  cursor: -moz-grab;
  cursor: -webkit-grab;
}

.draggable_tr:active {
    cursor: grabbing;
    cursor: -moz-grabbing;
    cursor: -webkit-grabbing;
}
</style>
<route lang="yaml">
  meta:
    action: access_education-sessions
    subject: access_education-sessions
</route>