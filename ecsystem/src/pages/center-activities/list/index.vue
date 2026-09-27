<script setup>
import { watchServerTableFetch } from '@core/utils/tableFetch'
import { useRouter } from 'vue-router'
import { centerActivitiesApi } from '@/plugins/apis/centerActivitiesRequest'
import { termsApi } from '@/plugins/apis/termsRequest'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'
import i18n from '@/plugins/i18n/index.js'
import moment from '@/plugins/moment'
import { can } from '@layouts/plugins/casl'
import axios from '@axios'

const activitiesStore = centerActivitiesApi()
const termsStore = termsApi()
const router = useRouter()
const snackbarRef = ref(null)
const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
})

const searchQuery = ref('')
const activities = ref([])
const addDialog = ref(false)
const newTitle = ref('')
const newTermId = ref(null)
const newRoleIds = ref([])
const termItems = ref([])
const roleItems = ref([])
const saving = ref(false)

const canCreate = computed(() => can('add_center-activities', 'add_center-activities'))
const canEditActivity = computed(() => can('edit_center-activities', 'edit_center-activities'))

const fetchActivities = () => {
  activitiesStore.fetchAll({
    q: searchQuery.value,
    options: options.value,
    page: options.value.page,
  }).then(response => {
    activities.value = response.data.data
  })
}

const loadTerms = () => {
  termsStore.items({}).then(response => {
    termItems.value = (response.data.data || []).map(term => ({
      title: term.title,
      value: term.id,
    }))
  }).catch(() => {
    termItems.value = []
  })
}

const loadRoles = () => {
  axios.get('/roles/get/all').then(response => {
    roleItems.value = (response.data.data?.roles || []).map(role => ({
      title: role.name,
      value: role.id,
    }))
  }).catch(() => {
    roleItems.value = []
  })
}

const openCreate = () => {
  newTitle.value = ''
  newTermId.value = null
  newRoleIds.value = []
  loadTerms()
  loadRoles()
  addDialog.value = true
}

const saveActivity = () => {
  if (!newTitle.value?.trim() || !newTermId.value) {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.missing_field'), 'error')

    return
  }
  saving.value = true
  activitiesStore.put({
    title: newTitle.value.trim(),
    term_id: newTermId.value,
    role_ids: newRoleIds.value,
  }, -1).then(response => {
    saving.value = false
    if (response.status === 200) {
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success')
      addDialog.value = false
      fetchActivities()
      const id = response.data.data?.id
      if (id)
        router.push(`/center-activities/view/${id}`)
    }
  }).catch(() => {
    saving.value = false
  })
}

watchServerTableFetch(fetchActivities, {
  search: searchQuery,
  options,
})

onMounted(fetchActivities)
</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <VCard class="padding-30p">
          <VCard class="border-1p">
            <template #title>
              <VRow>
                <span class="v-col v-col-12 justify-lg-space-between d-flex align-center gap-4">
                  <div style="inline-size: 15rem;">
                    <h4 class="text-h4">
                      {{ $t('center_activities.title') }}
                    </h4>
                  </div>
                  <div class="d-flex gap-4">
                    <AppTextField
                      v-model="searchQuery"
                      style="inline-size: 15rem;"
                      :placeholder="$t('Search')"
                      density="compact"
                    />
                    <VBtn
                      v-if="canCreate"
                      @click="openCreate"
                    >
                      {{ $t('center_activities.add') }}
                    </VBtn>
                  </div>
                </span>
              </VRow>
            </template>
            <VDivider />
            <VCardText>
              <VRow>
                <VCol
                  v-for="activity in activities"
                  :key="activity.id"
                  cols="12"
                  md="4"
                >
                  <VCard
                    class="cursor-pointer"
                    @click="router.push(`/center-activities/view/${activity.id}`)"
                  >
                    <VCardText>
                      <h6 class="text-h6 mb-2">
                        {{ activity.title }}
                      </h6>
                      <p
                        v-if="activity.term"
                        class="mb-1 text-sm"
                      >
                        <span class="font-weight-bold">{{ $t('Qualifying class') }}:</span>
                        {{ activity.term.title }}
                      </p>
                      <p class="mb-1 text-sm">
                        <span class="font-weight-bold">{{ $t('Created By') }}:</span>
                        {{ activity.creator?.name }}
                      </p>
                      <p class="mb-1 text-sm">
                        <span class="font-weight-bold">{{ $t('Created At') }}:</span>
                        {{ moment(activity.created_at).locale($i18n.locale).format('D MMMM YYYY') }}
                      </p>
                      <p class="mb-1 text-sm">
                        <span class="font-weight-bold">{{ $t('center_activities.entries_count') }}:</span>
                        {{ activity.entries_count ?? 0 }}
                      </p>
                      <template v-if="canEditActivity">
                        <p
                          v-if="activity.visible_roles?.length"
                          class="mb-0 text-sm"
                        >
                          <span class="font-weight-bold">{{ $t('center_activities.visible_roles') }}:</span>
                          {{ activity.visible_roles.map(r => r.name).join(', ') }}
                        </p>
                        <p
                          v-else
                          class="mb-0 text-sm text-medium-emphasis"
                        >
                          {{ $t('center_activities.visible_all_staff') }}
                        </p>
                      </template>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
              <p
                v-if="!activities.length"
                class="text-center text-medium-emphasis pa-6"
              >
                {{ $t('No data available') }}
              </p>
            </VCardText>
          </VCard>
        </VCard>
      </VCol>
    </VRow>

    <VDialog
      v-model="addDialog"
      max-width="560"
    >
      <VCard>
        <VCardTitle>{{ $t('center_activities.add') }}</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="newTitle"
            :label="$t('center_activities.name')"
            class="mb-4"
          />
          <AppSelect
            v-model="newTermId"
            :items="termItems"
            :label="$t('Qualifying class')"
            class="mb-4"
          />
          <AppSelect
            v-model="newRoleIds"
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
            @click="addDialog = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="success"
            :loading="saving"
            @click="saveActivity"
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
