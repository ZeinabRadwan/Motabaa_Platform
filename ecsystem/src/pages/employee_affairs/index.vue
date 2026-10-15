<script setup>
import { statisticsApi } from '@/plugins/apis/statisticsRequest'
import { centerData } from '@/router/utils'
import { can } from '@layouts/plugins/casl'
import PageHeaderActions from '@/components/PageHeaderActions.vue'

const router = useRouter()
const statisticsListStore = statisticsApi()
const statistics = ref(null)
const loading = ref(true)
const center = centerData()

const showAttendance = computed(() => (
  center
  && Number(center.package_id) !== 2
  && Number(center.package_id) !== 3
  && can('access_employees-attendance', 'access_employees-attendance')
))

const canUsers = computed(() => can('access_users', 'access_users'))

const fetchDashboard = () => {
  loading.value = true
  statisticsListStore.fetchHrDashboard().then(response => {
    statistics.value = response.data.data
  }).catch(error => {
    console.error(error)
  }).finally(() => {
    loading.value = false
  })
}

fetchDashboard()

const go = path => {
  router.push(path)
}

const kpiCards = computed(() => {
  if (! statistics.value)
    return []

  return [
    {
      key: 'total',
      value: statistics.value.total_employees,
      labelKey: 'employee_affairs.total_employees',
      icon: 'tabler-users',
      tone: 'primary',
      to: '/user/list?status=all',
      show: canUsers.value,
    },
    {
      key: 'active',
      value: statistics.value.active_employees,
      labelKey: 'employee_affairs.active_employees',
      icon: 'tabler-user-check',
      tone: 'success',
      to: '/user/list?status=active',
      show: canUsers.value,
    },
    {
      key: 'contracts',
      value: statistics.value.expiring_contracts,
      labelKey: 'employee_affairs.expiring_contracts_kpi',
      icon: 'tabler-file-text',
      tone: 'warning',
      to: '/user/list?hr_alert=contracts_ending',
      show: canUsers.value,
    },
    {
      key: 'ids',
      value: statistics.value.expiring_ids_or_docs,
      labelKey: 'employee_affairs.expiring_ids_docs_kpi',
      icon: 'tabler-id',
      tone: 'danger',
      to: '/user/list?hr_alert=expiring_ids_or_docs',
      show: canUsers.value,
    },
    {
      key: 'pending_leaves',
      value: statistics.value.pending_leaves,
      labelKey: 'employee_affairs.pending_leaves',
      icon: 'tabler-clock',
      tone: 'violet',
      to: '/employee_leaves/list?status=pending',
      show: canUsers.value,
    },
    {
      key: 'on_leave',
      value: statistics.value.on_leave,
      labelKey: 'employee_affairs.on_leave',
      icon: 'tabler-calendar-off',
      tone: 'info',
      to: '/employee_leaves/list?current=1',
      show: canUsers.value,
    },
  ].filter(card => card.show)
})

const quickLinks = computed(() => [
  {
    key: 'users',
    titleKey: 'Career staff',
    hintKey: 'employee_affairs.link_employees_hint',
    icon: 'tabler-users-group',
    to: '/user/list',
    show: canUsers.value,
  },
  {
    key: 'attendance',
    titleKey: 'Staff attendance',
    hintKey: 'employee_affairs.link_attendance_hint',
    icon: 'tabler-calendar-check',
    to: '/employees_attendance/list',
    show: showAttendance.value,
  },
  {
    key: 'leaves',
    titleKey: 'employee_leaves.leaves',
    hintKey: 'employee_affairs.link_leaves_hint',
    icon: 'tabler-beach',
    to: '/employee_leaves/list',
    show: canUsers.value,
  },
].filter(link => link.show))
</script>

