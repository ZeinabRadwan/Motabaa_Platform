<script setup>
import quickAction1 from '@images/QUICK_ACTION_1.jpg'
import quickAction2 from '@images/QUICK_ACTION_2.jpg'
import quickAction3 from '@images/QUICK_ACTION_3.jpg'

const router = useRouter()
const { t } = useI18n()

const actions = [
  {
    id: 'register',
    route: '/cases/add',
    titleKey: 'Register Case',
    descKey: 'dashboard.quick_action_register_desc',
    image: quickAction1,
  },
  {
    id: 'list',
    route: '/cases/list',
    titleKey: 'List Cases',
    descKey: 'dashboard.quick_action_list_desc',
    image: quickAction2,
  },
  {
    id: 'assessment',
    route: '/cases/assessments',
    titleKey: 'Comprehensive Assessment',
    descKey: 'dashboard.quick_action_assessment_desc',
    image: quickAction3,
  },
]

function navigate(route) {
  router.push(route)
}

function onKeydown(event, route) {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    navigate(route)
  }
}
</script>

<template>
  <section
    class="dashboard-quick-actions"
    :aria-label="t('dashboard.quick_actions_section')"
  >
    <article
      v-for="action in actions"
      :key="action.id"
      class="dashboard-quick-action"
      role="button"
      tabindex="0"
      @click="navigate(action.route)"
      @keydown="onKeydown($event, action.route)"
    >
      <div
        class="dashboard-quick-action__icon-wrap"
        aria-hidden="true"
      >
        <img
          :src="action.image"
          alt=""
          class="dashboard-quick-action__image"
          loading="lazy"
          decoding="async"
        >
      </div>

      <div class="dashboard-quick-action__body">
        <h3 class="dashboard-quick-action__title">
          {{ t(action.titleKey) }}
        </h3>
        <p class="dashboard-quick-action__desc">
          {{ t(action.descKey) }}
        </p>
      </div>

      <span
        class="dashboard-quick-action__cta"
        aria-hidden="true"
      >
        <VIcon
          icon="tabler-arrow-left"
          size="18"
        />
      </span>
    </article>
  </section>
</template>

<style lang="scss">
@import '@/styles/dashboard-quick-actions.scss';
</style>
