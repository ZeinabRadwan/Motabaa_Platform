<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrShiftExceptionsApi } from '@/plugins/apis/hrRequest'
import { shiftColor } from '@/utils/hrShifts'
import HrShiftExceptionDialog from '@/views/hr/attendance/HrShiftExceptionDialog.vue'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

const emit = defineEmits(['changed'])

const { t } = useI18n()
const { lookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-attendance-setup', 'edit_hr-attendance-setup'))
const allBranches = computed(() => can('access_hr-all-branches', 'access_hr-all-branches'))

const items = ref([])
const total = ref(0)
const loading = ref(false)
const search = ref('')
const periodFilter = ref('upcoming')
const typeFilter = ref(null)
const scopeFilter = ref(null)
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })

const periodItems = computed(() => [
  { value: 'upcoming', title: t('hr.exceptions.period.upcoming') },
  { value: 'past', title: t('hr.exceptions.period.past') },
])

const scopeItems = computed(() => ['employee', 'branch', 'center'].map(value => ({ value, title: t(`hr.exceptions.scope.${value}`) })))

const fetchItems = () => {
  loading.value = true

  return hrShiftExceptionsApi.list({
    q: search.value || undefined,
    period: periodFilter.value || undefined,
    exception_type: typeFilter.value || undefined,
    scope: scopeFilter.value || undefined,
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
  search,
  filters: () => [periodFilter.value, typeFilter.value, scopeFilter.value],
  options,
})

const updateOptions = incoming => applyServerTableOptions(options, incoming)

const isDialogVisible = ref(false)
const cancelTarget = ref(null)

const afterChange = () => {
  fetchItems()
  emit('changed')
}

const canManage = item => canEdit.value && !item.is_past && (item.scope !== 'center' || allBranches.value)

const confirmCancel = () => {
  const item = cancelTarget.value

  cancelTarget.value = null
  if (!item)
    return
  hrShiftExceptionsApi.cancel(item.id).then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    afterChange()
  }).catch(() => {})
}

const target = item => {
  if (item.scope === 'employee')
    return item.employee_name
  if (item.scope === 'branch')
    return item.branch_name

  return t('hr.exceptions.scope.center')
}

const TYPE_ICONS = { day_off: 'tabler-beach', shift: 'tabler-switch-horizontal', custom_hours: 'tabler-clock-edit' }

const headers = computed(() => [
  { title: t('hr.exceptions.dates'), key: 'date_from' },
  { title: t('hr.exceptions.applies_to'), key: 'target', sortable: false },
  { title: t('hr.exceptions.type'), key: 'exception_type' },
  { title: t('hr.attendance_setup.time_range'), key: 'time_range', sortable: false },
  { title: t('hr.exceptions.reason'), key: 'reason', sortable: false },
  { title: t('hr.exceptions.created_by'), key: 'created_by', sortable: false },
  { title: t('Actions'), key: 'actions', sortable: false },
])

defineExpose({ refresh: fetchItems })
</script>

<template>
  <div>
    <VCardText>
      <VRow>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <AppTextField
            v-model="search"
            :placeholder="$t('Search')"
            prepend-inner-icon="tabler-search"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="2"
        >
          <AppSelect
            v-model="periodFilter"
            :items="periodItems"
            :placeholder="$t('hr.exceptions.period.all')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="2"
        >
          <AppSelect
            v-model="typeFilter"
            :items="lookups.shift_exception_types"
            :placeholder="$t('hr.exceptions.type')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="2"
        >
          <AppSelect
            v-model="scopeFilter"
            :items="scopeItems"
            :placeholder="$t('hr.exceptions.applies_to')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          v-if="canEdit"
          cols="12"
          md="3"
          class="d-flex align-center justify-end"
        >
          <VBtn
            prepend-icon="tabler-calendar-plus"
            @click="isDialogVisible = true"
          >
            {{ $t('hr.exceptions.add') }}
          </VBtn>
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
      class="text-no-wrap"
      @update:options="updateOptions"
    >
      <template #item.date_from="{ item }">
        <span dir="ltr">{{ item.raw.date_from }}<template v-if="item.raw.date_to !== item.raw.date_from"> → {{ item.raw.date_to }}</template></span>
        <div class="hr-page__muted">
          {{ $t('hr.exceptions.days_count', item.raw.days) }}
        </div>
      </template>
      <template #item.target="{ item }">
        <div class="d-flex align-center gap-2">
          <VIcon
            :icon="item.raw.scope === 'employee' ? 'tabler-user' : item.raw.scope === 'branch' ? 'tabler-building' : 'tabler-building-community'"
            size="18"
          />
          <div>
            <div>{{ target(item.raw) }}</div>
            <div
              v-if="item.raw.employee_number"
              class="hr-page__muted"
              dir="ltr"
            >
              {{ item.raw.employee_number }}
            </div>
          </div>
        </div>
      </template>
      <template #item.exception_type="{ item }">
        <VChip
          size="small"
          label
          :prepend-icon="TYPE_ICONS[item.raw.exception_type]"
          :color="item.raw.exception_type === 'day_off' ? 'warning' : 'info'"
        >
          {{ item.raw.exception_type_label }}
        </VChip>
      </template>
      <template #item.time_range="{ item }">
        <div
          v-if="item.raw.exception_type === 'shift'"
          class="d-flex align-center gap-2"
        >
          <span
            class="hr-shift-swatch hr-shift-swatch--inline"
            :style="{ backgroundColor: shiftColor(item.raw.shift_color, null) }"
          />
          {{ item.raw.shift_name }} <span dir="ltr">({{ item.raw.time_range }})</span>
        </div>
        <span
          v-else-if="item.raw.time_range"
          dir="ltr"
        >{{ item.raw.time_range }}</span>
        <span v-else>—</span>
      </template>
      <template #item.reason="{ item }">
        {{ item.raw.reason || '—' }}
      </template>
      <template #item.created_by="{ item }">
        {{ item.raw.created_by || '—' }}
      </template>
      <template #item.actions="{ item }">
        <VBtn
          v-if="canManage(item.raw)"
          icon
          variant="text"
          size="small"
          color="error"
          @click="cancelTarget = item.raw"
        >
          <VIcon icon="tabler-calendar-x" />
          <VTooltip activator="parent">
            {{ $t('hr.exceptions.cancel') }}
          </VTooltip>
        </VBtn>
      </template>
    </VDataTableServer>

    <HrShiftExceptionDialog
      v-model="isDialogVisible"
      @saved="afterChange"
    />
    <HrConfirmDialog
      :model-value="!!cancelTarget"
      :message="$t('hr.exceptions.confirm_cancel')"
      :confirm-label="$t('hr.exceptions.cancel')"
      @update:model-value="cancelTarget = null"
      @confirm="confirmCancel"
    />
  </div>
</template>
