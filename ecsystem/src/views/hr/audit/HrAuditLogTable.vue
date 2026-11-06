<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrAuditLogsApi } from '@/plugins/apis/hrRequest'
import { formatHrDateTime } from '@/utils/hrFormat'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

const EVENTS = ['created', 'updated', 'deleted', 'restored']
const EVENT_COLORS = { created: 'success', updated: 'info', deleted: 'error', restored: 'warning' }

const { t, locale } = useI18n()
const route = useRoute()
const { lookups } = useHrLookups()

const auditableType = ref(route.query.auditable_type || null)
const auditableId = ref(route.query.auditable_id || '')
const event = ref(null)
const dateFrom = ref(null)
const dateTo = ref(null)

const items = ref([])
const total = ref(0)
const loading = ref(false)
const expanded = ref([])
const options = ref({ page: 1, itemsPerPage: 25, sortBy: [], groupBy: [] })

const eventItems = computed(() => EVENTS.map(value => ({ value, title: t(`hr.audit.events.${value}`) })))

const fetchLogs = () => {
  loading.value = true

  return hrAuditLogsApi.list({
    auditable_type: auditableType.value || undefined,
    auditable_id: auditableId.value || undefined,
    event: event.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
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

watchServerTableFetch(fetchLogs, {
  search: auditableId,
  filters: () => [auditableType.value, event.value, dateFrom.value, dateTo.value],
  options,
})

const headers = computed(() => [
  { title: '', key: 'data-table-expand', sortable: false },
  { title: t('hr.audit.when'), key: 'created_at', sortable: false },
  { title: t('hr.audit.record'), key: 'auditable', sortable: false },
  { title: t('hr.audit.event'), key: 'event', sortable: false },
  { title: t('hr.audit.by'), key: 'user', sortable: false },
  { title: t('hr.audit.ip'), key: 'ip_address', sortable: false },
])

const changedKeys = log => [...new Set([...Object.keys(log.old_values ?? {}), ...Object.keys(log.new_values ?? {})])]

const display = value => {
  if (value === null || value === undefined || value === '')
    return '—'
  if (typeof value === 'object')
    return JSON.stringify(value)

  return String(value)
}
</script>

<template>
  <VCard>
    <VCardText>
      <VRow>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <AppSelect
            v-model="auditableType"
            :items="lookups.auditable_types"
            :placeholder="$t('hr.audit.record_type')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="2"
        >
          <AppTextField
            v-model="auditableId"
            type="number"
            min="1"
            :placeholder="$t('hr.audit.record_id')"
            dir="ltr"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
          md="2"
        >
          <AppSelect
            v-model="event"
            :items="eventItems"
            :placeholder="$t('hr.audit.event')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
          md="2"
        >
          <AppDateTimePicker
            v-model="dateFrom"
            :placeholder="$t('from')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="4"
          md="3"
        >
          <AppDateTimePicker
            v-model="dateTo"
            :placeholder="$t('to')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
      </VRow>
    </VCardText>

    <VDivider />

    <VDataTableServer
      v-model:items-per-page="options.itemsPerPage"
      v-model:page="options.page"
      v-model:expanded="expanded"
      :items="items"
      :items-length="total"
      :headers="headers"
      :loading="loading"
      item-value="id"
      show-expand
      class="text-no-wrap"
      @update:options="applyServerTableOptions(options, $event)"
    >
      <template #item.created_at="{ item }">
        {{ formatHrDateTime(item.raw.created_at, locale) }}
      </template>
      <template #item.auditable="{ item }">
        {{ item.raw.auditable_type_label }} <span dir="ltr">#{{ item.raw.auditable_id }}</span>
      </template>
      <template #item.event="{ item }">
        <VChip
          size="small"
          label
          :color="EVENT_COLORS[item.raw.event] || 'secondary'"
        >
          {{ item.raw.event_label }}
        </VChip>
      </template>
      <template #item.user="{ item }">
        <span>{{ item.raw.user?.name || '—' }}</span>
        <VChip
          v-if="item.raw.via_impersonation"
          size="x-small"
          color="warning"
          label
          class="ms-2"
        >
          {{ $t('hr.audit.via_impersonation') }}
        </VChip>
      </template>
      <template #item.ip_address="{ item }">
        <span dir="ltr">{{ item.raw.ip_address || '—' }}</span>
      </template>
      <template #expanded-row="{ columns, item }">
        <tr>
          <td :colspan="columns.length">
            <div
              v-if="changedKeys(item.raw).length"
              class="hr-diff"
            >
              <span class="hr-diff__head">{{ $t('hr.audit.field') }}</span>
              <span class="hr-diff__head">{{ $t('hr.audit.old_value') }}</span>
              <span class="hr-diff__head">{{ $t('hr.audit.new_value') }}</span>
              <template
                v-for="key in changedKeys(item.raw)"
                :key="key"
              >
                <span dir="ltr">{{ key }}</span>
                <span class="hr-diff__old">{{ display(item.raw.old_values?.[key]) }}</span>
                <span class="hr-diff__new">{{ display(item.raw.new_values?.[key]) }}</span>
              </template>
            </div>
            <div
              v-else
              class="pa-4 hr-page__muted"
            >
              {{ $t('hr.audit.no_changes') }}
            </div>
          </td>
        </tr>
      </template>
    </VDataTableServer>
  </VCard>
</template>
