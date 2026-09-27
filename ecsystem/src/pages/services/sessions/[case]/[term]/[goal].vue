<script setup>
import i18n from '@/plugins/i18n/index.js'
import { useRoute, useRouter } from 'vue-router'
import moment from '@/plugins/moment'
import MessageSend from '@/views/messages/MessageSend.vue';
import { returnIdUserIfNotAdmin, isUser } from "@core/utils/helper";
import Message from '@/views/messages/Message.vue';
import { can, canDoes } from '@layouts/plugins/casl'
import { canRunTreatmentSessions, isParentUser } from '@core/utils/staffSessionVisibility'
import {
  scaleTypeFilterItems,
  termItems
} from '@core/utils/generalItems';
import {
requiredValidator
} from '@validators';


import { useUserListStore } from '@/views/apps/user/useUserListStore'
import {goalsApi} from "@/plugins/apis/goalsReqest"
import {casesApi} from "@/plugins/apis/casesReqest"
import {messagesApi} from "@/plugins/apis/messagesReqest"
import {termsApi} from "@/plugins/apis/termsRequest"
import SnackbarComponent from '@core/components/SnackbarCustom.vue';

const route = useRoute()
const router = useRouter()
const casesReqest = casesApi()
const messagesReqest = messagesApi()
const userListStore = useUserListStore()
const goalsReqest = goalsApi()
const termsReqest = termsApi()

const refVForm = ref()
const snackbarRef = ref(null);
const searchQuery = ref('')

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

const selectedGoal = ref(null)
const goals = ref([])
const goal = ref(null)
const messages = ref([])
const search = ref()
const casesItem = ref([]);
const selectedCase = ref(null);
const selectedTerm = ref(null);
const itemsTerm = ref([]);
const selectedScaleType = ref(null)
const specialist = ref(returnIdUserIfNotAdmin() ?? null)
const isDialogVisibleDate = ref(false)
const isEndedSession = ref(true);
const date = ref(currentDateInYmdFormat)
const time = ref(currentTimeInHiFormat)
const putGoalDialog = ref(false);
const putGoalID = ref(null);
const putGoalDescription = ref('');
const uploadPercentage = ref(0);

const errorsMessage = ref({
  file: undefined,
  image: undefined,
})
const loading = ref({
  users: true,
  cases: false,
  feilds:false,
  goals:false,
})

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

watch(isDialogVisibleDate, query => {
  window.scrollTo({
    top: 0,
  });
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
    selectedScaleType.value = selectedGoal.value.category
    if(selectedGoal.value.started_session != '' && selectedGoal.value.started_session != null){
      isEndedSession.value = selectedGoal.value.is_ended_session;
    }else{
      isEndedSession.value = true;
    }
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
};

fetchCase()
fetchTerm()
fetchGoal()

const startSession = () => {
  if(!isEndedSession.value){
    goalsReqest.endSession(route.params.goal, {datetime: date.value + ' ' +time.value, type: selectedScaleType.value}).then(() =>{
      fetchMessages();
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('goals.session_ended_successfully'), 'success');
      isDialogVisibleDate.value = false;
      date.value = null;
      isEndedSession.value = true;
    })
  }else{
    goalsReqest.startSession(route.params.goal, {datetime: date.value + ' ' +time.value, type: selectedScaleType.value}).then(() =>{
      fetchMessages();
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('goals.session_started_successfully'), 'success');
      isDialogVisibleDate.value = false;
      isEndedSession.value = false;
      date.value = null;
    })
  }
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

const goalDialog = (goal) =>{
  putGoalDialog.value = true;
  putGoalID.value = goal ? goal.id : null;
  putGoalDescription.value = goal ? goal.description : '';
}

