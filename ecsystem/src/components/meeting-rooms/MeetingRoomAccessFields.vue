<script setup>
import { meetingAudienceLabel, MEETING_AUDIENCE_OPTIONS } from '@/utils/meetingRoomAudienceUi'

const props = defineProps({
  audienceType: { type: String, default: 'general' },
  selectedParentIds: { type: Array, default: () => [] },
  searchParents: { type: Function, required: true },
  parentOptionTitle: { type: Function, required: true },
  showInvitationActions: { type: Boolean, default: false },
  invitationLinkLoading: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:audienceType',
  'update:selectedParentIds',
  'copyInvitationLink',
  'regenerateInvitationLink',
])

const isParentSpecific = computed(() => props.audienceType === 'parents')
const activeOption = computed(
  () => MEETING_AUDIENCE_OPTIONS.find(o => o.value === props.audienceType) || MEETING_AUDIENCE_OPTIONS[0],
)

const setAudienceType = value => {
  emit('update:audienceType', value)
}

const onParentIdsUpdate = value => {
  emit('update:selectedParentIds', value)
}
</script>

<template>
  <div class="meeting-access-fields">
    <p class="meeting-access-fields__label">
      {{ $t('rooms.audience_type') }}
    </p>
    <div
      class="meeting-access-fields__grid"
      role="radiogroup"
      :aria-label="$t('rooms.audience_type')"
    >
      <button
        v-for="opt in MEETING_AUDIENCE_OPTIONS"
        :key="opt.value"
        type="button"
        class="meeting-access-fields__option"
        :class="{ 'meeting-access-fields__option--active': audienceType === opt.value }"
        role="radio"
        :aria-checked="audienceType === opt.value"
        @click="setAudienceType(opt.value)"
      >
        <span class="meeting-access-fields__option-icon">
          <VIcon
            :icon="opt.icon"
            size="22"
          />
        </span>
        <span class="meeting-access-fields__option-text">
          {{ meetingAudienceLabel(opt.value) }}
        </span>
      </button>
    </div>

    <VAlert
      v-if="activeOption.hintKey"
      type="info"
      variant="tonal"
      density="compact"
      class="meeting-access-fields__hint"
    >
      {{ $t(activeOption.hintKey) }}
    </VAlert>

    <div
      v-if="showInvitationActions && audienceType === 'link_invite'"
      class="meeting-access-fields__invite-actions"
    >
      <VBtn
        size="small"
        variant="tonal"
        prepend-icon="tabler-copy"
        :loading="invitationLinkLoading"
        @click="emit('copyInvitationLink')"
      >
        {{ $t('rooms.copy_invitation_link') }}
      </VBtn>
      <VBtn
        size="small"
        variant="outlined"
        prepend-icon="tabler-refresh"
        :loading="invitationLinkLoading"
        @click="emit('regenerateInvitationLink')"
      >
        {{ $t('rooms.regenerate_invitation_link') }}
      </VBtn>
    </div>

    <AppAutocomplete
      v-if="isParentSpecific"
      :model-value="selectedParentIds"
      :server-search="searchParents"
      :item-title="parentOptionTitle"
      item-value="id"
      :label="$t('rooms.target_parents')"
      :no-data-text="$t('autocomplete.no_results')"
      multiple
      chips
      closable-chips
      clearable
      :preload-limit="50"
      class="meeting-access-fields__parents"
      @update:model-value="onParentIdsUpdate"
    />
  </div>
</template>
