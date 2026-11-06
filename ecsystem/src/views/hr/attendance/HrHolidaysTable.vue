<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { useHrCrudTable } from '@/composables/useHrCrudTable'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrHolidaysApi } from '@/plugins/apis/hrRequest'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

// Official and company holidays, for the whole center or one branch. Holiday days are never counted as absence
// nor deducted from leave balances; changing a holiday reprocesses the attendance of the affected days.
// Deleting requires admin_hr-attendance-setup.

const emit = defineEmits(['changed'])

const { t } = useI18n()
const { lookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-attendance-setup', 'edit_hr-attendance-setup'))
const canAdmin = computed(() => can('admin_hr-attendance-setup', 'admin_hr-attendance-setup'))

const yearFilter = ref(new Date().getFullYear())
const typeFilter = ref(null)
const branchFilter = ref(null)

const yearItems = computed(() => {
  const current = new Date().getFullYear()

  return [current + 1, current, current - 1, current - 2].map(value => ({ value, title: String(value) }))
})

const emptyForm = () => ({ name_ar: '', name_en: '', branch_id: null, date_from: null, date_to: null, holiday_type: 'official', is_paid: true, notes: '' })

const {
  items, total, loading, search, options, updateOptions,
  isDialogVisible, editingId, form, saving, openDialog, openCreateDialog, save,
  confirm, askConfirm, runConfirmed,
} = useHrCrudTable(hrHolidaysApi, {
  filters: () => ({ year: yearFilter.value || undefined, holiday_type: typeFilter.value || undefined, branch_id: branchFilter.value || undefined }),
  emptyForm,
  toForm: item => ({
    name_ar: item.name_ar,
    name_en: item.name_en ?? '',
    branch_id: item.branch_id,
    date_from: item.date_from,
    date_to: item.date_to,
    holiday_type: item.holiday_type,
    is_paid: item.is_paid,
    notes: item.notes ?? '',
  }),
  toPayload: payload => ({ ...payload, branch_id: payload.branch_id || null, date_to: payload.date_to || payload.date_from }),
  onSaved: () => emit('changed'),
})

const headers = computed(() => [
  { title: t('hr.fields.name'), key: 'name', sortable: false },
  { title: t('hr.holidays.dates'), key: 'date_from', sortable: false },
  { title: t('hr.holidays.type'), key: 'holiday_type', sortable: false },
  { title: t('hr.holidays.applies_to'), key: 'branch', sortable: false },
  { title: t('hr.leave.paid'), key: 'is_paid', sortable: false },
  { title: t('Actions'), key: 'actions', sortable: false },
])
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
          cols="6"
          md="2"
        >
          <AppSelect
            v-model="yearFilter"
            :items="yearItems"
            :placeholder="$t('hr.leave.year')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="6"
          md="2"
        >
          <AppSelect
            v-model="typeFilter"
            :items="lookups.holiday_types"
            :placeholder="$t('hr.holidays.type')"
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
            v-model="branchFilter"
            :items="lookups.branches"
            :placeholder="$t('hr.fields.branch')"
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
            prepend-icon="tabler-plus"
            @click="openCreateDialog"
          >
            {{ $t('hr.holidays.add') }}
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
      <template #item.name="{ item }">
        <div class="font-weight-medium">
          {{ item.raw.name }}
        </div>
        <div
          v-if="item.raw.notes"
          class="text-caption hr-page__muted"
        >
          {{ item.raw.notes }}
        </div>
      </template>
      <template #item.date_from="{ item }">
        <span dir="ltr">{{ item.raw.date_from }}<template v-if="item.raw.date_to !== item.raw.date_from"> → {{ item.raw.date_to }}</template></span>
        <div class="hr-page__muted">
          {{ $t('hr.exceptions.days_count', item.raw.days) }}
        </div>
      </template>
      <template #item.holiday_type="{ item }">
        <VChip
          size="small"
          label
          :color="item.raw.holiday_type === 'official' ? 'primary' : 'info'"
        >
          {{ item.raw.holiday_type_label }}
        </VChip>
      </template>
      <template #item.branch="{ item }">
        {{ item.raw.branch_name || $t('hr.holidays.all_branches') }}
      </template>
      <template #item.is_paid="{ item }">
        {{ $t(item.raw.is_paid ? 'hr.leave.paid_yes' : 'hr.leave.paid_no') }}
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <VBtn
            v-if="canEdit"
            icon
            variant="text"
            size="small"
            @click="openDialog(item.raw)"
          >
            <VIcon icon="tabler-edit" />
          </VBtn>
          <VBtn
            v-if="canAdmin"
            icon
            variant="text"
            size="small"
            color="error"
            @click="askConfirm('delete', item.raw)"
          >
            <VIcon icon="tabler-trash" />
          </VBtn>
        </div>
      </template>
    </VDataTableServer>

    <VDialog
      v-model="isDialogVisible"
      max-width="640"
    >
      <VCard :title="$t(editingId ? 'hr.holidays.edit' : 'hr.holidays.add')">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.name_ar"
                :label="$t('hr.fields.name_ar')"
                dir="rtl"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.name_en"
                :label="$t('hr.fields.name_en')"
                dir="ltr"
              />
            </VCol>
            <VCol cols="6">
              <AppDateTimePicker
                v-model="form.date_from"
                :label="$t('hr.holidays.date_from')"
              />
            </VCol>
            <VCol cols="6">
              <AppDateTimePicker
                v-model="form.date_to"
                :label="$t('hr.holidays.date_to')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.holiday_type"
                :items="lookups.holiday_types"
                :label="$t('hr.holidays.type')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.branch_id"
                :items="lookups.branches"
                :label="$t('hr.holidays.applies_to')"
                :placeholder="$t('hr.holidays.all_branches')"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>
            <VCol cols="12">
              <VSwitch
                v-model="form.is_paid"
                :label="$t('hr.leave.paid')"
                hide-details
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.notes"
                :label="$t('hr.fields.notes')"
                rows="2"
              />
            </VCol>
          </VRow>
          <VAlert
            type="info"
            variant="tonal"
            density="compact"
            class="mt-2"
          >
            {{ $t('hr.holidays.hint') }}
          </VAlert>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="tonal"
            color="secondary"
            @click="isDialogVisible = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            :loading="saving"
            @click="save"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <HrConfirmDialog
      v-model="confirm.visible"
      :action="confirm.action"
      @confirm="runConfirmed"
    />
  </div>
</template>
