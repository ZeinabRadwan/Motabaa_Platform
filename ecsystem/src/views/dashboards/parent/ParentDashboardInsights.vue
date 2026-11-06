<script setup>
import { questionnairesApi } from '@/plugins/apis/questionnairesRequest'
import { fetchUserNotifications, markUserNotificationRead } from '@/plugins/apis/notificationsRequest'
import { centerActivitiesApi } from '@/plugins/apis/centerActivitiesRequest'
import { roomsApi } from '@/plugins/apis/roomsReqest'
import { useSessionStore } from '@/stores/useSessionStore'
import { createScopedLoader } from '@core/utils/accountScope'
import { isParentMeetingParticipant } from '@core/utils/meetingRoomAccess'
import { isParentUser } from '@core/utils/staffSessionVisibility'
import { questionnaireRespondPath } from '@/utils/questionnaireShare'

const router = useRouter()
const questionnaireStore = questionnairesApi()
const activitiesStore = centerActivitiesApi()
const roomsStore = roomsApi()

const questionnaireTasks = ref([])
const notifications = ref([])
const activityEntries = ref([])
const meetingRooms = ref([])

const loading = ref(true)

const beginLoad = createScopedLoader()

const loadAll = async () => {
  const apply = beginLoad()

  loading.value = true

  const tasks = []

  tasks.push(
    questionnaireStore.fetchRespondentAvailableTasks()
      .then(apply(response => { questionnaireTasks.value = response.data?.data ?? [] }))
      .catch(apply(() => { questionnaireTasks.value = [] })),
  )

  tasks.push(
    fetchUserNotifications({ per_page: 12 })
      .then(apply(response => {
        const rows = response.data?.data ?? []

        notifications.value = rows.filter(row => !row.read_at).slice(0, 5)
      }))
      .catch(apply(() => { notifications.value = [] })),
  )

  tasks.push(
    activitiesStore.fetchParentFeed({
      page: 1,
      options: { itemsPerPage: 5, page: 1, sortBy: [], groupBy: [] },
    })
      .then(apply(response => { activityEntries.value = response.data?.data ?? [] }))
      .catch(apply(() => { activityEntries.value = [] })),
  )

  if (isParentMeetingParticipant()) {
    tasks.push(
      roomsStore.fetchAll({
        page: 1,
        options: { page: 1, itemsPerPage: 5, sortBy: [], groupBy: [] },
      })
        .then(apply(response => { meetingRooms.value = response.data?.data ?? [] }))
        .catch(apply(() => { meetingRooms.value = [] })),
    )
  }

  await Promise.all(tasks)
  apply(() => { loading.value = false })()
}

onMounted(loadAll)

const sessionStore = useSessionStore()

watch(() => `${sessionStore.userData?.id ?? ''}:${sessionStore.center ?? ''}`, () => {
  if (isParentUser())
    loadAll()
})

const hasAnySection = computed(() => (
  questionnaireTasks.value.length > 0
  || notifications.value.length > 0
  || activityEntries.value.length > 0
  || meetingRooms.value.length > 0
))

const hasCompactInsightsRow = computed(() => (
  notifications.value.length > 0
  || meetingRooms.value.length > 0
  || activityEntries.value.length > 0
))

const openQuestionnaire = task => {
  router.push(questionnaireRespondPath(task.id))
}

const navigateFromNotification = item => {
  const raw = item?.url
  if (!raw)
    return

  try {
    const path = new URL(raw, window.location.origin).pathname

    router.push(path)
  }
  catch {
    if (raw.startsWith('/'))
      router.push(raw)
  }
}

const openNotification = item => {
  if (!item.url)
    return

  markUserNotificationRead(item.id).catch(() => {})
  navigateFromNotification(item)
}

const stripHtml = html => {
  if (!html)
    return ''
  const tmp = document.createElement('div')

  tmp.innerHTML = html

  return (tmp.textContent || tmp.innerText || '').trim()
}

