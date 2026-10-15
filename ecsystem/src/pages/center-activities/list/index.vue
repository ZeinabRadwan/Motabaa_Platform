<script setup>
import { watchServerTableFetch } from '@core/utils/tableFetch'
import { useRouter } from 'vue-router'
import { centerActivitiesApi } from '@/plugins/apis/centerActivitiesRequest'
import { termsApi } from '@/plugins/apis/termsRequest'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'
import PageHeaderActions from '@/components/PageHeaderActions.vue'
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
const listLoading = ref(false)
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
  listLoading.value = true
  activitiesStore.fetchAll({
    q: searchQuery.value,
    options: options.value,
    page: options.value.page,
  }).then(response => {
    activities.value = response.data.data
  }).finally(() => {
    listLoading.value = false
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

const openActivity = id => {
  router.push(`/center-activities/view/${id}`)
}

const visibilityLabel = activity => {
  if (! canEditActivity.value)
    return ''

  if (activity.visible_roles?.length)
    return activity.visible_roles.map(r => r.name).join(', ')

  return i18n.global.t('center_activities.visible_all_staff')
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
  <div class="center-activities-list-page">
    <PageHeaderActions v-if="canCreate">
      <VBtn
        class="general-page-header__btn-primary"
        prepend-icon="tabler-plus"
        @click="openCreate"
      >
        {{ $t('center_activities.add') }}
      </VBtn>
    </PageHeaderActions>

    <section
      class="center-activities-list-shell"
      :aria-label="$t('center_activities.title')"
    >
      <div class="center-activities-list-toolbar">
        <AppTextField
          v-model="searchQuery"
          class="center-activities-list-toolbar__search"
          :placeholder="$t('Search')"
          density="comfortable"
          prepend-inner-icon="tabler-search"
          hide-details
        />
      </div>

      <div class="center-activities-list-body">
        <div
          v-if="listLoading"
          class="center-activities-list-loader"
        >
          <VProgressCircular
            :size="48"
            color="primary"
            indeterminate
          />
        </div>
        <div
          v-else-if="!activities.length"
          class="center-activities-list-empty"
        >
          <span class="center-activities-list-empty__icon">
            <VIcon
              icon="tabler-calendar-off"
              size="28"
            />
          </span>
          <p class="center-activities-list-empty__title">
            {{ $t('center_activities.list_empty') }}
          </p>
          <VBtn
            v-if="canCreate"
            class="general-page-header__btn-primary mt-2"
            prepend-icon="tabler-plus"
            @click="openCreate"
          >
            {{ $t('center_activities.add') }}
          </VBtn>
        </div>
        <div
          v-else
          class="center-activities-list-grid"
        >
          <article
            v-for="activity in activities"
            :key="activity.id"
            class="center-activities-list-card"
            role="button"
            tabindex="0"
            @click="openActivity(activity.id)"
            @keydown.enter="openActivity(activity.id)"
          >
            <div class="center-activities-list-card__head">
              <span class="center-activities-list-card__icon">
                <VIcon
                  icon="tabler-calendar-event"
                  size="22"
                />
              </span>
              <div class="center-activities-list-card__title-wrap">
                <h3 class="center-activities-list-card__title">
                  {{ activity.title }}
                </h3>
                <span
                  v-if="activity.term"
                  class="center-activities-list-card__term"
                >
                  {{ activity.term.title }}
                </span>
              </div>
            </div>

            <div class="center-activities-list-card__meta">
              <div class="center-activities-list-card__row">
                <span class="center-activities-list-card__label">{{ $t('Created By') }}:</span>
                <span class="center-activities-list-card__value">{{ activity.creator?.name || '—' }}</span>
              </div>
              <div class="center-activities-list-card__row">
                <span class="center-activities-list-card__label">{{ $t('Created At') }}:</span>
                <span class="center-activities-list-card__value">
                  {{ moment(activity.created_at).locale($i18n.locale).format('D MMMM YYYY') }}
                </span>
              </div>
              <div
                v-if="canEditActivity"
                class="center-activities-list-card__row"
              >
                <span class="center-activities-list-card__label">{{ $t('center_activities.visible_roles') }}:</span>
                <span
                  class="center-activities-list-card__value"
                  :class="{ 'text-medium-emphasis': !activity.visible_roles?.length }"
                >
                  {{ visibilityLabel(activity) }}
                </span>
              </div>
            </div>

            <footer class="center-activities-list-card__footer">
              <span class="center-activities-list-card__count">
                <VIcon
                  icon="tabler-photo"
                  size="18"
                />
                {{ activity.entries_count ?? 0 }}
                {{ $t('center_activities.entries_count') }}
              </span>
              <VBtn
                variant="tonal"
                color="primary"
                size="small"
                @click.stop="openActivity(activity.id)"
              >
                {{ $t('center_activities.open_activity') }}
              </VBtn>
            </footer>
          </article>
        </div>
      </div>
    </section>

    <VDialog
      v-model="addDialog"
      max-width="560"
    >
      <VCard class="center-activities-list-dialog">
        <VCardTitle class="pa-4 pb-2">
          {{ $t('center_activities.add') }}
        </VCardTitle>
        <VCardText class="pt-2">
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
            class="mb-2"
          />
        </VCardText>
        <VCardActions class="pa-4 pt-0 justify-end gap-2">
          <VBtn
            variant="tonal"
            color="secondary"
            @click="addDialog = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            class="general-page-header__btn-primary"
            :loading="saving"
            @click="saveActivity"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>

<route lang="yaml">
meta:
  action: access_center-activities
  subject: access_center-activities
  pageTitleKey: center_activities.title
  pageSubtitleKey: center_activities.list_subtitle
</route>

<style lang="scss">
@import '@/styles/center-activities-list-page.scss';
</style>
