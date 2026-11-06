<script setup>
import { hrAttendancePoliciesApi } from '@/plugins/apis/hrRequest'
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

/** Employed staff whose attendance configuration uses the policy today or from a scheduled date. */
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  policy: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const { t } = useI18n()

const canOpenProfile = computed(() => can('access_hr-employees', 'access_hr-employees'))

const items = ref([])
const total = ref(0)
const loading = ref(false)
const search = ref('')
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [], groupBy: [] })

const fetchItems = () => {
  if (!props.modelValue || !props.policy)
    return Promise.resolve()
  loading.value = true

  return hrAttendancePoliciesApi.employees(props.policy.id, {
    q: search.value || undefined,
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
  filters: () => [props.policy?.id, props.modelValue],
  options,
})

const updateOptions = incoming => applyServerTableOptions(options, incoming)

const headers = computed(() => [
  { title: t('hr.fields.employee_number'), key: 'employee_number', sortable: false },
  { title: t('hr.fields.name'), key: 'name', sortable: false },
  { title: t('hr.fields.branch'), key: 'branch', sortable: false },
  { title: t('hr.fields.attendance_mode'), key: 'attendance_mode_label', sortable: false },
  { title: t('hr.fields.effective_from'), key: 'effective_from', sortable: false },
  { title: t('hr.fields.effective_to'), key: 'effective_to', sortable: false },
])
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="960"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard
      v-if="policy"
      :title="$t('hr.policies.employees_title', { policy: policy.name })"
      :subtitle="$t('hr.policies.employees_hint')"
    >
      <VCardText>
        <AppTextField
          v-model="search"
          :placeholder="$t('Search')"
          prepend-inner-icon="tabler-search"
          clearable
          clear-icon="tabler-x"
        />
      </VCardText>
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
        <template #item.employee_number="{ item }">
          <span dir="ltr">{{ item.raw.employee_number }}</span>
        </template>
        <template #item.name="{ item }">
          <RouterLink
            v-if="canOpenProfile"
            :to="{ name: 'hr-employees-view-id', params: { id: item.raw.employee_id }, query: { tab: 'attendance' } }"
          >
            {{ item.raw.name }}
          </RouterLink>
          <span v-else>{{ item.raw.name }}</span>
        </template>
        <template #item.branch="{ item }">
          {{ item.raw.branch || '—' }}
        </template>
        <template #item.effective_from="{ item }">
          <span dir="ltr">{{ item.raw.effective_from }}</span>
          <VChip
            v-if="item.raw.is_scheduled"
            size="x-small"
            label
            color="info"
            class="ms-1"
          >
            {{ $t('hr.records.scheduled') }}
          </VChip>
        </template>
        <template #item.effective_to="{ item }">
          <span dir="ltr">{{ item.raw.effective_to || '—' }}</span>
        </template>
      </VDataTableServer>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="emit('update:modelValue', false)"
        >
          {{ $t('Close') }}
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
