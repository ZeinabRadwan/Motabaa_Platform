<script setup>
import { useSessionStore } from '@/stores/useSessionStore'
import ParentBeneficiarySelector from '@/views/parent/ParentBeneficiarySelector.vue'
import { casePictureUrl } from '@/utils/casePictureUrl'
import { avatarText } from '@core/utils/formatters'
import {
  resolveBeneficiarySelection,
  useParentBeneficiary,
} from '@/composables/useParentBeneficiary'
import {
  isParentCaseProfileRoute,
  isParentUser,
  parentCaseProfileLocation,
} from '@core/utils/staffSessionVisibility'

const route = useRoute()
const router = useRouter()

const {
  selectedBeneficiary,
  linkedBeneficiaries,
  linkedBeneficiariesLoading,
  linkedBeneficiariesError,
} = useParentBeneficiary()

const ready = ref(false)
const invalidCleared = ref(false)

const syncFromRoute = async () => {
  if (!isParentUser()) {
    ready.value = true

    return
  }

  const routeCaseId = route.params.case
    ?? route.query.case
    ?? (isParentCaseProfileRoute(route.name) ? route.params.id : null)
    ?? null

  const result = await resolveBeneficiarySelection({ routeCaseId })

  invalidCleared.value = result.clearedInvalid
  ready.value = true
}

onMounted(syncFromRoute)

const sessionStore = useSessionStore()

watch(
  () => [
    route.params.case,
    route.query.case,
    route.params.id,
    sessionStore.userData?.id,
    sessionStore.center,
  ],
  () => {
    syncFromRoute()
  },
)

const onSwitchChild = item => {
  if (!item?.id || !isParentCaseProfileRoute(route.name))
    return

  if (String(route.params.id) === String(item.id))
    return

  router.push(parentCaseProfileLocation(item.id, route.params.tab || 'info'))
}

const activeChild = computed(() => selectedBeneficiary.value)
const beneficiaryCount = computed(() => linkedBeneficiaries.value.length)
const hideOnDashboard = computed(() => route.name === 'dashboards-parent')
</script>

<template>
  <section
    v-if="isParentUser() && ready && !hideOnDashboard"
    class="parent-layout-beneficiary"
    :aria-label="$t('dashboard.parent_beneficiary_context_aria')"
  >
    <div
      v-if="linkedBeneficiariesLoading && !activeChild"
      class="parent-layout-beneficiary__loading"
    >
      <VProgressLinear
        indeterminate
        color="primary"
        height="3"
        rounded
      />
    </div>

    <p
      v-else-if="linkedBeneficiariesError"
      class="parent-layout-beneficiary__message text-medium-emphasis"
    >
      {{ $t('dashboard.parent_beneficiaries_error') }}
    </p>

    <p
      v-else-if="invalidCleared"
      class="parent-layout-beneficiary__message text-medium-emphasis"
    >
      {{ $t('dashboard.parent_beneficiary_invalid_cleared') }}
    </p>

    <p
      v-else-if="beneficiaryCount === 0"
      class="parent-layout-beneficiary__message text-medium-emphasis"
    >
      {{ $t('dashboard.parent_beneficiaries_empty') }}
    </p>

    <template v-else-if="activeChild">
      <div class="parent-layout-beneficiary__active">
        <span class="parent-layout-beneficiary__label">
          {{ $t('dashboard.parent_active_beneficiary_label') }}
        </span>
        <VAvatar
          size="32"
          class="parent-layout-beneficiary__avatar"
          :variant="casePictureUrl(activeChild.picture) ? undefined : 'tonal'"
          color="primary"
        >
          <VImg
            v-if="casePictureUrl(activeChild.picture)"
            :src="casePictureUrl(activeChild.picture)"
            :alt="activeChild.name"
            cover
          />
          <span v-else>{{ avatarText(activeChild.name) }}</span>
        </VAvatar>
        <span class="parent-layout-beneficiary__name">{{ activeChild.name }}</span>
      </div>

      <ParentBeneficiarySelector
        v-if="beneficiaryCount > 1"
        class="parent-layout-beneficiary__switcher"
        @change="onSwitchChild"
      />
    </template>

    <ParentBeneficiarySelector
      v-else-if="beneficiaryCount > 1"
      class="parent-layout-beneficiary__switcher-only"
      @change="onSwitchChild"
    />
  </section>
</template>

<style lang="scss" scoped>
@import '@styles/parent-experience.scss';
</style>
