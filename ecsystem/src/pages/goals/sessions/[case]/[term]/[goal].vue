<script setup>
import i18n from '@/plugins/i18n/index.js'
import { useRoute, useRouter } from 'vue-router'
import MessageSend from '@/views/messages/MessageSend.vue';
import Message from '@/views/messages/Message.vue';
import { can, canDoes } from '@layouts/plugins/casl'
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

const refVForm = ref()
const snackbarRef = ref(null);
const searchQuery = ref('')
const selectedStep = ref(null)
const totalSteps = ref(0)
const procedural_objectives = ref(null)
const number_of_attempts = ref(null)
const successful_attempts = ref(null)
const performance_evaluation = ref(null)
const reinforcement = ref(null)
const selectedGoal = ref(null)
const goals = ref([])
const steps = ref([])
const messages = ref([])
const adding = ref(false)
const addAnother = ref(true);
const isDialogVisible = ref(false)
const search = ref()
const casesItem = ref([]);
const selectedCase = ref(null);
const selectedTerm = ref(null);
const itemsTerm = ref([]);
const selectedFrom = ref(null);
const selectedTo = ref(null);
const teacherItems = ref([])
const teacher = ref(returnIdUserIfNotAdmin() ?? null)
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

const fetchSteps = async () => {
  steps.value = []
  loading.value.steps = true
  try {
    const first = await goalsReqest.fetchSteps({
      q: searchQuery.value,
      goal_id: route.params.goal,
      options: { itemsPerPage: 100, page: 1 },
      page: 1,
    })
    let all = first.data.data ?? []
    totalSteps.value = first.data.total ?? all.length
    const lastPage = first.data.lastPage ?? 1
    for (let page = 2; page <= lastPage; page++) {
      const next = await goalsReqest.fetchSteps({
        q: searchQuery.value,
        goal_id: route.params.goal,
        options: { itemsPerPage: 100, page },
        page,
      })
      all = all.concat(next.data.data ?? [])
    }
    steps.value = all
  } catch (error) {
    console.error(error)
  } finally {
    loading.value.steps = false
  }
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
    fetchSteps() 
    fetchMessages()
  }).catch(error => {
      console.error(error)
  })
};

fetchCase()
fetchTerm()
fetchGoal()

if(!canDoes('parent')) {
  isDraggable.value = true
}

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
        goal_id: route.params.goal,
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
  if(!canDoes('parent')) {
    e.dataTransfer.setData('itemID', e.currentTarget.dataset.id)
    e.dataTransfer.setData('itemOrder', e.currentTarget.dataset.order)
  }
}

const onDrop = (e) => {
  if(!canDoes('parent')) {
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
                  v-model:search="search"
                  :label="$t('Cases')"
                  :item-title="'name'"
                  :item-value="'id'"
                  :loading="loading.cases"
                  :placeholder="$t('Type Case Name')"
                  :items="casesItem"
                  :disabled="true"
                  clear-icon="tabler-x"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppSelect
                  v-model="selectedTerm"
                  :label="$t('term')"
                  :items="itemsTerm"
                  :disabled="true"
                  clear-icon="tabler-x"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                sm="8"
              >
                <AppAutocomplete
                  v-model="selectedGoal"
                  @update:modelValue="selectedGoal"
                  :label="$t('goals.goal_behavioral')"
                  :items="goals"
                  :loading="loading.goals"
                  :item-title="'title'"
                  :item-value="'id'"
                  :placeholder="$t('Type to search')"
                  :disabled="true"
                  clear-icon="tabler-x"
                  clearable
                >
                </AppAutocomplete>
              </VCol>

            </VRow>
          </VCardText>
        </VCard>
      </vcol>
    </VRow>

    <VCard class="mb-4">
      <v-card-title>
        <div class="d-flex flex-wrap py-4 gap-4">
          <div class="me-3 d-flex gap-3 align-center">
            <VIcon icon="tabler-analyze" />
            <h5 class="text-h5">
              {{$t('goals.goal_analysis')}} 
              <span v-if="selectedCase">
                (<RouterLink
                  :to="{ name: 'cases-view-tab-id', params: { id: selectedCase.id, tab: 'info' } }"
                  class="font-weight-medium user-list-name"
                >
                  {{ selectedCase.name }}
                </RouterLink>)
            </span>
            </h5>
          </div>
          <VSpacer />

          <div class="justify-end d-flex align-center flex-wrap gap-4">
            <VBtn v-if="can('edit_education-sessions','edit_education-sessions')" @click="addStep">
              {{ $t('goals.add_procedural_goal') }}
            </VBtn>
          </div>
        </div>
      </v-card-title>

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

              <td v-if="!canDoes('parent')">
                <IconBtn v-if="can('edit_education-sessions','edit_education-sessions')" :title="$t('Edit')" @click="editStep(step)">
                  <VIcon icon="tabler-edit" />
                </IconBtn>
                <IconBtn v-if="!step.deleted_at && can('admin_education-sessions','admin_education-sessions')" :title="$t('delete')" @click="deleteStep(step.id)">
                  <VIcon icon="tabler-trash" />
                </IconBtn>
                <IconBtn v-if="step.deleted_at && can('admin_education-sessions','admin_education-sessions')" :title="$t('restore')" @click="restoreStep(step.id)">
                  <VIcon icon="tabler-refresh" />
                </IconBtn>
              </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
    <MessageSend v-if="selectedGoal != null && selectedGoal != '' && (can('edit_education-sessions','edit_education-sessions') || canDoes('parent'))" :isMeeting="false" class="mb-4" :errors="errorsMessage" @send-meesage="sendMeesage" :uploadPercentage="uploadPercentage"></MessageSend>
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