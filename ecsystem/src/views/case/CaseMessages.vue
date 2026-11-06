<script setup>
import moment from '@/plugins/moment'
import { avatarText } from '@core/utils/formatters'
import { messagesApi } from '@/plugins/apis/messagesReqest'
import { isParentUser } from '@core/utils/staffSessionVisibility'

const props = defineProps({
  caseData: {
    type: Object,
    required: true,
  },
})

const PER_PAGE = 15
const SESSION_MARKER_TYPES = ['started_session', 'ended_session']
const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']
const VIDEO_EXTENSIONS = ['mp4', 'mov', 'ogg', 'webm', 'm4v']

const { t, te, locale } = useI18n()
const messagesReqest = messagesApi()
const viewerIsParent = isParentUser()

const filter = ref('all')
const items = ref([])
const page = ref(0)
const lastPage = ref(1)
const loading = ref(false)
const loadingMore = ref(false)
const loadError = ref(null)
let requestSeq = 0

const filters = computed(() => [
  { value: 'all', title: t('case_profile.filter_all'), icon: 'tabler-list' },
  { value: 'session', title: t('case_profile.filter_session'), icon: 'tabler-video' },
  { value: 'staff_comment', title: t('case_profile.filter_staff_comment'), icon: 'tabler-message' },
  { value: 'parent', title: t('case_profile.filter_parent'), icon: 'tabler-users' },
])

const hasMore = computed(() => page.value > 0 && page.value < lastPage.value)

const applyPage = (payload, requestedPage) => {
  const rows = payload?.data ?? []
  if (requestedPage === 1) {
    items.value = rows
  }
  else {
    // New uploads shift the pages, so the next page can repeat items already shown.
    const shown = new Set(items.value.map(item => item.id))

    items.value = items.value.concat(rows.filter(item => !shown.has(item.id)))
  }
  page.value = Number(payload?.currentPage) || requestedPage
  lastPage.value = Number(payload?.lastPage) || page.value
}

const applyFirstPageError = error => {
  items.value = []
  page.value = 0
  loadError.value = error?.response?.status === 403 ? 'denied' : 'failed'
}

const fetchPage = nextPage => {
  const seq = ++requestSeq
  const isCurrent = () => seq === requestSeq

  loading.value = nextPage === 1
  loadingMore.value = nextPage !== 1
  if (nextPage === 1)
    loadError.value = null

  messagesReqest.fetchAll({
    case_id: props.caseData.id,
    contribution: filter.value === 'all' ? undefined : filter.value,
    options: { itemsPerPage: PER_PAGE },
    page: nextPage,
  }).then(response => {
    if (isCurrent())
      applyPage(response.data, nextPage)
  }).catch(error => {
    if (isCurrent() && nextPage === 1)
      applyFirstPageError(error)
  }).finally(() => {
    if (!isCurrent())
      return

    loading.value = false
    loadingMore.value = false
  })
}

const reload = () => fetchPage(1)

watch(() => [props.caseData?.id, filter.value], reload, { immediate: true })

const senderType = item => {
  if (item.sender_type)
    return item.sender_type

  const roles = item.user?.roles ?? []

  return roles.length && roles.every(role => role.default_name === 'parent') ? 'parent' : 'staff'
}

const isParentSender = item => item.user && senderType(item) === 'parent'

const isSessionMarker = item => SESSION_MARKER_TYPES.includes(item.type)

const kindLabel = item => {
  if (Number(item.log_work) === 1)
    return t('case_profile.kind_session')

  return isParentSender(item) ? t('case_profile.kind_parent') : t('case_profile.kind_comment')
}

const senderLabel = item => {
  if (!item.user)
    return t('case_profile.sender_unknown')

  return isParentSender(item) ? t('case_profile.sender_parent') : t('case_profile.sender_staff')
}

const staffRoles = item => (item.user?.roles ?? [])
  .map(role => role.name)
  .filter(Boolean)
  .join(String(locale.value).startsWith('ar') ? '، ' : ', ')

const senderLink = item => {
  if (viewerIsParent || !item.user?.id)
    return null

  return isParentSender(item)
    ? { name: 'parents-view-id', params: { id: item.user.id } }
    : { name: 'user-view-tab-id', params: { id: item.user.id, tab: 'info' } }
}

const programLabel = item => {
  const key = `case_profile.programs.${item.goal?.category}`

  return item.goal?.category && te(key) ? t(key) : ''
}

const formatDateTime = value => value
  ? moment(value).locale(locale.value).format('D MMMM YYYY - h:mm a')
  : ''

const fromNow = value => value ? moment(value).locale(locale.value).fromNow() : ''

const extensionOf = file => String(file?.file_extension ?? '').toLowerCase()

