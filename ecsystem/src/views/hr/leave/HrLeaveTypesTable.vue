<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { useHrCrudTable } from '@/composables/useHrCrudTable'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrLeaveTypesApi } from '@/plugins/apis/hrRequest'
import { formatDays } from '@/utils/hrLeave'
import { SHIFT_COLOR_PRESETS } from '@/utils/hrShifts'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

// Configurable leave types. Built-in types (annual, sick, unpaid, ...) keep their code and cannot be deleted,
// but their rules can be changed or the type deactivated. Managing types requires admin_hr-leave.

const { t } = useI18n()
const { lookups, refresh: refreshLookups } = useHrLookups()

const canAdmin = computed(() => can('admin_hr-leave', 'admin_hr-leave'))
const typeOptions = computed(() => lookups.value.leave_type_options ?? {})

const emptyForm = () => ({
  code: '',
  name_ar: '',
  name_en: '',
  category: 'other',
  is_paid: true,
  balance_mode: 'none',
  days_per_year: null,
  carry_over_max_days: 0,
  max_days_per_request: null,
  day_counting: 'working_days',
  requires_attachment: false,
  requires_hr_approval: true,
  allow_self_request: true,
  color: SHIFT_COLOR_PRESETS[0],
  sort_order: 0,
  is_active: true,
})

const {
  items, total, loading, search, options, updateOptions,
  isDialogVisible, editingId, form, saving, openDialog, openCreateDialog, save,
  confirm, askConfirm, runConfirmed,
} = useHrCrudTable(hrLeaveTypesApi, {
  emptyForm,
  toForm: item => ({
    code: item.code,
    name_ar: item.name_ar,
    name_en: item.name_en ?? '',
    category: item.category,
    is_paid: item.is_paid,
    balance_mode: item.balance_mode,
    days_per_year: item.days_per_year,
    carry_over_max_days: item.carry_over_max_days ?? 0,
    max_days_per_request: item.max_days_per_request,
    day_counting: item.day_counting,
    requires_attachment: item.requires_attachment,
    requires_hr_approval: item.requires_hr_approval,
    allow_self_request: item.allow_self_request,
    color: item.color || SHIFT_COLOR_PRESETS[0],
    sort_order: item.sort_order ?? 0,
    is_active: item.is_active,
  }),
  toPayload: payload => ({
    ...payload,
    days_per_year: payload.balance_mode === 'fixed' ? Number(payload.days_per_year) || 0 : null,
    carry_over_max_days: Number(payload.carry_over_max_days) || 0,
    max_days_per_request: payload.max_days_per_request ? Number(payload.max_days_per_request) : null,
    sort_order: Number(payload.sort_order) || 0,
  }),
  onSaved: () => refreshLookups().catch(() => {}),
})

const editingItem = computed(() => items.value.find(item => item.id === editingId.value) ?? null)

