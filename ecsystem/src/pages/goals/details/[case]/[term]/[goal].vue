<script setup>
import ParentGoalDetails from '@/views/parent/ParentGoalDetails.vue'
import { useParentBeneficiary } from '@/composables/useParentBeneficiary'
import { openParentIndividualPlan } from '@/composables/useParentEducationalProgramNav'
import { isParentUser } from '@core/utils/staffSessionVisibility'

const route = useRoute()
const router = useRouter()
const isParent = isParentUser()

if (!isParent) {
  router.replace({
    name: 'goals-sessions-case-term-goal',
    params: { case: route.params.case, term: route.params.term, goal: route.params.goal },
  })
}

const backTo = computed(() => ({
  name: 'goals-list-case-term',
  params: { case: route.params.case, term: route.params.term },
}))

const { selectedBeneficiaryId } = useParentBeneficiary()

watch(selectedBeneficiaryId, id => {
  if (!isParent || id == null || Number(id) === Number(route.params.case))
    return

  openParentIndividualPlan(router, id)
})
</script>

<template>
  <section v-if="isParent">
    <ParentGoalDetails
      :goal-id="route.params.goal"
      :case-id="route.params.case"
      section="program"
      :back-to="backTo"
    />
  </section>
</template>

<route lang="yaml">
  meta:
    action: access_education-goals
    subject: access_education-goals
</route>
