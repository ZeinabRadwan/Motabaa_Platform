<script setup>
import { hrSettingsApi } from '@/plugins/apis/hrRequest'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'

const GROUP_ICONS = {
  general: 'tabler-adjustments',
  employees: 'tabler-id-badge-2',
  attendance: 'tabler-clock-check',
  leave: 'tabler-beach',
  payroll: 'tabler-cash',
}

const { t, te } = useI18n()

const canEdit = computed(() => can('edit_hr-settings', 'edit_hr-settings'))

const schema = ref({})
const values = ref({})
const loading = ref(true)
const saving = ref(false)

const applyResponse = response => {
  schema.value = response.data.data.schema ?? {}
  values.value = JSON.parse(JSON.stringify(response.data.data.values ?? {}))
}

const load = () => {
  loading.value = true
  hrSettingsApi.fetch().then(applyResponse).catch(() => {}).finally(() => {
    loading.value = false
  })
}

load()

const fieldLabel = (group, key) => t(`hr.settings.fields.${group}.${key}`)

const fieldHint = (group, key) => {
  const hintKey = `hr.settings.hints.${group}.${key}`

  return te(hintKey) ? t(hintKey) : ''
}

const optionItems = (group, key, definition) => (definition.options ?? []).map(option => {
  const labelKey = key === 'weekend_days' ? `hr.weekdays.${option}` : `hr.settings.options.${key}.${option}`

  return { value: option, title: te(labelKey) ? t(labelKey) : String(option) }
})

const save = () => {
  saving.value = true
  hrSettingsApi.update(values.value).then(response => {
    applyResponse(response)
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
  }).catch(() => {}).finally(() => {
    saving.value = false
  })
}
</script>

<template>
  <div>
    <div
      v-if="loading"
      class="d-flex justify-center pa-10"
    >
      <VProgressCircular
        indeterminate
        color="primary"
      />
    </div>

    <template v-else>
      <VCardText
        v-for="(definitions, group) in schema"
        :key="group"
      >
        <h3 class="hr-page__section-title d-flex align-center gap-2">
          <VIcon
            :icon="GROUP_ICONS[group] || 'tabler-settings'"
            size="20"
          />
          {{ $t(`hr.settings.groups.${group}`) }}
        </h3>
        <VRow>
          <VCol
            v-for="(definition, key) in definitions"
            :key="key"
            cols="12"
            sm="6"
            md="4"
          >
            <VSwitch
              v-if="definition.type === 'bool'"
              v-model="values[group][key]"
              :label="fieldLabel(group, key)"
              :hint="fieldHint(group, key)"
              :persistent-hint="!!fieldHint(group, key)"
              :disabled="!canEdit"
            />
            <AppSelect
              v-else-if="definition.options"
              v-model="values[group][key]"
              :items="optionItems(group, key, definition)"
              :label="fieldLabel(group, key)"
              :hint="fieldHint(group, key)"
              :persistent-hint="!!fieldHint(group, key)"
              :multiple="definition.type === 'int_list'"
              :chips="definition.type === 'int_list'"
              :disabled="!canEdit"
            />
            <AppTextField
              v-else-if="definition.type === 'int'"
              v-model.number="values[group][key]"
              type="number"
              :label="fieldLabel(group, key)"
              :hint="fieldHint(group, key)"
              :persistent-hint="!!fieldHint(group, key)"
              :disabled="!canEdit"
            />
            <AppDateTimePicker
              v-else-if="definition.type === 'date'"
              v-model="values[group][key]"
              :label="fieldLabel(group, key)"
              :hint="fieldHint(group, key)"
              :persistent-hint="!!fieldHint(group, key)"
              :disabled-input="!canEdit"
              clearable
            />
            <AppTextField
              v-else
              v-model="values[group][key]"
              :label="fieldLabel(group, key)"
              :hint="fieldHint(group, key)"
              :persistent-hint="!!fieldHint(group, key)"
              :disabled="!canEdit"
              dir="ltr"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VDivider />
      <VCardActions
        v-if="canEdit"
        class="pa-4"
      >
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          :disabled="saving"
          @click="load"
        >
          {{ $t('hr.common.discard_changes') }}
        </VBtn>
        <VBtn
          :loading="saving"
          @click="save"
        >
          {{ $t('Save') }}
        </VBtn>
      </VCardActions>
    </template>
  </div>
</template>
