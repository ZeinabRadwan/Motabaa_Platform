<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrApprovalsApi, hrEmployeesApi } from '@/plugins/apis/hrRequest'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'

const MAX_STEPS = 10
const TYPES_WITH_VALUE = ['role', 'user', 'permission']
const FALLBACK_PERMISSION = 'apply_hr-approvals'

const { t } = useI18n()
const { lookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-approvals', 'edit_hr-approvals'))

const workflows = ref([])
const loading = ref(true)

const load = () => {
  loading.value = true
  hrApprovalsApi.workflows().then(response => {
    workflows.value = response.data.data
  }).catch(() => {}).finally(() => {
    loading.value = false
  })
}

load()

const rows = computed(() => lookups.value.request_types.map(type => ({
  type,
  workflow: workflows.value.find(workflow => workflow.request_type === type.value) ?? null,
})))

const stepLabel = step => {
  const type = step.approver_type_label || step.approver_type

  return step.approver_value_label ? `${type}: ${step.approver_value_label}` : type
}

// user id (string) → name, so saved "specific user" steps display before any search.
const knownUsers = computed(() => {
  const names = {}

  workflows.value.forEach(workflow => workflow.steps
    .filter(step => step.approver_type === 'user' && step.approver_value)
    .forEach(step => { names[String(step.approver_value)] = step.approver_value_label }))

  return names
})

// ── Editor ──────────────────────────────────────────────────────────────
const isDialogVisible = ref(false)
const saving = ref(false)
const form = ref(null)

const openEditor = row => {
  const workflow = row.workflow

  form.value = {
    request_type: row.type.value,
    request_type_label: row.type.title,
    name_ar: workflow?.name_ar ?? row.type.title,
    name_en: workflow?.name_en ?? '',
    is_active: workflow?.is_active ?? true,
    steps: (workflow?.steps ?? [{ approver_type: 'direct_manager', approver_value: null }])
      .map(step => ({ approver_type: step.approver_type, approver_value: step.approver_value ?? null })),
  }
  isDialogVisible.value = true
}

const addStep = () => {
  if (form.value.steps.length < MAX_STEPS)
    form.value.steps.push({ approver_type: 'direct_manager', approver_value: null })
}

const removeStep = index => form.value.steps.splice(index, 1)

const moveStep = (index, offset) => {
  const target = index + offset
  if (target < 0 || target >= form.value.steps.length)
    return

  const [moved] = form.value.steps.splice(index, 1)

  form.value.steps.splice(target, 0, moved)
}

const onTypeChange = step => {
  step.approver_value = null
}

const searchUsers = params => hrEmployeesApi.list({
  q: params.q || undefined,
  employment_status: 'active',
  options: { page: 1, itemsPerPage: 20 },
}).then(response => {
  const found = response.data.data.map(employee => ({ id: String(employee.user_id), name: employee.name }))

  const selected = (params.ids ?? [])
    .map(String)
    .filter(id => knownUsers.value[id] && !found.some(user => user.id === id))
    .map(id => ({ id, name: knownUsers.value[id] }))

  return { data: { data: [...selected, ...found] } }
})

const canSave = computed(() => form.value?.name_ar && form.value.steps.length
  && form.value.steps.every(step => !TYPES_WITH_VALUE.includes(step.approver_type) || step.approver_value))

const save = () => {
  saving.value = true

  const { request_type_label: _label, ...payload } = form.value

  payload.steps = payload.steps.map(step => ({
    approver_type: step.approver_type,
    approver_value: TYPES_WITH_VALUE.includes(step.approver_type) ? String(step.approver_value) : null,
  }))

  hrApprovalsApi.saveWorkflow(payload).then(response => {
    isDialogVisible.value = false
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    load()
  }).catch(() => {}).finally(() => {
    saving.value = false
  })
}
</script>

<template>
  <div>
    <VCardText>
      <VAlert
        variant="tonal"
        color="info"
        density="compact"
        class="mb-4"
      >
        {{ $t('hr.workflows.fallback_hint', { permission: lookups.approver_permissions?.find(option => option.value === FALLBACK_PERMISSION)?.title ?? FALLBACK_PERMISSION }) }}
      </VAlert>

      <div
        v-if="loading"
        class="d-flex justify-center pa-6"
      >
        <VProgressCircular
          indeterminate
          color="primary"
        />
      </div>

      <VTable
        v-else
        class="text-no-wrap"
      >
        <thead>
          <tr>
            <th>{{ $t('hr.fields.request_type') }}</th>
            <th>{{ $t('hr.workflows.steps') }}</th>
            <th>{{ $t('Status') }}</th>
            <th v-if="canEdit" />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.type.value"
          >
            <td>{{ row.type.title }}</td>
            <td>
              <div
                v-if="row.workflow"
                class="hr-steps py-2"
              >
                <VChip
                  v-for="step in row.workflow.steps"
                  :key="step.step_order"
                  size="small"
                  label
                >
                  {{ step.step_order }}. {{ stepLabel(step) }}
                </VChip>
              </div>
              <span
                v-else
                class="hr-page__muted"
              >{{ $t('hr.workflows.using_fallback') }}</span>
            </td>
            <td>
              <VChip
                size="small"
                label
                :color="row.workflow?.is_active ? 'success' : 'secondary'"
              >
                {{ $t(row.workflow?.is_active ? 'Active' : 'hr.workflows.not_configured') }}
              </VChip>
            </td>
            <td
              v-if="canEdit"
              class="text-end"
            >
              <VBtn
                size="small"
                variant="tonal"
                prepend-icon="tabler-edit"
                @click="openEditor(row)"
              >
                {{ $t('hr.workflows.configure') }}
              </VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>

    <VDialog
      v-model="isDialogVisible"
      max-width="820"
    >
      <VCard
        v-if="form"
        :title="`${$t('hr.workflows.configure')} — ${form.request_type_label}`"
      >
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              sm="5"
            >
              <AppTextField
                v-model="form.name_ar"
                :label="$t('hr.fields.name_ar')"
                dir="rtl"
              />
            </VCol>
            <VCol
              cols="12"
              sm="5"
            >
              <AppTextField
                v-model="form.name_en"
                :label="$t('hr.fields.name_en')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="2"
              class="d-flex align-center"
            >
              <VSwitch
                v-model="form.is_active"
                :label="$t('Active')"
                hide-details
              />
            </VCol>
          </VRow>

          <h4 class="hr-page__section-title mt-4">
            {{ $t('hr.workflows.steps') }}
          </h4>
          <div
            v-for="(step, index) in form.steps"
            :key="index"
            class="hr-workflow-step"
          >
            <span class="hr-workflow-step__order">{{ index + 1 }}</span>
            <AppSelect
              v-model="step.approver_type"
              :items="lookups.approver_types"
              :label="$t('hr.workflows.approver')"
              @update:model-value="onTypeChange(step)"
            />
            <div>
              <AppSelect
                v-if="step.approver_type === 'role'"
                v-model="step.approver_value"
                :items="lookups.approver_roles"
                :label="$t('hr.workflows.role')"
              />
              <AppSelect
                v-else-if="step.approver_type === 'permission'"
                v-model="step.approver_value"
                :items="lookups.approver_permissions"
                :label="$t('hr.workflows.permission')"
              />
              <AppAutocomplete
                v-else-if="step.approver_type === 'user'"
                v-model="step.approver_value"
                :server-search="searchUsers"
                preload
                item-title="name"
                item-value="id"
                :label="$t('hr.workflows.user')"
              />
            </div>
            <div class="d-flex">
              <VBtn
                icon
                variant="text"
                size="small"
                :disabled="index === 0"
                :aria-label="$t('hr.workflows.move_up')"
                @click="moveStep(index, -1)"
              >
                <VIcon icon="tabler-arrow-up" />
              </VBtn>
              <VBtn
                icon
                variant="text"
                size="small"
                :disabled="index === form.steps.length - 1"
                :aria-label="$t('hr.workflows.move_down')"
                @click="moveStep(index, 1)"
              >
                <VIcon icon="tabler-arrow-down" />
              </VBtn>
              <VBtn
                icon
                variant="text"
                size="small"
                color="error"
                :disabled="form.steps.length === 1"
                :aria-label="$t('delete')"
                @click="removeStep(index)"
              >
                <VIcon icon="tabler-trash" />
              </VBtn>
            </div>
          </div>
          <VBtn
            variant="text"
            prepend-icon="tabler-plus"
            :disabled="form.steps.length >= MAX_STEPS"
            @click="addStep"
          >
            {{ $t('hr.workflows.add_step') }}
          </VBtn>
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
  </div>
</template>
