<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrLeaveBalancesApi } from '@/plugins/apis/hrRequest'
import { formatHrDateTime } from '@/utils/hrFormat'
import { formatDays } from '@/utils/hrLeave'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

// Leave balances per employee and year: entitlement, carry-over, adjustments, used, pending (reserved by
// requests awaiting approval) and available days. Every change is a transaction; HR adjusts with a reason.

const props = defineProps({
  canAdjust: { type: Boolean, default: false },
})

const { t, locale } = useI18n()
const { lookups } = useHrLookups()

const filters = reactive({ year: new Date().getFullYear(), branchId: null, q: '' })

const yearItems = computed(() => {
  const current = new Date().getFullYear()

  return [current + 1, current, current - 1, current - 2].map(value => ({ value, title: String(value) }))
})

const items = ref([])
const total = ref(0)
const loading = ref(false)
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })

const fetchItems = () => {
  loading.value = true

  return hrLeaveBalancesApi.list({
    year: filters.year,
    branch_id: filters.branchId || undefined,
    q: filters.q || undefined,
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

watchServerTableFetch(fetchItems, {
  filters: () => [filters.year, filters.branchId, filters.q],
  options,
})

const headers = computed(() => [
  { title: t('hr.fields.employee'), key: 'employee', sortable: false },
  { title: t('hr.leave.balances'), key: 'balances', sortable: false },
  { title: t('Actions'), key: 'actions', sortable: false },
])

// ── Adjustment ──────────────────────────────────────────────────────────
const adjust = ref({ visible: false, row: null, leave_type_id: null, days: null, reason: '' })
const adjusting = ref(false)

const openAdjust = row => {
  adjust.value = { visible: true, row, leave_type_id: row.balances[0]?.leave_type?.id ?? null, days: null, reason: '' }
}

const adjustTypes = computed(() => (adjust.value.row?.balances ?? []).map(balance => ({
  value: balance.leave_type?.id,
  title: `${balance.leave_type?.name} (${t('hr.leave.available_short', { days: formatDays(balance.available_days) })})`,
})))

const canSaveAdjust = computed(() => adjust.value.leave_type_id && Number(adjust.value.days) !== 0 && !Number.isNaN(Number(adjust.value.days))
  && adjust.value.reason.trim().length >= 5)

const saveAdjust = () => {
  adjusting.value = true
  hrLeaveBalancesApi.adjust({
    employee_id: adjust.value.row.employee.id,
    leave_type_id: adjust.value.leave_type_id,
    year: filters.year,
    days: Number(adjust.value.days),
    reason: adjust.value.reason.trim(),
  }).then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    adjust.value.visible = false
    fetchItems()
  }).catch(() => {}).finally(() => {
    adjusting.value = false
  })
}

// ── Transactions ────────────────────────────────────────────────────────
const history = ref({ visible: false, loading: false, balance: null, transactions: [] })

const openHistory = balance => {
  if (!balance.id)
    return
  history.value = { visible: true, loading: true, balance, transactions: [] }
  hrLeaveBalancesApi.transactions(balance.id).then(({ data }) => {
    history.value.balance = data.data.balance
    history.value.transactions = data.data.transactions
  }).catch(() => {}).finally(() => {
    history.value.loading = false
  })
}

defineExpose({ refresh: fetchItems })
</script>

<template>
  <div>
    <VCardText>
      <VRow>
        <VCol
          cols="12"
          sm="5"
        >
          <AppTextField
            v-model="filters.q"
            :placeholder="$t('Search')"
            prepend-inner-icon="tabler-search"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
        >
          <AppSelect
            v-model="filters.branchId"
            :items="lookups.branches"
            :placeholder="$t('hr.fields.branch')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="3"
        >
          <AppSelect
            v-model="filters.year"
            :items="yearItems"
            :label="$t('hr.leave.year')"
          />
        </VCol>
      </VRow>
    </VCardText>
    <VDivider />

    <VDataTableServer
      v-model:items-per-page="options.itemsPerPage"
      v-model:page="options.page"
      :items="items"
      :items-length="total"
      :headers="headers"
      :loading="loading"
      @update:options="applyServerTableOptions(options, $event)"
    >
      <template #item.employee="{ item }">
        <div class="text-no-wrap">
          {{ item.raw.employee?.name || '—' }}
        </div>
        <div class="text-caption hr-page__muted">
          {{ item.raw.employee?.employee_number }}
        </div>
      </template>
      <template #item.balances="{ item }">
        <div class="d-flex flex-wrap gap-2 py-2">
          <VChip
            v-for="balance in item.raw.balances"
            :key="balance.leave_type?.id"
            size="small"
            label
            :color="balance.leave_type?.color || 'primary'"
            variant="tonal"
            @click="openHistory(balance)"
          >
            {{ balance.leave_type?.name }}:
            <strong class="ms-1">{{ formatDays(balance.available_days) }}</strong>
            <span class="ms-1">/ {{ formatDays(balance.total_days) }}</span>
            <span
              v-if="balance.pending_days > 0"
              class="ms-1"
            >({{ $t('hr.leave.pending_short', { days: formatDays(balance.pending_days) }) }})</span>
          </VChip>
        </div>
      </template>
      <template #item.actions="{ item }">
        <VBtn
          v-if="props.canAdjust && item.raw.balances.length"
          size="small"
          variant="tonal"
          prepend-icon="tabler-adjustments"
          @click="openAdjust(item.raw)"
        >
          {{ $t('hr.leave.adjust') }}
        </VBtn>
      </template>
    </VDataTableServer>
    <VCardText class="text-caption hr-page__muted">
      {{ $t('hr.leave.balances_hint') }}
    </VCardText>

    <VDialog
      v-model="adjust.visible"
      max-width="520"
    >
      <VCard
        v-if="adjust.row"
        :title="`${$t('hr.leave.adjust_balance')} — ${adjust.row.employee?.name}`"
      >
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppSelect
                v-model="adjust.leave_type_id"
                :items="adjustTypes"
                :label="$t('hr.leave.leave_type')"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="adjust.days"
                type="number"
                step="0.5"
                :label="$t('hr.leave.adjust_days')"
                :hint="$t('hr.leave.adjust_days_hint', { year: filters.year })"
                persistent-hint
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="adjust.reason"
                :label="$t('hr.leave.adjust_reason')"
                rows="2"
                maxlength="1000"
                counter
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="adjust.visible = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            :loading="adjusting"
            :disabled="!canSaveAdjust"
            @click="saveAdjust"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="history.visible"
      max-width="760"
    >
      <VCard :title="`${$t('hr.leave.transactions')} — ${history.balance?.leave_type?.name ?? ''} ${history.balance?.year ?? ''}`">
        <VCardText>
          <VProgressLinear
            v-if="history.loading"
            indeterminate
          />
          <VTable
            v-else
            density="compact"
          >
            <thead>
              <tr>
                <th>{{ $t('hr.audit.when') }}</th>
                <th>{{ $t('hr.leave.transaction') }}</th>
                <th>{{ $t('hr.leave.days') }}</th>
                <th>{{ $t('hr.leave.reason') }}</th>
                <th>{{ $t('hr.audit.by') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in history.transactions"
                :key="row.id"
              >
                <td class="text-no-wrap">
                  {{ formatHrDateTime(row.created_at, locale) }}
                </td>
                <td>{{ row.kind_label }}</td>
                <td dir="ltr">
                  {{ row.days > 0 ? '+' : '' }}{{ formatDays(row.days) }}
                </td>
                <td>{{ row.reason || '—' }}</td>
                <td>{{ row.created_by?.name || $t('hr.history.system') }}</td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="history.visible = false"
          >
            {{ $t('Close') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
