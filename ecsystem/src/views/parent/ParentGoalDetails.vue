<script setup>
import moment from '@/plugins/moment'
import { goalsApi } from '@/plugins/apis/goalsReqest'
import ParentPageShell from '@/layouts/components/ParentPageShell.vue'
import { DEFAULT_CHUNK_BYTES, createUploadId, isRetryableUploadError, uploadInChunks } from '@/utils/chunkedUpload'
import { acceptAttribute, fileExtension, uploadFileName } from '@/utils/uploadFileTypes'

const props = defineProps({
  goalId: {
    type: [Number, String],
    required: true,
  },
  caseId: {
    type: [Number, String],
    required: true,
  },

  /** 'program' (individual plan) or 'treatment' (support services) */
  section: {
    type: String,
    required: true,
  },
  backTo: {
    type: Object,
    required: true,
  },
})

const { t, locale } = useI18n()
const goalsReqest = goalsApi()

const goal = ref(null)
const mediaItems = ref([])
const mediaPage = ref({ current: 1, last: 1, total: 0 })
const loading = ref(true)
const loadingMore = ref(false)
const loadError = ref(null)
const uploadRules = ref(null)
const uploadChunkBytes = ref(DEFAULT_CHUNK_BYTES)

const imageDialog = ref({ open: false, item: null })
const documentDialog = ref({ open: false, document: null })
const failedImages = ref(new Set())
const failedVideos = ref(new Set())

const isRtl = computed(() => String(locale.value || '').startsWith('ar'))

const formatDate = value => {
  if (!value)
    return ''

  return moment(value).locale(locale.value).format('D MMMM YYYY')
}

const applyDetails = (data, page) => {
  goal.value = data.goal ?? null
  if (data.upload_rules)
    uploadRules.value = data.upload_rules
  if (Number(data.upload_chunk_bytes) > 0)
    uploadChunkBytes.value = Number(data.upload_chunk_bytes)
  mediaItems.value = page === 1 ? (data.media ?? []) : [...mediaItems.value, ...(data.media ?? [])]
  mediaPage.value = data.media_page ?? { current: page, last: page, total: mediaItems.value.length }
}

const fetchDetails = (page = 1) => {
  if (page === 1) {
    loading.value = true
    loadError.value = null
  }
  else {
    loadingMore.value = true
  }

  return goalsReqest.fetchParentDetails(props.goalId, {
    case_id: props.caseId,
    section: props.section,
    page,
  }).then(response => {
    applyDetails(response.data?.data ?? {}, page)
  }).catch(error => {
    if (page === 1) {
      goal.value = null
      mediaItems.value = []

      const status = error.response?.status

      loadError.value = (status === 403 || status === 404) ? 'denied' : 'failed'
    }
  }).finally(() => {
    loading.value = false
    loadingMore.value = false
  })
}

watch(
  () => [String(props.goalId), String(props.caseId), props.section],
  () => {
    failedImages.value = new Set()
    failedVideos.value = new Set()
    fetchDetails(1)
  },
  { immediate: true },
)

const images = computed(() => mediaItems.value.filter(item => item.image))
const videos = computed(() => mediaItems.value.filter(item => item.video))
const documents = computed(() => mediaItems.value.filter(item => item.document))
const updates = computed(() => mediaItems.value.filter(item => !item.image && !item.video && !item.document))
const hasMoreMedia = computed(() => mediaPage.value.current < mediaPage.value.last)

const subtitleKey = computed(() => (
  props.section === 'treatment'
    ? 'parent_goal_details.treatment_subtitle'
    : 'parent_goal_details.program_subtitle'
))

const periodLabel = computed(() => {
  if (!goal.value?.date_from)
    return ''

  return t('goals.periodTo', {
    from: String(goal.value.date_from).split('-').reverse().join('-'),
    to: goal.value.date_to ? String(goal.value.date_to) : '',
  })
})

const infoRows = computed(() => {
  if (!goal.value)
    return []

  return [
    { key: 'service', label: t('parent_goal_details.service'), value: goal.value.service_label },
    { key: 'domain', label: t('parent_goal_details.domain'), value: goal.value.domain },
    { key: 'general_goal', label: t('parent_goal_details.general_goal'), value: goal.value.general_goal },
    { key: 'period', label: t('parent_goal_details.period'), value: periodLabel.value },
  ].filter(row => row.value)
})

