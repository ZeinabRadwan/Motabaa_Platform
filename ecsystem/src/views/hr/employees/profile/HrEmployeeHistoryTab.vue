<script setup>
import { useHrCrudTable } from '@/composables/useHrCrudTable'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

const props = defineProps({
  employee: { type: Object, required: true },
})

const { t } = useI18n()
const { lookups } = useHrLookups()

const FINANCIAL_TYPES = ['salary', 'allowances', 'insurance']

const changeTypes = ref([])

const typeOptions = computed(() => lookups.value.change_types.filter(option => props.employee.can_view_compensation || !FINANCIAL_TYPES.includes(option.value)))

const { items, total, loading, options, updateOptions, fetchItems } = useHrCrudTable({
  list: params => hrEmployeesApi.history(props.employee.id, params),
}, {
  filters: () => ({ change_types: changeTypes.value.length ? changeTypes.value : undefined }),
  emptyForm: () => ({}),
  toForm: () => ({}),
})

defineExpose({ refresh: fetchItems })

const typeColor = type => ({
  salary: 'primary',
  allowances: 'primary',
  insurance: 'primary',
  branch: 'info',
  department: 'info',
  work_location: 'info',
  manager: 'info',
  shift: 'warning',
  attendance_method: 'warning',
  gps_policy: 'warning',
  grace_period: 'warning',
  attendance_policy: 'warning',
  hired: 'success',
  rehired: 'success',
  terminated: 'error',
})[type] ?? 'secondary'

const headers = computed(() => [
  { title: t('hr.fields.effective_date'), key: 'effective_date', sortable: false },
  { title: t('hr.history.change_type'), key: 'change_type_label', sortable: false },
  { title: t('hr.history.from'), key: 'old_display', sortable: false },
  { title: t('hr.history.to'), key: 'new_display', sortable: false },
  { title: t('hr.fields.change_reason'), key: 'reason', sortable: false },
  { title: t('hr.history.changed_by'), key: 'changed_by', sortable: false },
])
</script>

<template>
  <VCard>
    <VCardText>
      <VRow>
        <VCol
          cols="12"
          md="6"
        >
          <AppSelect
            v-model="changeTypes"
            :items="typeOptions"
            :placeholder="$t('hr.history.all_types')"
            multiple
            chips
            closable-chips
            clearable
          />
        </VCol>
        <VCol
          cols="12"
          md="6"
          class="d-flex align-center hr-page__muted"
        >
          {{ $t('hr.history.hint') }}
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
      @update:options="updateOptions"
    >
      <template #item.effective_date="{ item }">
        <span
          dir="ltr"
          class="text-no-wrap"
        >{{ item.raw.effective_date }}</span>
      </template>
      <template #item.change_type_label="{ item }">
        <VChip
          size="small"
          label
          :color="typeColor(item.raw.change_type)"
        >
          {{ item.raw.change_type_label }}
        </VChip>
        <VChip
          v-if="item.raw.action === 'cancelled'"
          size="x-small"
          label
          color="error"
          class="ms-1"
        >
          {{ $t('hr.history.cancelled') }}
        </VChip>
      </template>
      <template #item.old_display="{ item }">
        {{ item.raw.old_display ?? '—' }}
      </template>
      <template #item.new_display="{ item }">
        {{ item.raw.new_display ?? '—' }}
      </template>
      <template #item.reason="{ item }">
        {{ item.raw.reason || '—' }}
      </template>
      <template #item.changed_by="{ item }">
        <div>{{ item.raw.changed_by || $t('hr.history.system') }}</div>
        <div
          class="hr-page__muted text-caption"
          dir="ltr"
        >
          {{ String(item.raw.created_at || '').slice(0, 16).replace('T', ' ') }}
        </div>
      </template>
    </VDataTableServer>
  </VCard>
</template>