const putGoal = () =>{
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){
      
      const formData = new FormData();
      formData.append('title', selectedGoal.value.title);
      formData.append('description', putGoalDescription.value);

      goalsReqest.put(formData, putGoalID.value).then(response => {
        if(response.data['status']){
          selectedGoal.value.description = putGoalDescription.value;
          putGoalDialog.value = false;
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
        }

      }).catch((e=>{
        const { errors: formErrors } = e.response.data
        errors.value = formErrors
      }))
    }
  })
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
                cols="12"
                sm="4"
              > 
                <AppAutocomplete
                  :items="scaleTypeFilterItems()"
                  v-model="selectedScaleType"
                  :label="$t('goals.service_type')"
                  :disabled="true"
                  clear-icon="tabler-x"
                  clearable
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppAutocomplete
                  v-model="selectedCase"
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
                sm="4"
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
                <AppSelect
                  v-model="selectedGoal"
                  :label="$t('therapeutic_goal')"
                  :loading="loading.goals"
                  :item-title="'title'"
                  :item-value="'id'"
                  :items="goals"
                  :placeholder="$t('Type to search')"
                  :disabled="true"
                  clear-icon="tabler-x"
                  clearable
                >
                </AppSelect>
              </VCol>

            </VRow>
          </VCardText>
        </VCard>
      </vcol>
    </vrow>

    <VCard class="mb-4" v-if="selectedGoal">
      <v-card-title>
        <div class="d-flex flex-wrap py-4 gap-4">
            <div class="me-3 d-flex gap-3">
            <h5 class="text-h5">
              {{ $t('description') }} 
              (<RouterLink
                v-if="selectedCase"
                :to="{ name: 'cases-view-tab-id', params: { id: selectedCase.id, tab: 'info' } }"
                class="font-weight-medium user-list-name"
              >
                {{ selectedCase.name }}
              </RouterLink>)
            </h5>
          </div>
          <VSpacer />
          <VBtn  
            v-if="canRunTreatmentSessions()" 
            prepend-icon="tabler-edit"
            @click="goalDialog(selectedGoal)"
          >
            {{ $t('Edit') }}
          </VBtn>

          <div class="justify-end d-flex align-center flex-wrap gap-4">
            <VBtn  v-if="isEndedSession  && canRunTreatmentSessions()" color="success" :disabled="selectedGoal == null || selectedGoal == ''" @click="startEndSession()">
                {{ $t('goals.start_session') }}
              </VBtn>
              <VBtn  v-if="!isEndedSession && canRunTreatmentSessions()" color="error" :disabled="selectedGoal == null || selectedGoal == ''" @click="startEndSession()">
                {{ $t('goals.end_session') }}
              </VBtn>
          </div>
        </div>
      </v-card-title>
      <VCardText >
        <VRow>
          <VCol
            cols="12"
          >
            <div class="d-inline-block">
              <span class="text-subtitle-2"> {{ selectedGoal.description }}</span>

            </div>

          </VCol>
        </VRow>
        <VRow>
          <VCol
            cols="4"
          >
            <span class="text-subtitle-2 mb-1 d-block">{{$t('goals.date_from')}}</span>
            <VAvatar
              color="primary"
              icon="tabler-calendar"
            />
            <span class="text-subtitle-2 mr-2 ml-2 font-weight-bold">{{ moment(selectedGoal.date_from).locale(i18n.global.locale.value).format("D MMMM YYYY") == 'Invalid date'? '': moment(selectedGoal.date_from).locale(i18n.global.locale.value).format("D MMMM YYYY") }}<template v-if="!isParentUser()"> {{ moment(selectedGoal.started_session).locale(i18n.global.locale.value).format("D MMMM YYYY") == 'Invalid date'? '': '('+moment(selectedGoal.started_session).locale(i18n.global.locale.value).format("D MMMM YYYY")+')' }}</template></span>
          </VCol>
          <VCol
            cols="4"
          >
            <span class="text-subtitle-2 mb-1 d-block">{{$t('goals.date_to')}}</span>
            <VAvatar
              color="primary"
              icon="tabler-calendar"
            />
            <span class="text-subtitle-2 mr-2 ml-2 font-weight-bold">{{ moment(selectedGoal.date_to).locale(i18n.global.locale.value).format("D MMMM YYYY") == 'Invalid date'? '': moment(selectedGoal.date_to).locale(i18n.global.locale.value).format("D MMMM YYYY")  }}<template v-if="!isParentUser()"> {{ moment(selectedGoal.ended_session).locale(i18n.global.locale.value).format("D MMMM YYYY") == 'Invalid date'? '': '('+moment(selectedGoal.ended_session).locale(i18n.global.locale.value).format("D MMMM YYYY")+')'  }}</template></span>

          </VCol>
        </VRow>
      </VCardText>
    </VCard>
    <MessageSend v-if="selectedGoal != null && selectedGoal != '' && (canRunTreatmentSessions() || isParentUser())" :isMeeting="false" :errors="errorsMessage" @send-meesage="sendMeesage" :uploadPercentage="uploadPercentage" class="mb-4"></MessageSend>
    <div>
      <Message v-for="message in messages" :message="message" :key="message.id" :canDelete="can('admin_treatment-sessions','admin_treatment-sessions') || isUser(message.user_id)" @delete-message="deleteMessage" class="mb-4"></Message>
    </div>

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
    
    <VDialog
        v-model="putGoalDialog"
        persistent
        class="v-dialog-sm"
    >
      <VCard :title="$t('Edit')">
        <VForm 
            ref="refVForm"
            @submit.prevent="putGoal"
          >
          <VCardText>
            <VRow>
              <VCol cols="12">
                <AppTextarea
                  v-model="putGoalDescription"
                  :label="$t('description')"
                  class="pa-1"
                />
              </VCol>
            </VRow>
          </VCardText>
          <VCardText class="d-flex justify-end gap-3 flex-wrap">
              <VBtn
              color="secondary"
              variant="tonal"
              @click="putGoalDialog = false"
              >
                  {{ $t('Close') }}
              </VBtn>
              <VBtn type="submit" color="success">
                  {{ $t('Save') }}
              </VBtn>
          </VCardText>
        </VForm>
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
.dataTable > div{
  overflow-y: hidden !important;
}

.selected-item {
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  max-width: 500px; /* Adjust this width as needed */
}
</style>
<route lang="yaml">
  meta:
    action: access_treatment-sessions
    subject: access_treatment-sessions
</route>