<template>
  <div class="hr-dashboard">
    <PageHeaderActions v-if="canUsers">
      <VBtn
        class="general-page-header__btn-primary"
        prepend-icon="tabler-users"
        @click="go('/user/list')"
      >
        {{ $t('Career staff') }}
      </VBtn>
    </PageHeaderActions>

    <section
      class="hr-dashboard__shell"
      :aria-label="$t('employee_affairs.dashboard')"
    >
      <div
        v-if="loading"
        class="hr-dashboard__loader"
      >
        <VProgressCircular
          :size="48"
          color="primary"
          indeterminate
        />
      </div>

      <template v-else-if="statistics">
        <div
          v-if="quickLinks.length"
          class="hr-dashboard__section"
        >
          <h2 class="hr-dashboard__section-title">
            {{ $t('employee_affairs.quick_links') }}
          </h2>
          <div class="hr-dashboard-links">
            <button
              v-for="link in quickLinks"
              :key="link.key"
              type="button"
              class="hr-dashboard-link"
              @click="go(link.to)"
            >
              <span class="hr-dashboard-link__icon">
                <VIcon
                  :icon="link.icon"
                  size="20"
                />
              </span>
              <span class="hr-dashboard-link__text">
                <span class="hr-dashboard-link__title">{{ $t(link.titleKey) }}</span>
                <span class="hr-dashboard-link__hint">{{ $t(link.hintKey) }}</span>
              </span>
              <VSpacer />
              <VIcon
                icon="tabler-chevron-left"
                size="18"
                class="text-medium-emphasis"
              />
            </button>
          </div>
        </div>

        <div
          v-if="kpiCards.length"
          class="hr-dashboard__section"
        >
          <h2 class="hr-dashboard__section-title">
            {{ $t('employee_affairs.kpi_section') }}
          </h2>
          <div class="hr-dashboard-kpi-grid">
            <button
              v-for="card in kpiCards"
              :key="card.key"
              type="button"
              class="hr-dashboard-kpi"
              @click="go(card.to)"
            >
              <div class="hr-dashboard-kpi__head">
                <span
                  class="hr-dashboard-kpi__icon"
                  :class="`hr-dashboard-kpi__icon--${card.tone}`"
                >
                  <VIcon
                    :icon="card.icon"
                    size="20"
                  />
                </span>
                <p class="hr-dashboard-kpi__label">
                  {{ $t(card.labelKey) }}
                </p>
              </div>
              <p class="hr-dashboard-kpi__value">
                {{ card.value ?? 0 }}
              </p>
            </button>
          </div>
        </div>

        <div
          v-if="showAttendance"
          class="hr-dashboard__section"
        >
          <h2 class="hr-dashboard__section-title">
            {{ $t('employee_affairs.attendance_section') }}
          </h2>
          <div
            class="hr-dashboard-attendance"
            role="button"
            tabindex="0"
            @click="go('/employees_attendance/list')"
            @keydown.enter="go('/employees_attendance/list')"
          >
            <div class="hr-dashboard-attendance__head">
              <span class="hr-dashboard-link__icon">
                <VIcon
                  icon="tabler-calendar-check"
                  size="20"
                />
              </span>
              <div>
                <h3 class="hr-dashboard-attendance__title">
                  {{ $t('employee_affairs.attendance_today') }}
                </h3>
                <p class="hr-dashboard-attendance__date">
                  {{ statistics.attendance_today?.date }}
                </p>
              </div>
            </div>
            <div class="hr-dashboard-attendance__stats">
              <div class="hr-dashboard-attendance__stat">
                <div class="hr-dashboard-attendance__stat-label hr-dashboard-attendance__stat-label--success">
                  {{ $t('employee_affairs.present') }}
                </div>
                <div class="hr-dashboard-attendance__stat-value">
                  {{ statistics.attendance_today?.present ?? 0 }}
                </div>
              </div>
              <div class="hr-dashboard-attendance__stat">
                <div class="hr-dashboard-attendance__stat-label hr-dashboard-attendance__stat-label--error">
                  {{ $t('employee_affairs.absent') }}
                </div>
                <div class="hr-dashboard-attendance__stat-value">
                  {{ statistics.attendance_today?.absent ?? 0 }}
                </div>
              </div>
              <div class="hr-dashboard-attendance__stat">
                <div class="hr-dashboard-attendance__stat-label hr-dashboard-attendance__stat-label--muted">
                  {{ $t('employee_affairs.unmarked') }}
                </div>
                <div class="hr-dashboard-attendance__stat-value">
                  {{ statistics.attendance_today?.unmarked ?? 0 }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </section>
  </div>
</template>

<route lang="yaml">
meta:
  action: access_users
  subject: access_users
  pageTitleKey: employee_affairs.dashboard
  pageSubtitleKey: employee_affairs.dashboard_subtitle
</route>

<style lang="scss">
@import '@/styles/employee-affairs-dashboard.scss';
</style>
