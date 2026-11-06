<script setup>
import ParentGoalDetails from '@/views/parent/ParentGoalDetails.vue'
import { useParentBeneficiary } from '@/composables/useParentBeneficiary'
import { openParentTreatmentPlans } from '@/composables/useParentEducationalProgramNav'
import { isParentUser } from '@core/utils/staffSessionVisibility'
import { parentTreatmentListLocation } from '@/utils/navigation/backNavigation'

const route = useRoute()
const router = useRouter()
const isParent = isParentUser()

if (!isParent)
  router.replace({ name: 'services-list' })

const backTo = computed(() => parentTreatmentListLocation(route))

const { selectedBeneficiaryId } = useParentBeneficiary()

watch(selectedBeneficiaryId, id => {
  if (!isParent || id == null || Number(id) === Number(route.params.case))
    return

  openParentTreatmentPlans(router, id)
})
</script>

<template>
  <section v-if="isParent">
    <ParentGoalDetails
      :goal-id="route.params.goal"
      :case-id="route.params.case"
      section="treatment"
      :back-to="backTo"
    />
  </section>
</template>

<route lang="yaml">
  meta:
    action: access_treatment-goals
    subject: access_treatment-goals
</route>
