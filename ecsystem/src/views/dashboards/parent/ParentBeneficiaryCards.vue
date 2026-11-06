<script setup>
import ParentBeneficiaryPicker from '@/views/parent/ParentBeneficiaryPicker.vue'
import { useParentBeneficiary } from '@/composables/useParentBeneficiary'
import { parentCaseProfileLocation } from '@core/utils/staffSessionVisibility'

const router = useRouter()
const { selectedBeneficiaryId, selectBeneficiary } = useParentBeneficiary()

const onSelect = item => {
  selectBeneficiary(item)
}

const onOpen = item => {
  if (!item?.id)
    return

  selectBeneficiary(item)
  router.push(parentCaseProfileLocation(item.id))
}
</script>

<template>
  <ParentBeneficiaryPicker
    :selected-id="selectedBeneficiaryId"
    card-aria-i18n-key="dashboard.parent_beneficiary_open_profile_aria"
    @select="onSelect"
    @open="onOpen"
  />
</template>