const sessionLines = computed(() => {
  const sessions = goal.value?.sessions
  if (!sessions || !sessions.count)
    return []

  const lines = [t('parent_goal_details.sessions_count', { count: sessions.count })]
  if (sessions.last_started_at)
    lines.push(t('parent_goal_details.last_session', { date: formatDate(sessions.last_started_at) }))

  return lines
})

const itemLabel = item => {
  if (item.is_own)
    return t('parent_goal_details.shared_by_you')

  return item.is_session_activity
    ? t('parent_goal_details.session_activity')
    : t('parent_goal_details.comment')
}

const documentIcon = kind => ({
  pdf: 'tabler-pdf',
  image: 'tabler-photo',
  video: 'tabler-video',
  audio: 'tabler-music',
  office: 'tabler-file-text',
}[kind] ?? 'tabler-file')

const openImage = item => {
  imageDialog.value = { open: true, item }
}

const previewDocument = document => {
  if (document.kind === 'image') {
    imageDialog.value = { open: true, item: { image: document, content: null, created_at: null } }

    return
  }
  documentDialog.value = { open: true, document }
}

const markImageFailed = id => {
  failedImages.value = new Set([...failedImages.value, id])
}

const markVideoFailed = id => {
  failedVideos.value = new Set([...failedVideos.value, id])
}

// Mirrors ParentGoalMessageRequest; the API's upload_rules take precedence when present.
const DEFAULT_UPLOAD_RULES = {
  image: { max_kb: 204800, extensions: ['jpg', 'jpeg', 'png'] },
  video: { max_kb: 204800, extensions: ['mp4', 'mov', 'ogg', 'ogv', 'webm', 'm4v', '3gp', '3g2'] },
  file: {
    max_kb: 204800,
    extensions: [
      'pdf',
      'doc',
      'docx',
      'xls',
      'xlsx',
      'ppt',
      'pptx',
      'odt',
      'ods',
      'odp',
      'rtf',
      'txt',
      'csv',
      'jpg',
      'jpeg',
      'png',
      'gif',
      'webp',
      'mp4',
      'mov',
      'webm',
      'm4v',
      '3gp',
      '3g2',
      'mp3',
      'wav',
      'm4a',
      'aac',
      'oga',
    ],
  },
}

const UPLOAD_TYPE_ICONS = { image: 'tabler-photo', video: 'tabler-video', file: 'tabler-file-text' }

const uploadTypes = computed(() => Object.keys(UPLOAD_TYPE_ICONS).map(value => {
  const rule = uploadRules.value?.[value] ?? DEFAULT_UPLOAD_RULES[value]
  const extensions = (rule.extensions ?? []).map(ext => String(ext).toLowerCase())

  return {
    value,
    icon: UPLOAD_TYPE_ICONS[value],
    extensions,
    accept: acceptAttribute(extensions),
    maxBytes: Number(rule.max_kb) * 1024,
    maxMb: Math.floor(Number(rule.max_kb) / 1024),
  }
}))

const upload = ref({
  type: 'image',
  files: [],
  content: '',
  progress: 0,
  sending: false,
  waiting: '',
  error: '',
  success: false,
})

let uploadController = null

// Chunked upload that stopped part-way; picking the same file again continues it.
const resumableUpload = shallowRef(null)

const uploadType = computed(() => uploadTypes.value.find(type => type.value === upload.value.type) ?? uploadTypes.value[0])

const uploadFile = computed(() => {
  const files = upload.value.files

  return Array.isArray(files) ? (files[0] ?? null) : (files ?? null)
})

const resumeKey = (file, field) => [props.goalId, props.caseId, props.section, field, file.name, file.size, file.lastModified].join('|')

const canResumeUpload = computed(() => (
  !!uploadFile.value
  && resumableUpload.value?.key === resumeKey(uploadFile.value, upload.value.type)
  && resumableUpload.value.nextIndex > 0
))

