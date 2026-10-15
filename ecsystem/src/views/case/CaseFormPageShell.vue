<script setup>
import PageHeaderActions from '@/components/PageHeaderActions.vue'

const props = defineProps({
  currentTab: {
    type: Number,
    required: true,
  },
  tabs: {
    type: Array,
    required: true,
  },
  saving: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:currentTab', 'save', 'cancel'])

const { t } = useI18n()

const progressPercent = computed(() => {
  const total = props.tabs.length || 1

  return Math.round(((props.currentTab + 1) / total) * 100)
})

const progressLabel = computed(() => t('cases.form_step_progress', {
  current: props.currentTab + 1,
  total: props.tabs.length,
}))

const selectTab = index => {
  emit('update:currentTab', index)
}

const goPrev = () => {
  if (props.currentTab > 0)
    emit('update:currentTab', props.currentTab - 1)
}

const goNext = () => {
  if (props.currentTab < props.tabs.length - 1)
    emit('update:currentTab', props.currentTab + 1)
}

const isFirst = computed(() => props.currentTab === 0)
const isLast = computed(() => props.currentTab === props.tabs.length - 1)
</script>

<template>
  <div class="case-form-page">
    <PageHeaderActions>
      <VBtn
        variant="outlined"
        color="secondary"
        class="case-form-page__btn-cancel"
        @click="emit('cancel')"
      >
        {{ t('cases.back_to_list') }}
      </VBtn>
      <VBtn
        class="general-page-header__btn-primary"
        :loading="saving"
        prepend-icon="tabler-device-floppy"
        @click="emit('save')"
      >
        {{ t('Save') }}
      </VBtn>
    </PageHeaderActions>

    <section
      class="case-form-page__shell"
      :aria-label="t('Register Case')"
    >
      <div class="case-form-page__progress">
        <div
          class="case-form-page__progress-track"
          role="progressbar"
          :aria-valuenow="progressPercent"
          aria-valuemin="0"
          aria-valuemax="100"
        >
          <div
            class="case-form-page__progress-fill"
            :style="{ inlineSize: `${progressPercent}%` }"
          />
        </div>
        <span class="case-form-page__progress-label">{{ progressLabel }}</span>
      </div>

      <nav
        class="case-form-page__nav"
        :aria-label="t('cases.form_sections_nav')"
      >
        <button
          v-for="(tab, index) in tabs"
          :key="tab.id"
          type="button"
          class="case-form-page__nav-item"
          :class="{ 'is-active': currentTab === index }"
          @click="selectTab(index)"
        >
          <span class="case-form-page__nav-icon">
            <VIcon
              :icon="tab.icon"
              size="20"
            />
          </span>
          <span class="case-form-page__nav-text">
            <span class="case-form-page__nav-step">{{ t('cases.form_step_n', { n: index + 1 }) }}</span>
            <span class="case-form-page__nav-title">{{ t(tab.labelKey) }}</span>
          </span>
        </button>
      </nav>

      <div class="case-form-page__body">
        <div class="case-form-page__window">
          <slot />
        </div>

        <footer class="case-form-page__footer">
          <p class="case-form-page__footer-hint">
            {{ t('cases.form_footer_hint') }}
          </p>
          <div class="case-form-page__footer-actions">
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="isFirst"
              prepend-icon="tabler-arrow-right"
              @click="goPrev"
            >
              {{ t('cases.form_previous') }}
            </VBtn>
            <VBtn
              v-if="!isLast"
              variant="flat"
              color="primary"
              append-icon="tabler-arrow-left"
              @click="goNext"
            >
              {{ t('cases.form_next') }}
            </VBtn>
            <VBtn
              v-else
              class="general-page-header__btn-primary"
              :loading="saving"
              prepend-icon="tabler-device-floppy"
              @click="emit('save')"
            >
              {{ t('Save') }}
            </VBtn>
          </div>
        </footer>
      </div>
    </section>
  </div>
</template>

<style lang="scss">
@import '@/styles/case-form-page.scss';
</style>
