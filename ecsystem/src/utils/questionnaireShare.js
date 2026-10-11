export function questionnaireRespondPath(taskId) {
  return `/questionnaires/questions/${taskId}`
}

export function questionnaireRespondUrl(taskId) {
  if (typeof window === 'undefined')
    return questionnaireRespondPath(taskId)

  return `${window.location.origin}${questionnaireRespondPath(taskId)}`
}

export async function copyQuestionnaireRespondLink(taskId) {
  const url = questionnaireRespondUrl(taskId)
  await navigator.clipboard.writeText(url)

  return url
}
