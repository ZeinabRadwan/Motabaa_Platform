import { casesApi } from '@/plugins/apis/casesReqest'
import { parentCanAccessRehabAssessment } from '@core/utils/staffSessionVisibility'

/**
 * Resolve the term used for parent educational plan / assessment views.
 */
export function resolveParentCaseTermId(caseRecord) {
  const current = caseRecord?.current_term?.id
  if (current != null && current !== '')
    return Number(current)

  const fromTerms = caseRecord?.terms?.[0]?.id
  if (fromTerms != null && fromTerms !== '')
    return Number(fromTerms)

  return null
}

export async function fetchParentCaseTermId(caseId) {
  const casesReqest = casesApi()
  const response = await casesReqest.fetchCase(caseId)
  const caseRecord = response.data?.data

  return {
    caseRecord,
    termId: resolveParentCaseTermId(caseRecord),
  }
}

export async function openParentIndividualPlan(router, caseId) {
  const { termId } = await fetchParentCaseTermId(caseId)
  if (!termId)
    return { ok: false, reason: 'missing_term' }

  await router.push({
    name: 'goals-list-case-term',
    params: { case: Number(caseId), term: termId },
  })

  return { ok: true }
}

export async function openParentEducationalAssessment(router, caseId) {
  if (!parentCanAccessRehabAssessment()) {
    return { ok: false, reason: 'assessment_disabled' }
  }

  await router.push({
    name: 'goals-assessments',
    query: { case: String(caseId) },
  })

  return { ok: true }
}

export async function openParentTreatmentPlans(router, caseId) {
  const { termId } = await fetchParentCaseTermId(caseId)
  if (!termId)
    return { ok: false, reason: 'missing_term' }

  await router.push({
    name: 'services-list',
    query: { case: String(caseId), term: String(termId) },
  })

  return { ok: true }
}

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
