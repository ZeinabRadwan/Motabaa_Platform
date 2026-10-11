import i18n from '@/plugins/i18n/index.js'

/**
 * Shared handler for MessageSend @send-meesage across session/meeting pages.
 * callback(true) only after the backend confirms success; callback(false) on failure.
 */
export function useMessageSendHandler({
  putFn,
  applyPayload,
  fetchMessages,
  snackbarRef,
  errorsMessage,
  uploadPercentage,
}) {
  return (data, callback) => {
    if (typeof applyPayload === 'function') {
      applyPayload(data)
    }

    putFn(data, progress => {
      uploadPercentage.value = progress
    }).then(response => {
      uploadPercentage.value = 0

      if (response?.data?.status !== 'success') {
        errorsMessage.value = {
          content: [i18n.global.t('File upload failed.')],
        }
        callback(false)

        return
      }

      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success')
      fetchMessages()
      callback(true)
    }).catch(error => {
      uploadPercentage.value = 0
      errorsMessage.value = error.response?.data?.errors || {}

      if (!errorsMessage.value || Object.keys(errorsMessage.value).length === 0) {
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('File upload failed.'), 'error')
      }

      callback(false)
    })
  }
}
