<script setup>
import HrConfirmDialog from '@/components/hr/HrConfirmDialog.vue'
import { useHrCrudTable } from '@/composables/useHrCrudTable'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrEmployeeDocumentsApi } from '@/plugins/apis/hrRequest'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'
import { VDataTableServer } from 'vuetify/labs/VDataTable'

const props = defineProps({
  employee: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const { t } = useI18n()
const { lookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-documents', 'edit_hr-documents'))
const canAdmin = computed(() => can('admin_hr-documents', 'admin_hr-documents'))

const MAX_UPLOAD_MB = 10
const ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx'

const category = ref(null)

const api = {
  list: params => hrEmployeeDocumentsApi.list(props.employee.id, params),
  create: fields => hrEmployeeDocumentsApi.upload(props.employee.id, fields),
  update: (id, data) => hrEmployeeDocumentsApi.update(props.employee.id, id, data),
  remove: id => hrEmployeeDocumentsApi.remove(props.employee.id, id),
  restore: () => Promise.resolve(),
}

const emptyForm = () => ({
  file: null,
  category: category.value ?? 'document',
  document_type: null,
  title: '',
  reference_number: '',
  issue_date: null,
  expiry_date: null,
  notes: '',
})

const {
  items, total, loading, search, options, updateOptions,
  isDialogVisible, editingId, form, saving, openDialog, openCreateDialog, save,
  confirm, askConfirm, runConfirmed,
} = useHrCrudTable(api, {
  filters: () => ({ category: category.value || undefined }),
  emptyForm,
  toForm: item => ({
    category: item.category,
    document_type: item.document_type,
    title: item.title,
    reference_number: item.reference_number ?? '',
    issue_date: item.issue_date,
    expiry_date: item.expiry_date,
    notes: item.notes ?? '',
  }),
  toPayload: payload => {
    if (editingId.value)
      delete payload.file

    return payload
  },
  onSaved: () => emit('changed'),
})

const typeOptions = computed(() => lookups.value.document_types.filter(option => option.categories.includes(form.value.category)))

watch(() => form.value.category, () => {
  if (form.value.document_type && !typeOptions.value.some(option => option.value === form.value.document_type))
    form.value.document_type = null
})

const fileError = computed(() => {
  const file = form.value.file

  return file && file.size > MAX_UPLOAD_MB * 1024 * 1024 ? [t('hr.documents.file_too_large', { size: MAX_UPLOAD_MB })] : []
})

const canSave = computed(() => !fileError.value.length && (editingId.value || form.value.file))

const onFileChange = files => {
  form.value.file = Array.isArray(files) ? (files[0] ?? null) : files
}

const formatSize = bytes => {
  if (!bytes)
    return '—'
  if (bytes < 1024 * 1024)
    return `${Math.ceil(bytes / 1024)} KB`

  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

const download = item => {
  hrEmployeeDocumentsApi.download(props.employee.id, item.id).then(response => {
    const url = URL.createObjectURL(new Blob([response.data], { type: item.mime_type || response.data.type }))
    const link = document.createElement('a')

    link.href = url
    link.download = item.original_name || item.title
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  }).catch(() => {})
}

const headers = computed(() => [
  { title: t('hr.documents.title_field'), key: 'title' },
  { title: t('hr.documents.category'), key: 'category_label', sortable: false },
  { title: t('hr.documents.type'), key: 'document_type_label', sortable: false },
  { title: t('hr.documents.reference_number'), key: 'reference_number', sortable: false },
  { title: t('hr.documents.expiry_date'), key: 'expiry_date' },
  { title: t('hr.documents.size'), key: 'size', sortable: false },
  { title: t('hr.documents.uploaded_at'), key: 'created_at' },
  { title: t('Actions'), key: 'actions', sortable: false },
])
</script>

<template>
  <VCard>
    <VCardText>
      <VRow>
        <VCol
          cols="12"
          sm="4"
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
        >
          <AppSelect
            v-model="category"
            :items="lookups.document_categories"
            :placeholder="$t('hr.documents.all_categories')"
            clearable
          />
        </VCol>
        <VCol
          v-if="canEdit"
          cols="12"
          sm="4"
          class="d-flex align-center justify-end"
        >
          <VBtn
            prepend-icon="tabler-upload"
            @click="openCreateDialog"
          >
            {{ $t('hr.documents.upload') }}
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
      <template #item.title="{ item }">
        <div class="font-weight-medium">
          {{ item.raw.title }}
        </div>
        <div class="hr-page__muted text-caption">
          {{ item.raw.original_name }}
        </div>
      </template>
      <template #item.reference_number="{ item }">
        <span dir="ltr">{{ item.raw.reference_number || '—' }}</span>
      </template>
      <template #item.expiry_date="{ item }">
        <span
          v-if="item.raw.expiry_date"
          dir="ltr"
        >{{ item.raw.expiry_date }}</span>
        <span v-else>—</span>
        <VChip
          v-if="item.raw.is_expired"
          size="x-small"
          label
          color="error"
          class="ms-1"
        >
          {{ $t('hr.documents.expired') }}
        </VChip>
        <VChip
          v-else-if="item.raw.expires_soon"
          size="x-small"
          label
          color="warning"
          class="ms-1"
        >
          {{ $t('hr.documents.expires_soon') }}
        </VChip>
      </template>
      <template #item.size="{ item }">
        <span dir="ltr">{{ formatSize(item.raw.size) }}</span>
      </template>
      <template #item.created_at="{ item }">
        <span dir="ltr">{{ String(item.raw.created_at || '').slice(0, 10) }}</span>
        <div class="hr-page__muted text-caption">
          {{ item.raw.uploaded_by }}
        </div>
      </template>
      <template #item.actions="{ item }">
        <div class="d-flex gap-1">
          <VBtn
            icon
            variant="text"
            size="small"
            @click="download(item.raw)"
          >
            <VIcon icon="tabler-download" />
            <VTooltip activator="parent">
              {{ $t('hr.documents.download') }}
            </VTooltip>
          </VBtn>
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
      max-width="680"
    >
      <VCard :title="$t(editingId ? 'hr.documents.edit' : 'hr.documents.upload')">
        <VCardText>
          <VRow>
            <VCol
              v-if="!editingId"
              cols="12"
            >
              <VFileInput
                :label="`${$t('hr.documents.file')} *`"
                :accept="ACCEPT"
                :hint="$t('hr.documents.file_hint', { size: MAX_UPLOAD_MB })"
                persistent-hint
                prepend-icon=""
                prepend-inner-icon="tabler-paperclip"
                :error-messages="fileError"
                @update:model-value="onFileChange"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.category"
                :items="lookups.document_categories"
                :label="`${$t('hr.documents.category')} *`"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.document_type"
                :items="typeOptions"
                :label="`${$t('hr.documents.type')} *`"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="form.title"
                :label="`${$t('hr.documents.title_field')} *`"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.reference_number"
                :label="$t('hr.documents.reference_number')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppDateTimePicker
                v-model="form.issue_date"
                :label="$t('hr.documents.issue_date')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppDateTimePicker
                v-model="form.expiry_date"
                :label="$t('hr.documents.expiry_date')"
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
            :disabled="!canSave"
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
  </VCard>
</template>
