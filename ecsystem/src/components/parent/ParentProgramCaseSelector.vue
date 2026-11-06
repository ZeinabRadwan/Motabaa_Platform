<script setup>
import ParentBeneficiaryPicker from '@/views/parent/ParentBeneficiaryPicker.vue'
import { useParentBeneficiary } from '@/composables/useParentBeneficiary'

const props = defineProps({
  /** When true, parents must pick a linked beneficiary before the main content shows. */
  gateParent: {
    type: Boolean,
    default: false,
  },
  titleI18nKey: {
    type: String,
    default: 'dashboard.parent_beneficiaries_title',
  },
  hintI18nKey: {
    type: String,
    default: 'dashboard.parent_beneficiaries_hint',
  },
})

const caseId = defineModel({
  type: [Number, String],
  default: null,
})

const { selectedBeneficiaryId, selectBeneficiary } = useParentBeneficiary()

const syncCaseFromSharedContext = () => {
  if (selectedBeneficiaryId.value != null && (caseId.value == null || caseId.value === ''))
    caseId.value = selectedBeneficiaryId.value
}

onMounted(syncCaseFromSharedContext)

watch(selectedBeneficiaryId, () => {
  syncCaseFromSharedContext()
})

const onSelect = item => {
  selectBeneficiary(item)
  caseId.value = item.id
}

const clearCase = () => {
  caseId.value = null
}

const showBeneficiaryPicker = computed(() => props.gateParent && (caseId.value == null || caseId.value === ''))
</script>

<template>
  <ParentBeneficiaryPicker
    v-if="showBeneficiaryPicker"
    :title-i18n-key="titleI18nKey"
    :hint-i18n-key="hintI18nKey"
    :selected-id="selectedBeneficiaryId"
    @select="onSelect"
  />
  <slot
    v-else
    :clear-case="gateParent ? clearCase : undefined"
  />
</template>