const imageOf = item => {
  if (item.image?.file_url)
    return item.image
  if (item.file?.file_url && IMAGE_EXTENSIONS.includes(extensionOf(item.file)))
    return item.file

  return null
}

const videoOf = item => {
  if (item.video?.file_url)
    return item.video
  if (item.file?.file_url && (item.is_file_video || VIDEO_EXTENSIONS.includes(extensionOf(item.file))))
    return item.file

  return null
}

const documentOf = item => {
  if (!item.file?.file_url || imageOf(item) === item.file || videoOf(item) === item.file)
    return null

  return item.file
}

const documentName = file => {
  const extension = extensionOf(file)

  return extension ? `${file.file_name}.${extension}` : file.file_name
}

const openNewTab = url => {
  window.open(url, '_blank', 'noopener')
}

const sessionMarkerLabel = item => item.type === 'started_session'
  ? t('case_profile.session_started')
  : t('case_profile.session_ended')
</script>

<template>
  <section class="case-clips">
    <VCard class="mb-4">
      <VCardText class="d-flex flex-column gap-3">
        <div>
          <h5 class="text-h5 mb-1">
            {{ $t('case_profile.clips_title') }}
          </h5>
          <p class="text-body-2 text-medium-emphasis mb-0">
            {{ $t('case_profile.clips_subtitle') }}
          </p>
        </div>
        <VChipGroup
          v-model="filter"
          mandatory
          selected-class="text-primary"
          :aria-label="$t('case_profile.filter_label')"
          class="case-clips__filters"
        >
          <VChip
            v-for="option in filters"
            :key="option.value"
            :value="option.value"
            :prepend-icon="option.icon"
            variant="tonal"
            filter
          >
            {{ option.title }}
          </VChip>
        </VChipGroup>
      </VCardText>
    </VCard>

    <div
      v-if="loading"
      class="d-flex justify-center pa-6"
    >
      <VProgressCircular
        indeterminate
        color="primary"
      />
    </div>

    <VAlert
      v-else-if="loadError"
      :type="loadError === 'denied' ? 'warning' : 'error'"
      variant="tonal"
    >
      <div class="d-flex flex-wrap align-center justify-space-between gap-3">
        <span>{{ loadError === 'denied' ? $t('case_profile.denied') : $t('case_profile.load_failed') }}</span>
        <VBtn
          v-if="loadError === 'failed'"
          size="small"
          variant="tonal"
          @click="reload"
        >
          {{ $t('case_profile.retry') }}
        </VBtn>
      </div>
    </VAlert>

    <VCard
      v-else-if="!items.length"
      variant="outlined"
      class="text-center pa-8"
    >
      <VIcon
        icon="tabler-video-off"
        size="40"
        class="text-disabled mb-3"
      />
      <p class="text-body-1 text-medium-emphasis mb-0">
        {{ filter === 'all' ? $t('case_profile.empty_all') : $t('case_profile.empty_filtered') }}
      </p>
    </VCard>

    <template v-else>
      <template
        v-for="item in items"
        :key="item.id"
      >
        <div
          v-if="isSessionMarker(item)"
          class="case-clips__marker d-flex flex-wrap align-center gap-2 mb-4 text-body-2"
        >
          <VIcon
            :icon="item.type === 'started_session' ? 'tabler-player-play' : 'tabler-player-stop'"
            :color="item.type === 'started_session' ? 'success' : 'error'"
            size="18"
          />
          <span class="font-weight-medium">{{ sessionMarkerLabel(item) }}</span>
          <span v-if="item.goal?.title">— {{ item.goal.title }}</span>
          <span
            v-if="item.user?.name"
            class="text-medium-emphasis"
          >· {{ item.user.name }}</span>
          <span
            class="text-medium-emphasis"
            :title="fromNow(item.created_at)"
          >· {{ formatDateTime(item.created_at) }}</span>
        </div>

        <VCard
          v-else
          class="case-clip mb-4"
          :class="isParentSender(item) ? 'case-clip--parent' : 'case-clip--staff'"
        >
          <VCardText>
            <div class="d-flex flex-wrap align-start justify-space-between gap-3">
              <div class="d-flex align-center gap-3">
                <VAvatar
                  size="40"
                  :color="isParentSender(item) ? 'warning' : 'primary'"
                  :variant="item.user?.picture?.file_url ? undefined : 'tonal'"
                >
                  <VImg
                    v-if="item.user?.picture?.file_url"
                    :src="item.user.picture.file_url"
                  />
                  <span v-else>{{ avatarText(item.user?.name || '?') }}</span>
                </VAvatar>
                <div class="d-flex flex-column">
                  <RouterLink
                    v-if="senderLink(item)"
                    :to="senderLink(item)"
                    class="text-h6 font-weight-medium case-clip__name"
                  >
                    {{ item.user.name }}
                  </RouterLink>
                  <span
                    v-else
                    class="text-h6 font-weight-medium"
                  >{{ item.user?.name || $t('case_profile.sender_unknown') }}</span>
                  <div class="d-flex flex-wrap align-center gap-2 mt-1">
                    <VChip
                      size="small"
                      label
                      :color="isParentSender(item) ? 'warning' : 'primary'"
                      :prepend-icon="isParentSender(item) ? 'tabler-user-heart' : 'tabler-school'"
                    >
                      {{ senderLabel(item) }}
                    </VChip>
                    <span
                      v-if="item.user && !isParentSender(item) && staffRoles(item)"
                      class="text-caption text-medium-emphasis"
                    >{{ staffRoles(item) }}</span>
                  </div>
                </div>
              </div>

              <div class="d-flex flex-column align-end gap-1 text-caption text-medium-emphasis">
                <span :title="fromNow(item.created_at)">
                  <VIcon
                    icon="tabler-clock"
                    size="14"
                    class="me-1"
                  />{{ formatDateTime(item.created_at) }}
                </span>
                <VChip
                  size="x-small"
                  variant="tonal"
                  :color="Number(item.log_work) === 1 ? 'success' : 'secondary'"
                >
                  {{ kindLabel(item) }}
                </VChip>
                <span
                  v-if="!viewerIsParent && !isParentSender(item)"
                  :class="Number(item.parents_can_see) === 1 ? 'text-success' : 'text-disabled'"
                >
                  {{ Number(item.parents_can_see) === 1 ? $t('case_profile.visible_to_parents') : $t('case_profile.hidden_from_parents') }}
                </span>
              </div>
            </div>

            <div
              v-if="item.goal"
              class="case-clip__session d-flex flex-wrap align-center gap-2 mt-4 pa-2 rounded text-body-2"
            >
              <VIcon
                icon="tabler-calendar-event"
                size="18"
              />
              <span class="font-weight-medium">{{ $t('case_profile.session_label') }}:</span>
              <span>{{ item.goal.title }}</span>
              <VChip
                v-if="programLabel(item)"
                size="x-small"
                variant="outlined"
              >
                {{ programLabel(item) }}
              </VChip>
            </div>

            <p
              v-if="item.content"
              class="case-clip__content text-body-1 mt-4 mb-0"
            >
              {{ item.content }}
            </p>

            <div
              v-if="imageOf(item)"
              class="mt-4"
            >
              <VImg
                :src="imageOf(item).file_url"
                max-height="360"
                class="case-clip__image rounded cursor-pointer"
                :alt="$t('case_profile.open_image')"
                @click="openNewTab(imageOf(item).file_url)"
              />
            </div>

            <div
              v-if="videoOf(item)"
              class="mt-4"
            >
              <video
                class="case-clip__video rounded"
                :src="videoOf(item).file_url"
                controls
                preload="metadata"
              />
            </div>

            <div class="d-flex flex-wrap gap-3 mt-3">
              <VBtn
                v-if="documentOf(item)"
                variant="tonal"
                color="success"
                size="small"
                prepend-icon="tabler-file-download"
                @click="openNewTab(documentOf(item).file_url)"
              >
                {{ documentName(documentOf(item)) }}
              </VBtn>
              <VBtn
                v-if="videoOf(item)"
                variant="text"
                color="info"
                size="small"
                prepend-icon="tabler-download"
                @click="openNewTab(videoOf(item).file_url)"
              >
                {{ $t('case_profile.download') }}
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </template>

      <div
        v-if="hasMore"
        class="d-flex justify-center"
      >
        <VBtn
          variant="tonal"
          :loading="loadingMore"
          prepend-icon="tabler-history"
          @click="fetchPage(page + 1)"
        >
          {{ $t('case_profile.load_older') }}
        </VBtn>
      </div>
    </template>
  </section>
</template>

<style lang="scss" scoped>
.case-clip {
  border-inline-start: 4px solid transparent;
}

.case-clip--staff {
  border-inline-start-color: rgb(var(--v-theme-primary));
}

.case-clip--parent {
  border-inline-start-color: rgb(var(--v-theme-warning));
  background: rgba(var(--v-theme-warning), 0.04);
}

.case-clip__name:not(:hover) {
  color: rgba(var(--v-theme-on-background), var(--v-high-emphasis-opacity));
}

.case-clip__session {
  background: rgba(var(--v-theme-on-surface), 0.04);
}

.case-clip__content {
  line-height: 1.7;
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.case-clip__video {
  display: block;
  max-height: 360px;
  inline-size: 100%;
  max-inline-size: 560px;
  background: #000;
}

.case-clips__marker {
  padding-inline: 0.5rem;
}

.case-clips__filters :deep(.v-slide-group__content) {
  flex-wrap: wrap;
}
</style>
