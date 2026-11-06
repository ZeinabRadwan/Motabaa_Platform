import { fetchParentCaseTermId } from '@/composables/useParentEducationalProgramNav'

export async function openParentIndependentSkills(router, caseId) {
  const { termId } = await fetchParentCaseTermId(caseId)
  if (!termId)
    return { ok: false, reason: 'missing_term' }

  await router.push({
    name: 'independent_goals-list-case-term',
    params: { case: Number(caseId), term: termId },
  })

  return { ok: true }
}
