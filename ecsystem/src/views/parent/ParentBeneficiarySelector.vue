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
  /** When set, emits updates instead of only updating shared context */
  modelValue: {
    type: [Number, String, null],
    default: undefined,
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

const {
  selectedBeneficiaryId,
  selectBeneficiary,
  syncBeneficiaryId,
  linkedBeneficiaries,
  linkedBeneficiariesLoading,
  linkedBeneficiariesError,
} = useParentBeneficiary()

const activeId = computed(() => {
  if (props.modelValue !== undefined)
    return props.modelValue != null && props.modelValue !== '' ? Number(props.modelValue) : null

  return selectedBeneficiaryId.value
})

const load = async () => {
  await fetchLinkedBeneficiaries()
  await resolveBeneficiarySelection()
}

onMounted(load)

const sessionStore = useSessionStore()

watch(() => `${sessionStore.userData?.id ?? ''}:${sessionStore.center ?? ''}`, load)

const onPick = item => {
  selectBeneficiary(item)
  emit('update:modelValue', item.id)
  emit('change', item)
}

const onChipKeydown = (event, item) => {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    onPick(item)
  }
}

watch(() => props.modelValue, value => {
  if (props.modelValue === undefined)
    return

  syncBeneficiaryId(value)
})
</script>

<template>
  <section
    v-if="isParentUser() && (linkedBeneficiariesLoading || linkedBeneficiaries.length > 1)"
    class="parent-beneficiary-selector"
    :aria-label="$t('dashboard.parent_beneficiary_selector_aria')"
  >
    <p class="parent-beneficiary-selector__label">
      {{ $t('dashboard.parent_beneficiary_switch_label') }}
    </p>

    <div
      v-if="linkedBeneficiariesLoading"
      class="parent-page-skeleton"
    >
      <div
        class="parent-page-skeleton__bar"
        style="block-size: 2.25rem; inline-size: 60%;"
      />
    </div>

    <p
      v-else-if="linkedBeneficiariesError"
      class="text-medium-emphasis text-body-2 mb-0"
    >
      {{ $t('dashboard.parent_beneficiaries_error') }}
    </p>

    <div
      v-else
      class="parent-beneficiary-selector__track"
    >
      <button
        v-for="item in linkedBeneficiaries"
        :key="item.id"
        type="button"
        class="parent-beneficiary-chip"
        :class="{ 'parent-beneficiary-chip--active': activeId === item.id }"
        @click="onPick(item)"
        @keydown="onChipKeydown($event, item)"
      >
        <VAvatar
          size="28"
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
        <span class="parent-beneficiary-chip__name">{{ item.name }}</span>
      </button>
    </div>
  </section>
</template>

<style lang="scss" scoped>
@import '@styles/parent-experience.scss';
</style>