const activityPreview = entry => stripHtml(entry.content).slice(0, 120)

const meetingIsUpcoming = room => {
  const start = room?.start_at || room?.scheduled_at || room?.date
  if (!start)
    return false

  const when = new Date(start)

  return !Number.isNaN(when.getTime()) && when.getTime() >= Date.now()
}
</script>

<template>
  <div
    v-if="loading"
    class="parent-dashboard-insights parent-dashboard-insights--loading"
    :aria-label="$t('dashboard.parent_insights_loading_aria')"
  >
    <div class="parent-dashboard-insights__row parent-dashboard-insights__row--loading">
      <div
        v-for="n in 3"
        :key="n"
        class="parent-page-skeleton parent-insight-block parent-insight-block--in-row"
      >
        <div
          class="parent-page-skeleton__bar"
          style="block-size: 0.75rem; inline-size: 55%; margin-block-end: 0.5rem;"
        />
        <div
          class="parent-page-skeleton__bar"
          style="block-size: 2rem; inline-size: 100%;"
        />
      </div>
    </div>
  </div>

  <div
    v-else-if="hasAnySection"
    class="parent-dashboard-insights"
  >
    <header class="parent-dashboard-insights__intro">
      <h2 class="parent-dashboard-insights__heading">
        {{ $t('dashboard.parent_section_actions') }}
      </h2>
      <p class="parent-dashboard-insights__lede">
        {{ $t('dashboard.parent_section_actions_hint') }}
      </p>
    </header>

    <section
      v-if="questionnaireTasks.length"
      class="parent-insight-block"
      :aria-label="$t('dashboard.parent_section_questionnaires')"
    >
      <div class="parent-insight-block__head">
        <h3 class="parent-insight-block__title">
          {{ $t('dashboard.parent_section_questionnaires') }}
        </h3>
        <VBtn
          v-if="questionnaireTasks.length > 1"
          variant="text"
          size="small"
          color="primary"
          @click="router.push({ name: 'questionnaires-respondent' })"
        >
          {{ $t('dashboard.parent_view_all') }}
        </VBtn>
      </div>
      <ul class="parent-insight-list">
        <li
          v-for="task in questionnaireTasks.slice(0, 3)"
          :key="task.id"
        >
          <button
            type="button"
            class="parent-insight-list__item"
            @click="openQuestionnaire(task)"
          >
            <VIcon
              icon="tabler-clipboard-list"
              size="20"
              class="parent-insight-list__icon"
            />
            <span class="parent-insight-list__text">{{ task.title || task.questionnaire?.title }}</span>
            <span class="parent-insight-status parent-insight-status--attention">
              {{ $t('dashboard.parent_status_needs_attention') }}
            </span>
            <VIcon
              icon="tabler-chevron-right"
              size="18"
              class="parent-insight-list__chevron"
            />
          </button>
        </li>
      </ul>
    </section>

    <div
      v-if="hasCompactInsightsRow"
      class="parent-dashboard-insights__row"
      :aria-label="$t('dashboard.parent_insights_row_aria')"
    >
      <section
        v-if="notifications.length"
        class="parent-insight-block parent-insight-block--in-row"
        :aria-label="$t('dashboard.parent_section_notifications')"
      >
        <div class="parent-insight-block__head parent-insight-block__head--compact">
          <div class="parent-insight-block__title-row">
            <VIcon
              icon="tabler-bell"
              size="18"
              class="parent-insight-block__title-icon"
            />
            <h3 class="parent-insight-block__title">
              {{ $t('dashboard.parent_section_notifications') }}
            </h3>
          </div>
        </div>
        <ul class="parent-insight-list parent-insight-list--compact">
          <li
            v-for="item in notifications"
            :key="item.id"
          >
            <button
              type="button"
              class="parent-insight-list__item"
              :disabled="!item.url"
              @click="openNotification(item)"
            >
              <span class="parent-insight-list__text">
                <strong>{{ item.title }}</strong>
                <span
                  v-if="item.message"
                  class="parent-insight-list__sub"
                >{{ item.message }}</span>
              </span>
              <span class="parent-insight-status parent-insight-status--attention">
                {{ $t('dashboard.parent_status_unread') }}
              </span>
            </button>
          </li>
        </ul>
      </section>

      <section
        v-if="meetingRooms.length"
        class="parent-insight-block parent-insight-block--in-row"
        :aria-label="$t('dashboard.parent_section_meetings')"
      >
        <div class="parent-insight-block__head parent-insight-block__head--compact">
          <div class="parent-insight-block__title-row">
            <VIcon
              icon="tabler-video"
              size="18"
              class="parent-insight-block__title-icon"
            />
            <h3 class="parent-insight-block__title">
              {{ $t('dashboard.parent_section_meetings') }}
            </h3>
          </div>
          <VBtn
            variant="text"
            size="x-small"
            color="primary"
            class="parent-insight-block__view-all"
            @click="router.push({ name: 'meeting-rooms-list' })"
          >
            {{ $t('dashboard.parent_view_all') }}
          </VBtn>
        </div>
        <ul class="parent-insight-list parent-insight-list--compact">
          <li
            v-for="room in meetingRooms.slice(0, 3)"
            :key="room.id"
          >
            <button
              type="button"
              class="parent-insight-list__item"
              @click="router.push(`/meeting-rooms/view/${room.id}`)"
            >
              <span class="parent-insight-list__text">{{ room.title }}</span>
              <span
                v-if="meetingIsUpcoming(room)"
                class="parent-insight-status"
              >
                {{ $t('dashboard.parent_status_upcoming') }}
              </span>
              <VIcon
                icon="tabler-chevron-right"
                size="16"
                class="parent-insight-list__chevron"
              />
            </button>
          </li>
        </ul>
      </section>

      <section
        v-if="activityEntries.length"
        class="parent-insight-block parent-insight-block--in-row"
        :aria-label="$t('dashboard.parent_section_activities')"
      >
        <div class="parent-insight-block__head parent-insight-block__head--compact">
          <div class="parent-insight-block__title-row">
            <VIcon
              icon="tabler-calendar-event"
              size="18"
              class="parent-insight-block__title-icon"
            />
            <h3 class="parent-insight-block__title">
              {{ $t('dashboard.parent_section_activities') }}
            </h3>
          </div>
          <VBtn
            variant="text"
            size="x-small"
            color="primary"
            class="parent-insight-block__view-all"
            @click="router.push({ name: 'parent-center-activities' })"
          >
            {{ $t('dashboard.parent_view_all') }}
          </VBtn>
        </div>
        <ul class="parent-insight-list parent-insight-list--compact">
          <li
            v-for="entry in activityEntries.slice(0, 3)"
            :key="entry.id"
          >
            <button
              type="button"
              class="parent-insight-list__item parent-insight-list__item--static"
              @click="router.push({ name: 'parent-center-activities' })"
            >
              <span class="parent-insight-list__text">
                <strong v-if="entry.activity?.title">{{ entry.activity.title }}</strong>
                <span
                  v-if="activityPreview(entry)"
                  class="parent-insight-list__sub"
                >{{ activityPreview(entry) }}</span>
              </span>
              <VIcon
                icon="tabler-chevron-right"
                size="16"
                class="parent-insight-list__chevron"
              />
            </button>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>

<style lang="scss" scoped>
@import '@styles/parent-dashboard.scss';
@import '@styles/parent-experience.scss';

.parent-dashboard-insights__intro {
  margin-block-end: 0.25rem;
}

.parent-dashboard-insights__heading {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 600;
  color: var(--v-theme-on-surface);
}

.parent-dashboard-insights__lede {
  margin: 0.35rem 0 0;
  font-size: 0.875rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}
</style>
