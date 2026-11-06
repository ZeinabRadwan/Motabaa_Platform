<script setup>
import { hrLocationMonitoringApi } from '@/plugins/apis/hrRequest'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

/**
 * Outside-radius alerts: one per uninterrupted period outside the allowed radius during a session. Reviewing an
 * alert records HR's decision only; attendance itself is never changed by location monitoring.
 */
const props = defineProps({
  filters: { type: Object, required: true },
})

const emit = defineEmits(['reviewed'])

const { t } = useI18n()

const REVIEW_COLORS = { pending: 'warning', acknowledged: 'success', dismissed: 'secondary' }

const canEdit = computed(() => can('edit_hr-location-monitoring', 'edit_hr-location-monitoring'))

const reviewStatus = ref('pending')
const items = ref([])
const total = ref(0)
const loading = ref(false)
const options = ref({ page: 1, itemsPerPage: 25, sortBy: [], groupBy: [] })

const reviewItems = computed(() => ['pending', 'acknowledged', 'dismissed'].map(value => ({ value, title: t(`hr.location_monitoring.review.${value}`) })))

const fetchExceptions = () => {
  loading.value = true

  return hrLocationMonitoringApi.exceptions({
    branch_id: props.filters.branchId || undefined,
    employee_id: props.filters.employeeId || undefined,
    from: props.filters.date || undefined,
    to: props.filters.date || undefined,
    review_status: reviewStatus.value || undefined,
    options: options.value,
  }).then(response => {
    items.value = response.data.data
    total.value = response.data.total
  }).catch(() => {
    items.value = []
    total.value = 0
  }).finally(() => {
    loading.value = false
  })
}

watchServerTableFetch(fetchExceptions, {
  filters: () => [props.filters.branchId, props.filters.employeeId, props.filters.date, reviewStatus.value],
  options,
})

const headers = computed(() => [
  { title: t('hr.fields.employee'), key: 'employee', sortable: false },
  { title: t('hr.location_monitoring.period'), key: 'period', sortable: false },
  { title: t('hr.my_attendance.work_location'), key: 'work_location', sortable: false },
  { title: t('hr.location_monitoring.max_distance'), key: 'max_distance_meters', sortable: false },
  { title: t('hr.location_monitoring.review_status'), key: 'review_status', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
])

const reviewTarget = ref(null)
const reviewForm = ref({ review_status: 'acknowledged', notes: '' })
const reviewErrors = ref({})
const saving = ref(false)

const openReview = exception => {
  reviewTarget.value = exception
  reviewForm.value = { review_status: exception.review_status === 'dismissed' ? 'dismissed' : 'acknowledged', notes: exception.review_notes ?? '' }
  reviewErrors.value = {}
}

const saveReview = async () => {
  saving.value = true
  reviewErrors.value = {}
  try {
    const { data } = await hrLocationMonitoringApi.reviewException(reviewTarget.value.id, reviewForm.value)

    window.Snackbar?.value?.exposevisibleSnackbar?.(data.message, 'success')
    reviewTarget.value = null
    fetchExceptions()
    emit('reviewed')
  }
  catch (error) {
    reviewErrors.value = error.response?.data?.errors ?? {}
  }
  finally {
    saving.value = false
  }
}

defineExpose({ refresh: fetchExceptions })
</script>

<template>
  <div>
    <VCardText class="pb-0">
      <VRow>
        <VCol
          cols="12"
          sm="4"
          md="3"
        >
          <AppSelect
            v-model="reviewStatus"
            :items="reviewItems"
            :placeholder="$t('hr.location_monitoring.review_status')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDataTableServer
      v-model:items-per-page="options.itemsPerPage"
      v-model:page="options.page"
      :items="items"
      :items-length="total"
      :headers="headers"
      :loading="loading"
      item-value="id"
      class="text-no-wrap"
      @update:options="applyServerTableOptions(options, $event)"
    >
      <template #item.employee="{ item }">
        <div>{{ item.raw.employee.name }}</div>
        <div class="text-caption hr-page__muted">
          <span dir="ltr">{{ item.raw.employee.employee_number }}</span>
          <span v-if="item.raw.employee.branch"> · {{ item.raw.employee.branch.name }}</span>
        </div>
      </template>
      <template #item.period="{ item }">
        <span dir="ltr">{{ item.raw.date }} · {{ item.raw.started_time }} - {{ item.raw.ended_time ?? item.raw.last_seen_time }}</span>
        <VChip
          v-if="item.raw.status === 'open'"
          color="error"
          size="x-small"
          label
          class="ms-2"
        >
          {{ $t('hr.location_monitoring.still_outside') }}
        </VChip>
        <div class="text-caption hr-page__muted">
          {{ $t('hr.location_monitoring.checks_outside', { count: item.raw.checks_count }) }}
        </div>
      </template>
      <template #item.work_location="{ item }">
        {{ item.raw.work_location?.name ?? '—' }}
      </template>
      <template #item.max_distance_meters="{ item }">
        {{ item.raw.max_distance_meters !== null ? $t('hr.my_attendance.meters', { value: Math.round(item.raw.max_distance_meters) }) : '—' }}
      </template>
      <template #item.review_status="{ item }">
        <VChip
          :color="REVIEW_COLORS[item.raw.review_status] || 'secondary'"
          size="small"
          label
        >
          {{ $t(`hr.location_monitoring.review.${item.raw.review_status}`) }}
        </VChip>
        <div
          v-if="item.raw.reviewed_by"
          class="text-caption hr-page__muted"
        >
          {{ item.raw.reviewed_by.name }}
        </div>
        <div
          v-if="item.raw.review_notes"
          class="text-caption hr-page__muted text-wrap"
        >
          {{ item.raw.review_notes }}
        </div>
      </template>
      <template #item.actions="{ item }">
        <VBtn
          v-if="canEdit"
          size="small"
          variant="tonal"
          @click="openReview(item.raw)"
        >
          {{ $t('hr.location_monitoring.review_action') }}
        </VBtn>
      </template>
    </VDataTableServer>

    <VDialog
      :model-value="!!reviewTarget"
      max-width="480"
      @update:model-value="reviewTarget = null"
    >
      <VCard v-if="reviewTarget">
        <VCardItem>
          <VCardTitle>{{ $t('hr.location_monitoring.review_title') }}</VCardTitle>
          <VCardSubtitle>{{ reviewTarget.employee.name }} · {{ reviewTarget.date }}</VCardSubtitle>
        </VCardItem>
        <VCardText>
          <VRadioGroup
            v-model="reviewForm.review_status"
            :error-messages="reviewErrors.review_status"
          >
            <VRadio
              value="acknowledged"
              :label="$t('hr.location_monitoring.review.acknowledged')"
            />
            <VRadio
              value="dismissed"
              :label="$t('hr.location_monitoring.review.dismissed')"
            />
          </VRadioGroup>
          <AppTextarea
            v-model="reviewForm.notes"
            :label="$t('hr.location_monitoring.review_notes')"
            :hint="reviewForm.review_status === 'dismissed' ? $t('hr.location_monitoring.notes_required') : ''"
            persistent-hint
            :error-messages="reviewErrors.notes"
            rows="3"
          />
          <div class="text-caption hr-page__muted mt-3">
            {{ $t('hr.location_monitoring.review_note') }}
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="reviewTarget = null"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="saving"
            @click="saveReview"
          >
            {{ $t('hr.location_monitoring.save_review') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
