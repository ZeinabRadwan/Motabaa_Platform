<script setup>
import {
  fetchLinkedBeneficiaries,
  resolveBeneficiarySelection,
  useParentBeneficiary,
} from '@/composables/useParentBeneficiary'
import { useSessionStore } from '@/stores/useSessionStore'
import { casePictureUrl } from '@/utils/casePictureUrl'
import { avatarText } from '@core/utils/formatters'
import { isParentUser } from '@core/utils/staffSessionVisibility'

const props = defineProps({
  selectedId: {
    type: [Number, String, null],
    default: null,
  },
  titleI18nKey: {
    type: String,
    default: 'dashboard.parent_beneficiaries_title',
  },
  hintI18nKey: {
    type: String,
    default: 'dashboard.parent_beneficiaries_hint',
  },
  ariaI18nKey: {
    type: String,
    default: 'dashboard.parent_beneficiaries_aria',
  },
  emptyI18nKey: {
    type: String,
    default: 'dashboard.parent_beneficiaries_empty',
  },
  errorI18nKey: {
    type: String,
    default: 'dashboard.parent_beneficiaries_error',
  },
  /** When true, auto-select the only linked child (default on). */
  autoSelectSingle: {
    type: Boolean,
    default: true,
  },

  /** Optional per-card aria-label key; receives `{ name }`. */
  cardAriaI18nKey: {
    type: String,
    default: '',
  },
})

/** `select`: selection changed (also on auto-select). `open`: the user activated a card. */
const emit = defineEmits(['select', 'open'])

const {
  linkedBeneficiaries,
  linkedBeneficiariesLoading,
  linkedBeneficiariesError,
  linkedBeneficiariesLoaded,
  selectedBeneficiaryId,
} = useParentBeneficiary()

const loadBeneficiaries = async () => {
  await fetchLinkedBeneficiaries()
  if (props.autoSelectSingle) {
    const { selected } = await resolveBeneficiarySelection()
    if (selected)
      emit('select', selected)
  }
}

onMounted(loadBeneficiaries)

const sessionStore = useSessionStore()

watch(() => `${sessionStore.userData?.id ?? ''}:${sessionStore.center ?? ''}`, loadBeneficiaries)

const beneficiaries = computed(() => linkedBeneficiaries.value)

const loading = computed(() => linkedBeneficiariesLoading.value
  || (isParentUser() && !linkedBeneficiariesLoaded.value && !linkedBeneficiariesError.value))

const loadError = computed(() => linkedBeneficiariesError.value)

const effectiveSelectedId = computed(() => {
  if (props.selectedId != null && props.selectedId !== '')
    return props.selectedId

  return selectedBeneficiaryId.value
})

const onSelect = item => {
  emit('select', item)
  emit('open', item)
}

const onCardKeydown = (event, item) => {
  if (event.repeat)
    return

  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    onSelect(item)
  }
}
</script>

<template>
  <section
    class="parent-dashboard-beneficiaries"
    :aria-label="$t(ariaI18nKey)"
  >
    <header class="parent-dashboard-beneficiaries__header">
      <h2 class="parent-dashboard-beneficiaries__title">
        {{ $t(titleI18nKey) }}
      </h2>
      <p class="parent-dashboard-beneficiaries__hint">
        {{ $t(hintI18nKey) }}
      </p>
    </header>

    <div
      v-if="loading"
      class="parent-dashboard-beneficiaries__loading"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="32"
      />
    </div>

    <p
      v-else-if="loadError"
      class="parent-dashboard-beneficiaries__empty text-medium-emphasis"
    >
      {{ $t(errorI18nKey) }}
    </p>

    <p
      v-else-if="!beneficiaries.length"
      class="parent-dashboard-beneficiaries__empty text-medium-emphasis"
    >
      {{ $t(emptyI18nKey) }}
    </p>

    <div
      v-else
      class="parent-dashboard-beneficiaries__grid"
    >
      <article
        v-for="item in beneficiaries"
        :key="item.id"
        class="parent-beneficiary-card"
        :class="{ 'parent-beneficiary-card--selected': effectiveSelectedId != null && Number(effectiveSelectedId) === Number(item.id) }"
        role="button"
        tabindex="0"
        :aria-label="cardAriaI18nKey ? $t(cardAriaI18nKey, { name: item.name }) : undefined"
        @click="onSelect(item)"
        @keydown="onCardKeydown($event, item)"
      >
        <VAvatar
          size="56"
          class="parent-beneficiary-card__avatar"
          aria-hidden="true"
          :variant="casePictureUrl(item.picture) ? undefined : 'tonal'"
          color="primary"
        >
          <VImg
            v-if="casePictureUrl(item.picture)"
            :src="casePictureUrl(item.picture)"
            :alt="item.name"
            cover
          />
          <span v-else>{{ avatarText(item.name) }}</span>
        </VAvatar>
        <div class="parent-beneficiary-card__body">
          <h3 class="parent-beneficiary-card__name">
            {{ item.name }}
          </h3>
          <p
            v-if="item.disability_type_ids?.length"
            class="parent-beneficiary-card__meta"
          >
            {{ item.disability_type_ids.map(d => d.name).join(' · ') }}
          </p>
        </div>
        <VIcon
          icon="tabler-chevron-right"
          class="parent-beneficiary-card__chevron"
          size="22"
          aria-hidden="true"
        />
      </article>
    </div>
  </section>
</template>

<style lang="scss" scoped>
@import '@styles/parent-dashboard.scss';
</style>