const validateUploadFile = file => {
  if (!file)
    return t('parent_goal_details.upload_choose_file')
  if (!file.size)
    return t('parent_goal_details.upload_empty_file')
  if (!uploadType.value.extensions.includes(fileExtension(uploadFileName(file, uploadType.value.extensions))))
    return t('parent_goal_details.upload_unsupported_type', { types: uploadType.value.extensions.join(', ').toUpperCase() })
  if (file.size > uploadType.value.maxBytes)
    return t('parent_goal_details.upload_too_large', { mb: uploadType.value.maxMb })

  return ''
}

const uploadFileInvalid = computed(() => !!uploadFile.value && !!validateUploadFile(uploadFile.value))

const UPLOAD_STATUS_MESSAGES = {
  401: 'upload_session_expired',
  403: 'upload_forbidden',
  413: 'upload_too_large',
  504: 'upload_timeout',
  524: 'upload_timeout',
}

const firstApiError = data => {
  const errors = data?.errors
  const first = errors && typeof errors === 'object' ? Object.values(errors).flat()[0] : null

  return typeof first === 'string' ? first : null
}

const uploadErrorMessage = error => {
  if (error?.code === 'ERR_CANCELED')
    return t('parent_goal_details.upload_canceled')

  const response = error?.response
  if (!response)
    return t('parent_goal_details.upload_interrupted')

  const status = response.status
  const apiMessage = firstApiError(response.data)

  if ([410, 422, 423, 429].includes(status) && apiMessage)
    return apiMessage
  if (UPLOAD_STATUS_MESSAGES[status])
    return t(`parent_goal_details.${UPLOAD_STATUS_MESSAGES[status]}`, { mb: uploadType.value.maxMb })

  // A 404 without a denial reason means the route itself is missing on the API.
  if (status === 404)
    return t(response.data?.reason ? 'parent_goal_details.not_accessible' : 'parent_goal_details.upload_endpoint_missing')
  if (status >= 500)
    return apiMessage ?? t('parent_goal_details.upload_server_error')

  return t('parent_goal_details.upload_failed')
}

watch(() => upload.value.type, () => {
  upload.value.files = []
  upload.value.error = ''
})

watch(uploadFile, file => {
  if (!file)
    return

  upload.value.success = false
  upload.value.error = validateUploadFile(file)
})

const cancelUpload = () => {
  uploadController?.abort()
}

onBeforeUnmount(cancelUpload)

const sendUpload = (file, signal) => {
  const field = upload.value.type

  const payload = {
    caseId: props.caseId,
    section: props.section,
    field,
    file,
    fileName: uploadFileName(file, uploadType.value.extensions),
    content: upload.value.content.trim(),
  }

  if (file.size <= uploadChunkBytes.value) {
    return goalsReqest.addParentMessage(props.goalId, payload, progress => {
      upload.value.progress = progress
    }, signal)
  }

  const key = resumeKey(file, field)
  const previous = resumableUpload.value?.key === key ? resumableUpload.value : null
  const state = { key, uploadId: previous?.uploadId ?? createUploadId(), nextIndex: previous?.nextIndex ?? 0 }
  const startIndex = state.nextIndex

  resumableUpload.value = state

  return uploadInChunks({
    file,
    chunkBytes: uploadChunkBytes.value,
    signal,
    startIndex,
    onProgress: loaded => {
      upload.value.waiting = ''
      upload.value.progress = Math.min(100, Math.round((loaded / file.size) * 100))
    },
    onChunkSent: index => {
      state.nextIndex = index + 1
    },
    onWaiting: waiting => {
      upload.value.waiting = waiting
    },
    sendChunk: (chunk, onChunkProgress) => goalsReqest.addParentMessageChunk(
      props.goalId,
      { ...payload, uploadId: state.uploadId, chunk },
      onChunkProgress,
      signal,
    ),
  }).then(response => {
    resumableUpload.value = null

    return response
  }).catch(error => {
    // The server dropped the partial upload (expired or reclaimed): start it over once.
    if (startIndex > 0 && error?.response?.data?.reason === 'upload_expired') {
      resumableUpload.value = null

      return sendUpload(file, signal)
    }
    if (error?.code === 'ERR_CANCELED' || isRetryableUploadError(error))
      resumableUpload.value = { ...state }
    else
      resumableUpload.value = null

    throw error
  })
}

