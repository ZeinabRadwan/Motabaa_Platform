<script setup>
import { fetchLinkedBeneficiaries, useParentBeneficiary } from '@/composables/useParentBeneficiary'
import { fetchUserNotifications } from '@/plugins/apis/notificationsRequest'
import { questionnairesApi } from '@/plugins/apis/questionnairesRequest'
import { centerActivitiesApi } from '@/plugins/apis/centerActivitiesRequest'
import { roomsApi } from '@/plugins/apis/roomsReqest'
import { useSessionStore } from '@/stores/useSessionStore'
import { createScopedLoader } from '@core/utils/accountScope'
import { isParentMeetingParticipant } from '@core/utils/meetingRoomAccess'
import { isParentUser } from '@core/utils/staffSessionVisibility'

const router = useRouter()
const questionnaireStore = questionnairesApi()
const activitiesStore = centerActivitiesApi()
const roomsStore = roomsApi()

const { selectedBeneficiary } = useParentBeneficiary()

const loading = ref(true)
const loadError = ref(false)

const linkedChildrenCount = ref(0)
const unreadNotificationsCount = ref(0)
const pendingQuestionnairesCount = ref(0)
const upcomingMeetingsCount = ref(0)
const recentUpdatesCount = ref(0)

const needsAttentionCount = computed(() =>
  unreadNotificationsCount.value + pendingQuestionnairesCount.value,
)

const formatCount = value => {
  if (value == null || Number.isNaN(value))
    return '—'

  if (value > 99)
    return '99+'

  return String(value)
}

const beginLoad = createScopedLoader()

const loadStats = async () => {
  const apply = beginLoad()

  loading.value = true
  loadError.value = false

  try {
    const tasks = [
      fetchLinkedBeneficiaries()
        .then(apply(list => { linkedChildrenCount.value = list.length }))
        .catch(apply(() => { linkedChildrenCount.value = 0 })),

      fetchUserNotifications({ per_page: 100, page: 1 })
        .then(apply(response => {
          const rows = response.data?.data ?? []

          unreadNotificationsCount.value = rows.filter(row => !row.read_at).length
        }))
        .catch(apply(() => { unreadNotificationsCount.value = 0 })),

      questionnaireStore.fetchRespondentAvailableTasks()
        .then(apply(response => {
          pendingQuestionnairesCount.value = (response.data?.data ?? []).length
        }))
        .catch(apply(() => { pendingQuestionnairesCount.value = 0 })),

      activitiesStore.fetchParentFeed({
        page: 1,
        options: { itemsPerPage: 1, page: 1, sortBy: [], groupBy: [] },
      })
        .then(apply(response => {
          recentUpdatesCount.value = Number(response.data?.total ?? 0)
        }))
        .catch(apply(() => { recentUpdatesCount.value = 0 })),
    ]

    if (isParentMeetingParticipant()) {
      tasks.push(
        roomsStore.fetchAll({
          page: 1,
          options: { page: 1, itemsPerPage: 50, sortBy: [], groupBy: [] },
        })
          .then(apply(response => {
            const rows = response.data?.data ?? []
            const now = Date.now()

            upcomingMeetingsCount.value = rows.filter(room => {
              const start = room?.start_at || room?.scheduled_at || room?.date
              if (!start)
                return false
              const when = new Date(start)

              return !Number.isNaN(when.getTime()) && when.getTime() >= now
            }).length
          }))
          .catch(apply(() => { upcomingMeetingsCount.value = 0 })),
      )
    }

    await Promise.all(tasks)
  }
  catch {
    apply(() => { loadError.value = true })()
  }
  finally {
    apply(() => { loading.value = false })()
  }
}

onMounted(loadStats)

const sessionStore = useSessionStore()

watch(() => `${sessionStore.userData?.id ?? ''}:${sessionStore.center ?? ''}`, () => {
  if (isParentUser())
    loadStats()
})

const statCards = computed(() => {
  const cards = [
    {
      id: 'children',
      icon: 'tabler-mood-kid',
      labelKey: 'dashboard.parent_stat_children',
      value: formatCount(linkedChildrenCount.value),
      accent: false,
      action: () => {
        document.getElementById('parent-dashboard-beneficiaries')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
      },
    },
    {
      id: 'attention',
      icon: 'tabler-bell-ringing',
      labelKey: 'dashboard.parent_stat_needs_attention',
      value: formatCount(needsAttentionCount.value),
      accent: needsAttentionCount.value > 0,
      action: () => {
        document.getElementById('parent-dashboard-insights')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
      },
    },
    {
      id: 'updates',
      icon: 'tabler-calendar-event',
      labelKey: 'dashboard.parent_stat_center_updates',
      value: formatCount(recentUpdatesCount.value),
      accent: false,
      action: () => router.push({ name: 'parent-center-activities' }),
    },
  ]

  if (isParentMeetingParticipant()) {
    cards.push({
      id: 'meetings',
      icon: 'tabler-video',
      labelKey: 'dashboard.parent_stat_upcoming_meetings',
      value: formatCount(upcomingMeetingsCount.value),
      accent: upcomingMeetingsCount.value > 0,
      action: () => router.push({ name: 'meeting-rooms-list' }),
    })
  }

  return cards
})

const onCardActivate = card => {
  card.action?.()
}
</script>

<template>
  <section
    class="parent-dashboard-stats"
    :aria-label="$t('dashboard.parent_stats_aria')"
  >
    <header class="parent-dashboard-stats__header">
      <h2 class="parent-dashboard-stats__title">
        {{ $t('dashboard.parent_stats_title') }}
      </h2>
      <p
        v-if="selectedBeneficiary?.name"
        class="parent-dashboard-stats__context"
      >
        {{ $t('dashboard.parent_stat_active_child', { name: selectedBeneficiary.name }) }}
      </p>
    </header>

    <p
      v-if="loadError"
      class="parent-dashboard-stats__error text-medium-emphasis"
    >
      {{ $t('dashboard.parent_beneficiaries_error') }}
    </p>

    <div
      v-else-if="loading"
      class="parent-dashboard-stats__grid"
    >
      <div
        v-for="n in 4"
        :key="n"
        class="parent-dashboard-stats__card parent-dashboard-stats__card--skeleton"
      >
        <div class="parent-page-skeleton__bar" style="block-size: 0.75rem; inline-size: 55%;" />
        <div class="parent-page-skeleton__bar" style="block-size: 1.75rem; inline-size: 35%; margin-block-start: 0.5rem;" />
      </div>
    </div>

    <div
      v-else
      class="parent-dashboard-stats__grid"
    >
      <button
        v-for="card in statCards"
        :key="card.id"
        type="button"
        class="parent-dashboard-stats__card"
        :class="{ 'parent-dashboard-stats__card--accent': card.accent }"
        @click="onCardActivate(card)"
      >
        <span class="parent-dashboard-stats__icon-wrap">
          <VIcon
            :icon="card.icon"
            size="20"
          />
        </span>
        <span class="parent-dashboard-stats__body">
          <span class="parent-dashboard-stats__value">{{ card.value }}</span>
          <span class="parent-dashboard-stats__label">{{ $t(card.labelKey) }}</span>
        </span>
      </button>
    </div>
  </section>
</template>

<style lang="scss" scoped>
@import '@styles/parent-dashboard.scss';
</style>
