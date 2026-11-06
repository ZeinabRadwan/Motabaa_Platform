<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { useHrCrudTable } from '@/composables/useHrCrudTable'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrDepartmentsApi } from '@/plugins/apis/hrRequest'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

const { t } = useI18n()
const { lookups, refresh: refreshLookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-organization', 'edit_hr-organization'))
const canAdmin = computed(() => can('admin_hr-organization', 'admin_hr-organization'))

const emptyForm = () => ({
  code: '',
  name_ar: '',
  name_en: '',
  parent_id: null,
  sort_order: 0,
  is_active: true,
})

const {
  items, total, loading, search, showTrashed, options, updateOptions,
  isDialogVisible, editingId, form, saving, openDialog, openCreateDialog, save,
  confirm, askConfirm, runConfirmed,
} = useHrCrudTable(hrDepartmentsApi, {
  emptyForm,
  toForm: item => ({
    code: item.code,
    name_ar: item.name_ar,
    name_en: item.name_en ?? '',
    parent_id: item.parent_id,
    sort_order: item.sort_order ?? 0,
    is_active: item.is_active,
  }),
  toPayload: payload => ({ ...payload, sort_order: Number(payload.sort_order) || 0 }),
  onSaved: () => refreshLookups().catch(() => {}),
})

const parentOptions = computed(() => lookups.value.departments.filter(option => option.value !== editingId.value))

const headers = computed(() => [
  { title: t('hr.fields.code'), key: 'code' },
  { title: t('hr.fields.name'), key: 'name' },
  { title: t('hr.fields.parent_department'), key: 'parent_name', sortable: false },
  { title: t('hr.employees.title'), key: 'employees_count', sortable: false },
  { title: t('hr.fields.sort_order'), key: 'sort_order' },
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
          sm="5"
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
          sm="4"
          class="d-flex align-center"
        >
          <VSwitch
            v-if="canAdmin"
            v-model="showTrashed"
            :label="$t('hr.common.show_deleted')"
            hide-details
          />
        </VCol>
        <VCol
          v-if="canEdit"
          cols="12"
          sm="3"
          class="d-flex align-center justify-end"
        >
          <VBtn
            prepend-icon="tabler-plus"
            @click="openCreateDialog"
          >
            {{ $t('hr.organization.add_department') }}
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
      <template #item.parent_name="{ item }">
        {{ item.raw.parent_name || '—' }}
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
        <div class="d-flex gap-1">
          <template v-if="item.raw.deleted_at">
            <VBtn
              v-if="canAdmin"
              icon
              variant="text"
              size="small"
              @click="askConfirm('restore', item.raw)"
            >
              <VIcon icon="tabler-arrow-back-up" />
            </VBtn>
          </template>
          <template v-else>
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
          </template>
        </div>
      </template>
    </VDataTableServer>

    <VDialog
      v-model="isDialogVisible"
      max-width="640"
    >
      <VCard :title="$t(editingId ? 'hr.organization.edit_department' : 'hr.organization.add_department')">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.code"
                :label="$t('hr.fields.code')"
                :hint="$t('hr.fields.department_code_hint')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.parent_id"
                :items="parentOptions"
                :label="$t('hr.fields.parent_department')"
                clearable
                clear-icon="tabler-x"
              />
            </VCol>
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
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.sort_order"
                type="number"
                min="0"
                :label="$t('hr.fields.sort_order')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              class="d-flex align-center"
            >
              <VSwitch
                v-model="form.is_active"
                :label="$t('Active')"
                hide-details
              />
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