const submitUpload = () => {
  if (upload.value.sending)
    return

  const file = uploadFile.value

  upload.value.success = false

  const invalid = validateUploadFile(file)
  if (invalid) {
    upload.value.error = invalid

    return
  }

  upload.value.error = ''
  upload.value.waiting = ''
  upload.value.progress = 0
  upload.value.sending = true
  uploadController = new AbortController()

  sendUpload(file, uploadController.signal).then(response => {
    const item = response.data?.data?.media
    if (item) {
      mediaItems.value = [item, ...mediaItems.value.filter(existing => existing.id !== item.id)]
      mediaPage.value = { ...mediaPage.value, total: (mediaPage.value.total ?? 0) + 1 }
    }
    else {
      fetchDetails(1)
    }
    upload.value.files = []
    upload.value.content = ''
    upload.value.success = true
  }).catch(error => {
    upload.value.error = uploadErrorMessage(error)
  }).finally(() => {
    uploadController = null
    upload.value.sending = false
    upload.value.waiting = ''
    upload.value.progress = 0
  })
}
</script>

<template>
  <div
    v-if="loading"
    class="parent-page-skeleton pa-2"
    aria-busy="true"
  >
    <div
      class="parent-page-skeleton__bar"
      style="block-size: 2rem; inline-size: 60%;"
    />
    <div
      class="parent-page-skeleton__bar"
      style="block-size: 8rem;"
    />
    <div
      class="parent-page-skeleton__bar"
      style="block-size: 12rem;"
    />
  </div>

  <div
    v-else-if="loadError"
    class="d-flex flex-column gap-4"
  >
    <VAlert
      :type="loadError === 'denied' ? 'warning' : 'error'"
      variant="tonal"
    >
      {{ loadError === 'denied' ? $t('parent_goal_details.not_accessible') : $t('parent_goal_details.load_error') }}
    </VAlert>
    <div class="d-flex flex-wrap gap-3">
      <VBtn
        v-if="loadError === 'failed'"
        variant="tonal"
        @click="fetchDetails(1)"
      >
        {{ $t('parent_goal_details.retry') }}
      </VBtn>
      <VBtn
        :to="backTo"
        variant="text"
      >
        {{ $t('parent_goal_details.back_to_plan') }}
      </VBtn>
    </div>
  </div>

  <ParentPageShell
    v-else-if="goal"
    :title="goal.title"
    :subtitle-i18n-key="subtitleKey"
  >
    <div class="parent-goal-details">
      <VCard
        class="parent-goal-details__card"
        variant="outlined"
      >
        <VCardText>
          <div
            v-if="goal.case?.name || goal.standard || goal.generalization"
            class="d-flex flex-wrap gap-2 mb-4"
          >
            <VChip
              v-if="goal.case?.name"
              size="small"
              color="primary"
              variant="tonal"
              prepend-icon="tabler-user"
            >
              {{ $t('parent_goal_details.beneficiary') }}: {{ goal.case.name }}
            </VChip>
            <VChip
              v-if="goal.standard"
              size="small"
              variant="tonal"
            >
              {{ $t(goal.standard) }}
            </VChip>
            <VChip
              v-if="goal.generalization"
              size="small"
              variant="tonal"
            >
              {{ $t(goal.generalization) }}
            </VChip>
          </div>

          <h2 class="parent-goal-details__section-title">
            {{ $t('parent_goal_details.description') }}
          </h2>
          <p
            v-if="goal.description"
            class="parent-goal-details__description"
          >
            {{ goal.description }}
          </p>
          <p
            v-else
            class="text-medium-emphasis text-body-2 mb-0"
          >
            {{ $t('parent_goal_details.no_description') }}
          </p>

          <dl
            v-if="infoRows.length || sessionLines.length"
            class="parent-goal-details__facts"
          >
            <div
              v-for="row in infoRows"
              :key="row.key"
              class="parent-goal-details__fact"
            >
              <dt>{{ row.label }}</dt>
              <dd>{{ row.value }}</dd>
            </div>
            <div
              v-if="sessionLines.length"
              class="parent-goal-details__fact"
            >
              <dt>{{ $t('parent_goal_details.sessions') }}</dt>
              <dd>
                <span
                  v-for="line in sessionLines"
                  :key="line"
                  class="d-block"
                >{{ line }}</span>
              </dd>
            </div>
          </dl>
        </VCardText>
      </VCard>

      <VCard
        class="parent-goal-details__card"
        variant="outlined"
      >
        <VCardText>
          <h2 class="parent-goal-details__section-title">
            {{ $t('parent_goal_details.upload_title') }}
          </h2>
          <p class="text-medium-emphasis text-body-2 mb-4">
            {{ $t('parent_goal_details.upload_hint') }}
          </p>

          <VForm @submit.prevent="submitUpload">
            <VBtnToggle
              v-model="upload.type"
              mandatory
              divided
              density="comfortable"
              color="primary"
              variant="outlined"
              class="mb-4 parent-goal-details__upload-types"
              :aria-label="$t('parent_goal_details.upload_type')"
            >
              <VBtn
                v-for="type in uploadTypes"
                :key="type.value"
                :value="type.value"
                :prepend-icon="type.icon"
                :disabled="upload.sending"
              >
                {{ $t(`parent_goal_details.upload_type_${type.value}`) }}
              </VBtn>
            </VBtnToggle>

            <VFileInput
              :key="upload.type"
              v-model="upload.files"
              :accept="uploadType.accept"
              :label="$t(`parent_goal_details.upload_pick_${upload.type}`)"
              :hint="$t('parent_goal_details.upload_max_size', { mb: uploadType.maxMb })"
              persistent-hint
              :show-size="1024"
              prepend-icon=""
              prepend-inner-icon="tabler-paperclip"
              :disabled="upload.sending"
              class="mb-4"
            />

            <AppTextarea
              v-model="upload.content"
              :label="$t('parent_goal_details.upload_caption')"
              rows="2"
              auto-grow
              counter="2048"
              maxlength="2048"
              :disabled="upload.sending"
              class="mb-4"
            />

            <div
              v-if="upload.sending"
              class="mb-4"
            >
              <VProgressLinear
                :model-value="upload.progress"
                :indeterminate="upload.progress >= 100"
                color="primary"
                height="6"
                rounded
              />
              <p
                class="text-body-2 text-medium-emphasis mt-1 mb-0"
                aria-live="polite"
              >
                <template v-if="upload.waiting === 'reconnecting'">
                  {{ $t('parent_goal_details.upload_reconnecting') }}
                </template>
                <template v-else-if="upload.waiting === 'finalizing' || upload.progress >= 100">
                  {{ $t('parent_goal_details.upload_processing') }}
                </template>
                <template v-else>
                  {{ $t('parent_goal_details.upload_progress', { percent: upload.progress }) }}
                </template>
              </p>
              <p class="text-caption text-medium-emphasis mt-1 mb-0">
                {{ $t('parent_goal_details.upload_keep_open') }}
              </p>
            </div>

            <VAlert
              v-if="upload.error"
              type="error"
              variant="tonal"
              density="compact"
              class="mb-4"
            >
              {{ upload.error }}
            </VAlert>
            <VAlert
              v-else-if="upload.success"
              type="success"
              variant="tonal"
              density="compact"
              class="mb-4"
            >
              {{ $t('parent_goal_details.upload_success') }}
            </VAlert>

            <div class="d-flex flex-wrap gap-3">
              <VBtn
                type="submit"
                color="primary"
                :prepend-icon="canResumeUpload ? 'tabler-refresh' : 'tabler-upload'"
                :loading="upload.sending"
                :disabled="!uploadFile || upload.sending || uploadFileInvalid"
              >
                {{ $t(canResumeUpload ? 'parent_goal_details.upload_resume' : 'parent_goal_details.upload_submit') }}
              </VBtn>
              <VBtn
                v-if="upload.sending"
                variant="text"
                color="secondary"
                @click="cancelUpload"
              >
                {{ $t('parent_goal_details.upload_cancel') }}
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>

      <section
        class="parent-goal-details__media"
        :aria-label="$t('parent_goal_details.published_content')"
      >
        <h2 class="parent-goal-details__heading">
          {{ $t('parent_goal_details.published_content') }}
        </h2>

        <VAlert
          v-if="!mediaItems.length"
          type="info"
          variant="tonal"
        >
          {{ $t('parent_goal_details.no_media') }}
        </VAlert>

        <template v-else>
          <div
            v-if="updates.length"
            class="parent-goal-details__group"
          >
            <h3 class="parent-goal-details__group-title">
              <VIcon
                icon="tabler-message-2"
                size="20"
              />
              {{ $t('parent_goal_details.updates') }}
              <span class="text-medium-emphasis">({{ updates.length }})</span>
            </h3>
            <ul class="parent-goal-details__doc-list">
              <li
                v-for="item in updates"
                :key="`update-${item.id}`"
                class="parent-goal-details__doc"
              >
                <VAvatar
                  color="primary"
                  variant="tonal"
                  rounded
                  size="40"
                >
                  <VIcon icon="tabler-notes" />
                </VAvatar>
                <div class="parent-goal-details__doc-body">
                  <p class="parent-goal-details__doc-title">
                    {{ itemLabel(item) }}
                  </p>
                  <p class="parent-goal-details__meta mb-0">
                    {{ formatDate(item.created_at) }}
                  </p>
                  <p
                    v-if="item.content"
                    class="parent-goal-details__caption-text mb-0"
                  >
                    {{ item.content }}
                  </p>
                </div>
              </li>
            </ul>
          </div>

          <div
            v-if="images.length"
            class="parent-goal-details__group"
          >
            <h3 class="parent-goal-details__group-title">
              <VIcon
                icon="tabler-photo"
                size="20"
              />
              {{ $t('parent_goal_details.images') }}
              <span class="text-medium-emphasis">({{ images.length }})</span>
            </h3>
            <div class="parent-goal-details__image-grid">
              <figure
                v-for="item in images"
                :key="`img-${item.id}`"
                class="parent-goal-details__image-card"
              >
                <button
                  v-if="!failedImages.has(item.id)"
                  type="button"
                  class="parent-goal-details__image-button"
                  :aria-label="$t('parent_goal_details.open_image')"
                  @click="openImage(item)"
                >
                  <VImg
                    :src="item.image.url"
                    :alt="$t('parent_goal_details.image_alt', { date: formatDate(item.created_at) })"
                    aspect-ratio="4/3"
                    cover
                    @error="markImageFailed(item.id)"
                  />
                </button>
                <div
                  v-else
                  class="parent-goal-details__unavailable"
                >
                  <VIcon
                    icon="tabler-photo-off"
                    size="28"
                  />
                  <span>{{ $t('parent_goal_details.image_unavailable') }}</span>
                </div>
                <figcaption class="parent-goal-details__caption">
                  <span class="parent-goal-details__meta">{{ itemLabel(item) }} · {{ formatDate(item.created_at) }}</span>
                  <span
                    v-if="item.content"
                    class="parent-goal-details__caption-text"
                  >{{ item.content }}</span>
                </figcaption>
              </figure>
            </div>
          </div>

          <div
            v-if="videos.length"
            class="parent-goal-details__group"
          >
            <h3 class="parent-goal-details__group-title">
              <VIcon
                icon="tabler-video"
                size="20"
              />
              {{ $t('parent_goal_details.videos') }}
              <span class="text-medium-emphasis">({{ videos.length }})</span>
            </h3>
            <div class="parent-goal-details__video-list">
              <VCard
                v-for="item in videos"
                :key="`vid-${item.id}`"
                variant="outlined"
                class="parent-goal-details__video-card"
              >
                <video
                  v-if="!failedVideos.has(item.id)"
                  class="parent-goal-details__video"
                  :src="item.video.url"
                  controls
                  preload="metadata"
                  playsinline
                  @error="markVideoFailed(item.id)"
                />
                <VAlert
                  v-else
                  type="warning"
                  variant="tonal"
                  density="compact"
                  class="ma-3"
                >
                  {{ $t('parent_goal_details.video_unavailable') }}
                </VAlert>
                <VCardText class="pt-3">
                  <p class="parent-goal-details__meta mb-1">
                    {{ itemLabel(item) }} · {{ formatDate(item.created_at) }}
                  </p>
                  <p
                    v-if="item.content"
                    class="parent-goal-details__caption-text mb-2"
                  >
                    {{ item.content }}
                  </p>
                  <VBtn
                    :href="item.video.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    variant="text"
                    size="small"
                    class="px-1"
                    prepend-icon="tabler-download"
                  >
                    {{ $t('parent_goal_details.download') }}
                  </VBtn>
                </VCardText>
              </VCard>
            </div>
          </div>

          <div
            v-if="documents.length"
            class="parent-goal-details__group"
          >
            <h3 class="parent-goal-details__group-title">
              <VIcon
                icon="tabler-files"
                size="20"
              />
              {{ $t('parent_goal_details.documents') }}
              <span class="text-medium-emphasis">({{ documents.length }})</span>
            </h3>
            <ul class="parent-goal-details__doc-list">
              <li
                v-for="item in documents"
                :key="`doc-${item.id}`"
                class="parent-goal-details__doc"
              >
                <VAvatar
                  color="primary"
                  variant="tonal"
                  rounded
                  size="40"
                >
                  <VIcon :icon="documentIcon(item.document.kind)" />
                </VAvatar>
                <div class="parent-goal-details__doc-body">
                  <p class="parent-goal-details__doc-title">
                    {{ $t(`parent_goal_details.kind_${item.document.kind}`) }}
                    <span
                      v-if="item.document.extension"
                      class="text-medium-emphasis text-uppercase"
                    >({{ item.document.extension }})</span>
                  </p>
                  <p class="parent-goal-details__meta mb-0">
                    {{ itemLabel(item) }} · {{ formatDate(item.created_at) }}
                  </p>
                  <p
                    v-if="item.content"
                    class="parent-goal-details__caption-text mb-0"
                  >
                    {{ item.content }}
                  </p>
                </div>
                <div class="parent-goal-details__doc-actions">
                  <VBtn
                    v-if="item.document.previewable"
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-eye"
                    @click="previewDocument(item.document)"
                  >
                    {{ $t('parent_goal_details.preview') }}
                  </VBtn>
                  <VBtn
                    size="small"
                    variant="text"
                    prepend-icon="tabler-download"
                    :href="item.document.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    download
                  >
                    {{ $t('parent_goal_details.download') }}
                  </VBtn>
                </div>
              </li>
            </ul>
          </div>

          <div
            v-if="hasMoreMedia"
            class="d-flex justify-center"
          >
            <VBtn
              variant="tonal"
              :loading="loadingMore"
              @click="fetchDetails(mediaPage.current + 1)"
            >
              {{ $t('parent_goal_details.load_more') }}
            </VBtn>
          </div>
        </template>
      </section>

      <div>
        <VBtn
          :to="backTo"
          variant="tonal"
          color="primary"
          prepend-icon="tabler-arrow-left"
          :class="{ 'parent-goal-details__back--rtl': isRtl }"
        >
          {{ $t('parent_goal_details.back_to_plan') }}
        </VBtn>
      </div>
    </div>

    <VDialog
      v-model="imageDialog.open"
      max-width="960"
    >
      <VCard v-if="imageDialog.item">
        <DialogCloseBtn
          :aria-label="$t('parent_goal_details.close')"
          @click="imageDialog.open = false"
        />
        <VImg
          :src="imageDialog.item.image.url"
          :alt="$t('parent_goal_details.image_alt', { date: formatDate(imageDialog.item.created_at) })"
          max-height="75vh"
          contain
        />
        <VCardText class="d-flex flex-wrap align-center gap-3">
          <span
            v-if="imageDialog.item.content"
            class="parent-goal-details__caption-text flex-grow-1"
          >{{ imageDialog.item.content }}</span>
          <VSpacer v-else />
          <VBtn
            :href="imageDialog.item.image.url"
            target="_blank"
            rel="noopener noreferrer"
            variant="text"
            prepend-icon="tabler-download"
          >
            {{ $t('parent_goal_details.download') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <VDialog
      v-model="documentDialog.open"
      max-width="1000"
    >
      <VCard v-if="documentDialog.document">
        <DialogCloseBtn
          :aria-label="$t('parent_goal_details.close')"
          @click="documentDialog.open = false"
        />
        <VCardTitle class="pe-12">
          {{ $t(`parent_goal_details.kind_${documentDialog.document.kind}`) }}
        </VCardTitle>
        <VCardText>
          <iframe
            v-if="documentDialog.document.kind === 'pdf'"
            :src="documentDialog.document.url"
            :title="$t('parent_goal_details.kind_pdf')"
            class="parent-goal-details__pdf"
          />
          <video
            v-else-if="documentDialog.document.kind === 'video'"
            :src="documentDialog.document.url"
            class="parent-goal-details__video"
            controls
            playsinline
          />
          <audio
            v-else-if="documentDialog.document.kind === 'audio'"
            :src="documentDialog.document.url"
            controls
            class="w-100"
          />
        </VCardText>
        <VCardText class="d-flex justify-end pt-0">
          <VBtn
            :href="documentDialog.document.url"
            target="_blank"
            rel="noopener noreferrer"
            variant="tonal"
            prepend-icon="tabler-external-link"
          >
            {{ $t('parent_goal_details.download') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
  </ParentPageShell>
</template>

<style lang="scss" scoped>
@import '@styles/parent-experience.scss';

.parent-goal-details {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.parent-goal-details__section-title,
.parent-goal-details__heading {
  margin: 0 0 0.5rem;
  font-size: 1rem;
  font-weight: 600;
}

.parent-goal-details__heading {
  font-size: 1.125rem;
  margin-block-end: 0.75rem;
}

.parent-goal-details__description,
.parent-goal-details__caption-text {
  margin: 0;
  white-space: pre-line;
  line-height: 1.7;
  overflow-wrap: anywhere;
}

.parent-goal-details__facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
  gap: 1rem;
  margin: 1.25rem 0 0;
  padding-block-start: 1rem;
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.parent-goal-details__fact {
  min-width: 0;

  dt {
    font-size: 0.8125rem;
    font-weight: 600;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  }

  dd {
    margin: 0.2rem 0 0;
    overflow-wrap: anywhere;
  }
}

.parent-goal-details__media {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.parent-goal-details__group {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.parent-goal-details__group-title {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 600;
}

.parent-goal-details__meta {
  font-size: 0.8125rem;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.parent-goal-details__image-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
  gap: 1rem;
}

.parent-goal-details__image-card {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  margin: 0;
  min-width: 0;
}

.parent-goal-details__image-button {
  display: block;
  width: 100%;
  padding: 0;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.5rem;
  overflow: hidden;
  cursor: zoom-in;
  background: rgba(var(--v-theme-on-surface), 0.04);
  transition: box-shadow 0.15s ease, border-color 0.15s ease;

  &:hover {
    border-color: rgba(var(--v-theme-primary), 0.5);
  }

  &:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: 2px;
  }
}

.parent-goal-details__unavailable {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  aspect-ratio: 4 / 3;
  padding: 0.75rem;
  border: 1px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  text-align: center;
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
}

.parent-goal-details__caption {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  font-size: 0.875rem;
}

.parent-goal-details__video-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr));
  gap: 1rem;
}

.parent-goal-details__video {
  display: block;
  width: 100%;
  max-height: 60vh;
  background: #000;
}

.parent-goal-details__doc-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.parent-goal-details__doc {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.75rem 1rem;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 0.5rem;
}

.parent-goal-details__doc-body {
  flex: 1 1 14rem;
  min-width: 0;
}

.parent-goal-details__doc-title {
  margin: 0 0 0.15rem;
  font-weight: 600;
}

.parent-goal-details__doc-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}

.parent-goal-details__pdf {
  display: block;
  width: 100%;
  height: 70vh;
  border: 0;
}

.parent-goal-details__upload-types {
  flex-wrap: wrap;
  block-size: auto;
  max-inline-size: 100%;
}

.parent-goal-details__back--rtl :deep(.v-btn__prepend .v-icon) {
  transform: scaleX(-1);
}
</style>
