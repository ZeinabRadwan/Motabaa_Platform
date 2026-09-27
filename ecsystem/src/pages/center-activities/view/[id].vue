<script setup>
import { useRoute } from 'vue-router'
import MessageSend from '@/views/messages/MessageSend.vue'
import Message from '@/views/messages/Message.vue'
import { centerActivitiesApi } from '@/plugins/apis/centerActivitiesRequest'
import { termsApi } from '@/plugins/apis/termsRequest'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'
import i18n from '@/plugins/i18n/index.js'
import { can } from '@layouts/plugins/casl'
import axios from '@axios'

const route = useRoute()
const activitiesStore = centerActivitiesApi()
const termsStore = termsApi()
const snackbarRef = ref(null)

const activity = ref(null)
const entries = ref([])
const errorsMessage = ref({})
const uploadPercentage = ref(0)
const editDialog = ref(false)
const editTitle = ref('')
const editTermId = ref(null)
const editRoleIds = ref([])
const roleItems = ref([])
const termItems = ref([])

const userData = JSON.parse(localStorage.getItem('userData') || '{}')

const canManageContent = computed(() => can('manage_center-activities_content', 'manage_center-activities_content'))
const canEditActivity = computed(() => can('edit_center-activities', 'edit_center-activities'))
const canDeleteActivity = computed(() => can('delete_center-activities', 'delete_center-activities'))

const canDeleteEntry = entry => canManageContent.value
  && (canDeleteActivity.value || entry.user_id === userData?.id)

const fetchActivity = () => {
  activitiesStore.show(route.params.id).then(response => {
    activity.value = response.data.data
  }).catch(() => {
    activity.value = null
  })
}

const fetchEntries = () => {
  activitiesStore.fetchEntries(route.params.id, { page: 1, options: { itemsPerPage: 50 } }).then(response => {
    entries.value = response.data.data || []
  })
}

const loadRoles = () => {
  axios.get('/roles/get/all').then(response => {
    roleItems.value = (response.data.data?.roles || []).map(role => ({
      title: role.name,
      value: role.id,
    }))
  })
}

const loadTerms = () => {
  termsStore.items({}).then(response => {
    termItems.value = (response.data.data || []).map(term => ({
      title: term.title,
      value: term.id,
    }))
  })
}

const sendEntry = (data, done) => {
  activitiesStore.addEntry(route.params.id, data, progress => {
    uploadPercentage.value = progress
  }).then(() => {
    uploadPercentage.value = 0
    fetchEntries()
    if (done)
      done()
  }).catch(error => {
    uploadPercentage.value = 0
    errorsMessage.value = error.response?.data?.errors || {}
  })
}

const deleteEntry = id => {
  activitiesStore.deleteEntry(id).then(() => {
    fetchEntries()
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('deleted_successfully'), 'success')
  })
}

const openEdit = () => {
  editTitle.value = activity.value?.title || ''
  editTermId.value = activity.value?.term_id || null
  editRoleIds.value = (activity.value?.visible_roles || []).map(r => r.id)
  loadRoles()
  loadTerms()
  editDialog.value = true
}

const saveEdit = () => {
  if (!editTitle.value?.trim() || !editTermId.value) {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.missing_field'), 'error')

    return
  }
  activitiesStore.put({
    title: editTitle.value,
    term_id: editTermId.value,
    role_ids: editRoleIds.value,
  }, route.params.id).then(() => {
    editDialog.value = false
    fetchActivity()
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success')
  })
}

const deleteActivity = () => {
  activitiesStore.delete(route.params.id).then(() => {
    window.location.href = '/center-activities/list'
  })
}

onMounted(() => {
  fetchActivity()
  fetchEntries()
})
</script>

<template>
  <div>
    <VRow v-if="activity">
      <VCol cols="12">
        <VCard class="padding-30p mb-4">
          <VCardText>
            <div class="d-flex flex-wrap justify-space-between align-center gap-4 mb-4">
              <h3 class="text-h4 mb-0">
                {{ $t('center_activities.event_info') }}
              </h3>
              <div class="d-flex gap-2">
                <VBtn
                  v-if="canEditActivity"
                  variant="tonal"
                  @click="openEdit"
                >
                  {{ $t('Edit') }}
                </VBtn>
                <VBtn
                  v-if="canDeleteActivity"
                  color="error"
                  variant="tonal"
                  @click="deleteActivity"
                >
                  {{ $t('delete') }}
                </VBtn>
              </div>
            </div>
            <p class="text-h6 mb-2">
              {{ activity.title }}
            </p>
            <p
              v-if="activity.term"
              class="mb-1 text-sm"
            >
              <span class="font-weight-bold">{{ $t('Qualifying class') }}:</span>
              {{ activity.term.title }}
            </p>
            <p
              v-if="canEditActivity && activity.visible_roles?.length"
              class="mb-1 text-sm"
            >
              <span class="font-weight-bold">{{ $t('center_activities.visible_roles') }}:</span>
              {{ activity.visible_roles.map(r => r.name).join(', ') }}
            </p>
            <p
              v-else-if="canEditActivity"
              class="mb-1 text-sm text-medium-emphasis"
            >
              {{ $t('center_activities.visible_all_staff') }}
            </p>
          </VCardText>
        </VCard>

        <h4 class="text-h5 mb-3">
          {{ $t('center_activities.content_title') }}
        </h4>

        <MessageSend
          v-if="canManageContent"
          :errors="errorsMessage"
          :upload-percentage="uploadPercentage"
          :is-center-activity="true"
          @send-meesage="sendEntry"
        />

        <div
          v-for="entry in entries"
          :key="entry.id"
          class="mt-4"
        >
          <Message
            :message="entry"
            :can-delete="canDeleteEntry(entry)"
            is-center-activity-entry
            @delete-message="deleteEntry"
          />
        </div>
      </VCol>
    </VRow>

    <VDialog
      v-model="editDialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>{{ $t('center_activities.edit') }}</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="editTitle"
            :label="$t('center_activities.name')"
            class="mb-4"
          />
          <AppSelect
            v-model="editTermId"
            :items="termItems"
            :label="$t('Qualifying class')"
            class="mb-4"
          />
          <AppSelect
            v-model="editRoleIds"
            :items="roleItems"
            :label="$t('center_activities.visible_roles')"
            :hint="$t('center_activities.visible_roles_hint')"
            persistent-hint
            multiple
            chips
            closable-chips
            class="mb-4"
          />
        </VCardText>
        <VCardText class="d-flex justify-end gap-3">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="editDialog = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="success"
            @click="saveEdit"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>

<route lang="yaml">
  meta:
    action: access_center-activities
    subject: access_center-activities
</route>