const headers = computed(() => [
  { title: t('hr.fields.code'), key: 'code' },
  { title: t('hr.fields.name'), key: 'name', sortable: false },
  { title: t('hr.leave.balance'), key: 'balance_mode', sortable: false },
  { title: t('hr.leave.day_counting'), key: 'day_counting', sortable: false },
  { title: t('hr.leave.rules'), key: 'rules', sortable: false },
  { title: t('Status'), key: 'is_active', sortable: false },
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
          v-if="canAdmin"
          cols="12"
          sm="6"
          class="d-flex align-center justify-end"
        >
          <VBtn
            prepend-icon="tabler-plus"
            @click="openCreateDialog"
          >
            {{ $t('hr.leave.add_type') }}
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
      <template #item.code="{ item }">
        <span dir="ltr">{{ item.raw.code }}</span>
      </template>
      <template #item.name="{ item }">
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-circle-filled"
            size="12"
            :color="item.raw.color || 'primary'"
          />
          <span>{{ item.raw.name }}</span>
          <VChip
            v-if="item.raw.is_system"
            size="x-small"
            label
          >
            {{ $t('hr.leave.system') }}
          </VChip>
        </div>
        <div class="text-caption hr-page__muted">
          {{ item.raw.category_label }} · {{ $t(item.raw.is_paid ? 'hr.leave.paid_yes' : 'hr.leave.paid_no') }}
        </div>
      </template>
      <template #item.balance_mode="{ item }">
        {{ item.raw.balance_mode_label }}
        <span v-if="item.raw.balance_mode === 'fixed'">({{ formatDays(item.raw.days_per_year) }})</span>
      </template>
      <template #item.day_counting="{ item }">
        {{ item.raw.day_counting_label }}
      </template>
      <template #item.rules="{ item }">
        <div class="d-flex flex-wrap gap-1">
          <VChip
            v-if="item.raw.max_days_per_request"
            size="x-small"
            label
          >
            {{ $t('hr.leave.max_days_short', { days: item.raw.max_days_per_request }) }}
          </VChip>
          <VChip
            v-if="item.raw.requires_attachment"
            size="x-small"
            label
          >
            {{ $t('hr.leave.requires_attachment') }}
          </VChip>
          <VChip
            v-if="item.raw.requires_hr_approval"
            size="x-small"
            label
          >
            {{ $t('hr.leave.requires_hr_approval') }}
          </VChip>
          <VChip
            v-if="!item.raw.allow_self_request"
            size="x-small"
            label
            color="warning"
          >
            {{ $t('hr.leave.hr_only') }}
          </VChip>
        </div>
      </template>
      <template #item.is_active="{ item }">
        <VChip
          size="small"
          label
          :color="item.raw.is_active ? 'success' : 'secondary'"
        >
          {{ $t(item.raw.is_active ? 'Active' : 'Inactive') }}
        </VChip>
      </template>
      <template #item.actions="{ item }">
        <div
          v-if="canAdmin"
          class="d-flex gap-1"
        >
          <VBtn
            icon
            variant="text"
            size="small"
            @click="openDialog(item.raw)"
          >
            <VIcon icon="tabler-edit" />
          </VBtn>
          <VBtn
            v-if="!item.raw.is_system"
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
      max-width="760"
    >
      <VCard :title="$t(editingId ? 'hr.leave.edit_type' : 'hr.leave.add_type')">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.code"
                :label="$t('hr.fields.code')"
                :hint="$t('hr.fields.department_code_hint')"
                :disabled="!!editingItem?.is_system"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.name_ar"
                :label="$t('hr.fields.name_ar')"
                dir="rtl"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.name_en"
                :label="$t('hr.fields.name_en')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppSelect
                v-model="form.category"
                :items="typeOptions.categories ?? []"
                :label="$t('hr.leave.category')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppSelect
                v-model="form.balance_mode"
                :items="typeOptions.balance_modes ?? []"
                :label="$t('hr.leave.balance')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppSelect
                v-model="form.day_counting"
                :items="typeOptions.day_countings ?? []"
                :label="$t('hr.leave.day_counting')"
              />
            </VCol>
            <VCol
              v-if="form.balance_mode === 'fixed'"
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.days_per_year"
                type="number"
                min="0"
                :label="$t('hr.leave.days_per_year')"
              />
            </VCol>
            <VCol
              v-if="form.balance_mode !== 'none'"
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.carry_over_max_days"
                type="number"
                min="0"
                :label="$t('hr.leave.carry_over_max_days')"
                :hint="$t('hr.leave.carry_over_hint')"
                persistent-hint
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.max_days_per_request"
                type="number"
                min="1"
                :label="$t('hr.leave.max_days_per_request')"
                clearable
              />
            </VCol>
            <VCol
              v-if="form.balance_mode === 'annual'"
              cols="12"
            >
              <VAlert
                type="info"
                variant="tonal"
                density="compact"
              >
                {{ $t('hr.leave.annual_mode_hint') }}
              </VAlert>
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VSwitch
                v-model="form.is_paid"
                :label="$t('hr.leave.paid')"
                hide-details
              />
              <VSwitch
                v-model="form.requires_attachment"
                :label="$t('hr.leave.requires_attachment')"
                hide-details
              />
              <VSwitch
                v-model="form.requires_hr_approval"
                :label="$t('hr.leave.requires_hr_approval')"
                hide-details
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VSwitch
                v-model="form.allow_self_request"
                :label="$t('hr.leave.allow_self_request')"
                hide-details
              />
              <VSwitch
                v-model="form.is_active"
                :label="$t('Active')"
                hide-details
              />
              <div class="d-flex align-center gap-2 mt-3">
                <span class="hr-page__muted">{{ $t('hr.leave.color') }}</span>
                <VBtn
                  v-for="color in SHIFT_COLOR_PRESETS"
                  :key="color"
                  icon
                  size="x-small"
                  variant="flat"
                  :color="color"
                  @click="form.color = color"
                >
                  <VIcon
                    v-if="form.color === color"
                    icon="tabler-check"
                    size="14"
                  />
                </VBtn>
              </div>
            </VCol>
          </VRow>
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
