<script setup>
import { hrMyLeaveApi } from '@/plugins/apis/hrRequest'
import { formatDays } from '@/utils/hrLeave'
import HrLeaveRequestDialog from '@/views/hr/leave/HrLeaveRequestDialog.vue'
import HrLeaveRequestsTable from '@/views/hr/leave/HrLeaveRequestsTable.vue'

// Employee self-service: balances of the current year, new requests (self-service leave types only)
// and the leave / approval history. Users without an HR employee record see a notice instead.

const overview = ref(null)
const loading = ref(false)
const requestOpen = ref(false)
const history = ref(null)

const loadOverview = () => {
  loading.value = true

  return hrMyLeaveApi.overview().then(response => {
    overview.value = response.data.data
  }).catch(() => {
    overview.value = null
  }).finally(() => {
    loading.value = false
  })
}

const onChanged = () => {
  loadOverview()
  history.value?.refresh()
}

const percentUsed = balance => {
  const total = Number(balance.total_days) || 0

  return total > 0 ? Math.min(100, Math.round(((Number(balance.used_days) + Number(balance.pending_days)) / total) * 100)) : 0
}

onMounted(loadOverview)
</script>

<template>
  <div class="hr-page">
    <VProgressLinear
      v-if="loading && !overview"
      indeterminate
    />

    <VAlert
      v-else-if="overview && !overview.eligible"
      type="info"
      variant="tonal"
    >
      {{ $t('hr.my_leave.not_eligible') }}
    </VAlert>

    <template v-else-if="overview">
      <VCard>
        <VCardItem>
          <VCardTitle>{{ $t('hr.my_leave.balances_title', { year: overview.year }) }}</VCardTitle>
          <VCardSubtitle v-if="overview.pending_count">
            {{ $t('hr.my_leave.pending_requests', { count: overview.pending_count }) }}
          </VCardSubtitle>
          <template #append>
            <VBtn
              prepend-icon="tabler-plus"
              :disabled="!overview.leave_types?.length"
              @click="requestOpen = true"
            >
              {{ $t('hr.my_leave.new_request') }}
            </VBtn>
          </template>
        </VCardItem>
        <VCardText>
          <VRow v-if="overview.balances?.length">
            <VCol
              v-for="balance in overview.balances"
              :key="balance.id"
              cols="12"
              sm="6"
              md="4"
            >
              <VCard
                variant="outlined"
                class="pa-4"
              >
                <div class="d-flex align-center gap-2 mb-2">
                  <VIcon
                    icon="tabler-circle-filled"
                    size="12"
                    :color="balance.leave_type?.color || 'primary'"
                  />
                  <span class="text-subtitle-1 font-weight-medium">{{ balance.leave_type?.name }}</span>
                </div>
                <div class="text-h4">
                  {{ formatDays(balance.available_days) }}
                  <span class="text-body-2 hr-page__muted">/ {{ formatDays(balance.total_days) }}</span>
                </div>
                <VProgressLinear
                  class="my-2"
                  :model-value="percentUsed(balance)"
                  :color="balance.leave_type?.color || 'primary'"
                  rounded
                />
                <div class="d-flex justify-space-between text-caption hr-page__muted">
                  <span>{{ $t('hr.my_leave.used', { days: formatDays(balance.used_days) }) }}</span>
                  <span v-if="Number(balance.pending_days)">{{ $t('hr.leave.pending_short', { days: formatDays(balance.pending_days) }) }}</span>
                </div>
              </VCard>
            </VCol>
          </VRow>
          <div
            v-else
            class="hr-page__muted"
          >
            {{ $t('hr.leave.no_balance') }}
          </div>
        </VCardText>
      </VCard>

      <VCard class="mt-6">
        <VCardItem>
          <VCardTitle>{{ $t('hr.my_leave.history_title') }}</VCardTitle>
        </VCardItem>
        <HrLeaveRequestsTable
          ref="history"
          mode="self"
          :leave-types="overview.leave_types ?? []"
          can-cancel
          @changed="loadOverview"
        />
      </VCard>

      <HrLeaveRequestDialog
        v-model="requestOpen"
        mode="self"
        :leave-types="overview.leave_types ?? []"
        @saved="onChanged"
      />
    </template>
  </div>
</template>

<route lang="yaml">
meta:
  action: apply_hr-self-leave
  subject: apply_hr-self-leave
  pageTitleKey: hr.my_leave.title
  pageSubtitleKey: hr.my_leave.subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
