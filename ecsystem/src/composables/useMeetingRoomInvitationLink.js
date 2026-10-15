import { ref } from 'vue'
import i18n from '@/plugins/i18n/index.js'
import { translateApiValidationMessage } from '@core/utils/termOverlap'

export function useMeetingRoomInvitationLink({
  fetchInvitationLink,
  snackbarRef,
  getRoomId,
  getCachedUrl,
  setCachedUrl,
}) {
  const invitationLinkLoading = ref(false)

  const translateError = message => translateApiValidationMessage(
    message,
    key => i18n.global.t(key),
    i18n.global.locale.value,
  )

  const resolveInvitationUrl = async (rotate = false) => {
    const roomId = getRoomId()
    if (roomId > 0) {
      const response = await fetchInvitationLink(roomId, rotate)

      return response.data?.data?.invitation_url ?? null
    }

    return getCachedUrl?.() ?? null
  }

  const copyToClipboard = async url => {
    if (! url)
      return false

    if (! navigator?.clipboard?.writeText)
      throw new Error('clipboard_unavailable')

    await navigator.clipboard.writeText(url)

    return true
  }

  const showClipboardError = () => {
    snackbarRef.value?.exposevisibleSnackbar(
      i18n.global.t('rooms.invitation_clipboard_failed'),
      'error',
    )
  }

  const copyInvitationLink = async () => {
    const roomId = getRoomId()
    if (roomId <= 0 && ! getCachedUrl?.())
      return

    invitationLinkLoading.value = true
    try {
      const url = await resolveInvitationUrl(false)
      if (! url)
        return

      await copyToClipboard(url)
      setCachedUrl?.(url)
      snackbarRef.value?.exposevisibleSnackbar(
        i18n.global.t('rooms.invitation_link_copied'),
        'success',
      )
    }
    catch (error) {
      if (error?.message === 'clipboard_unavailable' || error?.name === 'NotAllowedError') {
        showClipboardError()

        return
      }

      const msg = error.response?.data?.errors?.error?.[0]
      if (msg)
        snackbarRef.value?.exposevisibleSnackbar(translateError(msg), 'error')
    }
    finally {
      invitationLinkLoading.value = false
    }
  }

  const regenerateInvitationLink = async () => {
    const roomId = getRoomId()
    if (roomId <= 0)
      return

    invitationLinkLoading.value = true
    try {
      const url = await resolveInvitationUrl(true)
      if (! url)
        return

      await copyToClipboard(url)
      setCachedUrl?.(url)
      snackbarRef.value?.exposevisibleSnackbar(
        i18n.global.t('rooms.invitation_link_copied'),
        'success',
      )
    }
    catch (error) {
      if (error?.message === 'clipboard_unavailable' || error?.name === 'NotAllowedError') {
        showClipboardError()

        return
      }

      const msg = error.response?.data?.errors?.error?.[0]
      if (msg)
        snackbarRef.value?.exposevisibleSnackbar(translateError(msg), 'error')
    }
    finally {
      invitationLinkLoading.value = false
    }
  }

  return {
    invitationLinkLoading,
    copyInvitationLink,
    regenerateInvitationLink,
  }
}
