<script setup>
import { useHrLookups } from '@/composables/useHrLookups'
import { hrAttendancePoliciesApi } from '@/plugins/apis/hrRequest'
import { todayIso } from '@/utils/hrShifts'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  modelValue: { type: Boolean, default: false },

  // Policy being edited (AttendancePolicyResource) or null to create one.
  policy: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'saved'])

const RULE_FIELDS = [
  'check_in_before_minutes',
  'check_in_after_minutes',
  'check_out_before_minutes',
  'check_out_after_minutes',
  'late_grace_minutes',
  'early_leave_grace_minutes',
  'late_counting',
  'late_action',
  'absence_after_minutes',
  'early_leave_counting',
  'early_leave_action',
  'missing_checkout_action',
  'duplicate_punch_action',
  'duplicate_window_minutes',
  'gps_requirement',
  'allow_manual_attendance',
]

const NUMERIC_FIELDS = RULE_FIELDS.filter(field => field.endsWith('_minutes'))

// Sample shift used to preview the windows (minutes from midnight).
const SAMPLE_START = 8 * 60
const SAMPLE_END = 16 * 60

const { t } = useI18n()
const { lookups } = useHrLookups()

const form = ref({})
const original = ref({})
const errors = ref({})
const saving = ref(false)

const isEdit = computed(() => !!props.policy?.id)
const options = computed(() => lookups.value.attendance_rule_options ?? {})

const pickRules = source => Object.fromEntries(RULE_FIELDS.map(field => [field, source?.[field] ?? null]))

const toFormValue = (field, value) => (NUMERIC_FIELDS.includes(field) && value === null ? '' : value)

const reset = () => {
  const source = props.policy ?? lookups.value.attendance_defaults ?? {}
  const rules = Object.fromEntries(Object.entries(pickRules(source)).map(([field, value]) => [field, toFormValue(field, value)]))

  rules.allow_manual_attendance = !!rules.allow_manual_attendance
  form.value = {
    code: props.policy?.code ?? '',
    name_ar: props.policy?.name_ar ?? '',
    name_en: props.policy?.name_en ?? '',
    description: props.policy?.description ?? '',
    is_active: props.policy?.is_active ?? true,
    ...rules,
    effective_from: todayIso(),
    reason: '',
  }
  original.value = { ...rules }
  errors.value = {}
}

watch(() => props.modelValue, visible => {
  if (visible)
    reset()
})

const close = () => emit('update:modelValue', false)

const toNumberOrNull = value => (value === '' || value === null || value === undefined ? null : Number(value))

const rulesPayload = () => Object.fromEntries(RULE_FIELDS.map(field => [
  field,
  NUMERIC_FIELDS.includes(field) ? toNumberOrNull(form.value[field]) : form.value[field],
]))

const rulesChanged = computed(() => RULE_FIELDS.some(field => String(form.value[field] ?? '') !== String(original.value[field] ?? '')))

// A used policy keeps its history: rule changes become a new version from the chosen date.
const needsEffectiveDate = computed(() => isEdit.value && !!props.policy?.has_history && rulesChanged.value)

const payload = () => {
  const data = {
    code: form.value.code,
    name_ar: form.value.name_ar,
    name_en: form.value.name_en || null,
    description: form.value.description || null,
    ...rulesPayload(),
  }

  if (!isEdit.value)
    data.is_active = form.value.is_active
  if (needsEffectiveDate.value) {
    data.effective_from = form.value.effective_from || null
    data.reason = form.value.reason || null
  }

  return data
}

const save = () => {
  saving.value = true
  errors.value = {}

  const request = isEdit.value ? hrAttendancePoliciesApi.update(props.policy.id, payload()) : hrAttendancePoliciesApi.create(payload())

  request.then(response => {
    window.Snackbar?.value?.exposevisibleSnackbar?.(response.data?.message || t('saved_successfully'), 'success')
    emit('saved', response.data?.data)
    close()
  }).catch(error => {
    errors.value = error?.response?.data?.errors ?? {}
  }).finally(() => {
    saving.value = false
  })
}

const errorsOf = field => errors.value[field] ?? []

const clock = minutes => {
  const normalized = ((minutes % 1440) + 1440) % 1440
  const day = Math.floor(minutes / 1440)
  const time = `${String(Math.floor(normalized / 60)).padStart(2, '0')}:${String(normalized % 60).padStart(2, '0')}`

  return day === 0 ? time : `${time} (${day > 0 ? '+' : ''}${day})`
}

const windowPreview = computed(() => {
  const rules = rulesPayload()
  const checkInFrom = SAMPLE_START - (rules.check_in_before_minutes ?? 0)
  const checkInTo = rules.check_in_after_minutes === null ? SAMPLE_END : Math.min(SAMPLE_START + rules.check_in_after_minutes, SAMPLE_END)
  const checkOutFrom = rules.check_out_before_minutes === null ? SAMPLE_START : Math.max(SAMPLE_END - rules.check_out_before_minutes, SAMPLE_START)
  const checkOutTo = SAMPLE_END + (rules.check_out_after_minutes ?? 0)

  return t('hr.policies.window_preview', {
    shift: `${clock(SAMPLE_START)} - ${clock(SAMPLE_END)}`,
    check_in: `${clock(checkInFrom)} - ${clock(checkInTo)}`,
    check_out: `${clock(checkOutFrom)} - ${clock(checkOutTo)}`,
  })
})
</script>

<template>
  <VDialog
    :model-value="modelValue"
    max-width="900"
    scrollable
    @update:model-value="emit('update:modelValue', $event)"
  >
    <VCard :title="$t(isEdit ? 'hr.attendance_setup.edit_policy' : 'hr.attendance_setup.add_policy')">
      <VCardText>
        <VRow>
          <VCol
            cols="12"
            sm="4"
          >
            <AppTextField
              v-model="form.code"
              :label="`${$t('hr.fields.code')} *`"
              :error-messages="errorsOf('code')"
              dir="ltr"
            />
          </VCol>
          <VCol
            cols="12"
            sm="4"
          >
            <AppTextField
              v-model="form.name_ar"
              :label="`${$t('hr.fields.name_ar')} *`"
              :error-messages="errorsOf('name_ar')"
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
              :error-messages="errorsOf('name_en')"
              dir="ltr"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea
              v-model="form.description"
              :label="$t('hr.fields.description')"
              rows="2"
              :error-messages="errorsOf('description')"
            />
          </VCol>
        </VRow>

        <div class="hr-policy-section">
          <div class="hr-policy-section__title">
            <VIcon
              icon="tabler-clock-hour-8"
              size="20"
            />
            {{ $t('hr.policies.sections.windows') }}
          </div>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.check_in_before_minutes"
                type="number"
                min="0"
                max="720"
                :label="`${$t('hr.policies.check_in_before')} *`"
                :error-messages="errorsOf('check_in_before_minutes')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.check_in_after_minutes"
                type="number"
                min="0"
                max="720"
                :label="$t('hr.policies.check_in_after')"
                :hint="$t('hr.policies.check_in_after_hint')"
                persistent-hint
                :error-messages="errorsOf('check_in_after_minutes')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.check_out_before_minutes"
                type="number"
                min="0"
                max="720"
                :label="$t('hr.policies.check_out_before')"
                :hint="$t('hr.policies.check_out_before_hint')"
                persistent-hint
                :error-messages="errorsOf('check_out_before_minutes')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.check_out_after_minutes"
                type="number"
                min="0"
                max="720"
                :label="`${$t('hr.policies.check_out_after')} *`"
                :error-messages="errorsOf('check_out_after_minutes')"
                dir="ltr"
              />
            </VCol>
          </VRow>
          <VAlert
            density="compact"
            variant="tonal"
            color="info"
            icon="tabler-info-circle"
            class="mt-4"
          >
            {{ windowPreview }}
          </VAlert>
        </div>

        <div class="hr-policy-section">
          <div class="hr-policy-section__title">
            <VIcon
              icon="tabler-clock-exclamation"
              size="20"
            />
            {{ $t('hr.policies.sections.late') }}
          </div>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.late_grace_minutes"
                type="number"
                min="0"
                max="240"
                :label="`${$t('hr.attendance_setup.late_grace')} *`"
                :error-messages="errorsOf('late_grace_minutes')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.late_counting"
                :items="options.counting_modes"
                :label="$t('hr.policies.counting')"
                :error-messages="errorsOf('late_counting')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.late_action"
                :items="options.deviation_actions"
                :label="$t('hr.policies.action')"
                :error-messages="errorsOf('late_action')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.absence_after_minutes"
                type="number"
                min="1"
                max="1440"
                :label="$t('hr.attendance_setup.absence_after')"
                :hint="$t('hr.policies.absence_after_hint')"
                persistent-hint
                :error-messages="errorsOf('absence_after_minutes')"
                dir="ltr"
              />
            </VCol>
          </VRow>
        </div>

        <div class="hr-policy-section">
          <div class="hr-policy-section__title">
            <VIcon
              icon="tabler-door-exit"
              size="20"
            />
            {{ $t('hr.policies.sections.early') }}
          </div>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="form.early_leave_grace_minutes"
                type="number"
                min="0"
                max="240"
                :label="`${$t('hr.attendance_setup.early_leave_grace')} *`"
                :error-messages="errorsOf('early_leave_grace_minutes')"
                dir="ltr"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.early_leave_counting"
                :items="options.counting_modes"
                :label="$t('hr.policies.counting')"
                :error-messages="errorsOf('early_leave_counting')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.early_leave_action"
                :items="options.deviation_actions"
                :label="$t('hr.policies.action')"
                :error-messages="errorsOf('early_leave_action')"
              />
            </VCol>
          </VRow>
          <div class="hr-page__muted text-caption mt-2">
            {{ $t('hr.policies.shift_grace_hint') }}
          </div>
        </div>

        <div class="hr-policy-section">
          <div class="hr-policy-section__title">
            <VIcon
              icon="tabler-fingerprint"
              size="20"
            />
            {{ $t('hr.policies.sections.punches') }}
          </div>
          <VRow>
            <VCol
              cols="12"
              sm="4"
            >
              <AppSelect
                v-model="form.missing_checkout_action"
                :items="options.missing_checkout_actions"
                :label="$t('hr.policies.missing_checkout')"
                :error-messages="errorsOf('missing_checkout_action')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppSelect
                v-model="form.duplicate_punch_action"
                :items="options.duplicate_actions"
                :label="$t('hr.policies.duplicate_punch')"
                :error-messages="errorsOf('duplicate_punch_action')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="4"
            >
              <AppTextField
                v-model="form.duplicate_window_minutes"
                type="number"
                min="1"
                max="120"
                :label="`${$t('hr.policies.duplicate_window')} *`"
                :hint="$t('hr.policies.duplicate_window_hint')"
                persistent-hint
                :error-messages="errorsOf('duplicate_window_minutes')"
                dir="ltr"
              />
            </VCol>
          </VRow>
        </div>

        <div class="hr-policy-section">
          <div class="hr-policy-section__title">
            <VIcon
              icon="tabler-map-pin-check"
              size="20"
            />
            {{ $t('hr.policies.sections.verification') }}
          </div>
          <VRow>
            <VCol
              cols="12"
              sm="6"
            >
              <AppSelect
                v-model="form.gps_requirement"
                :items="options.gps_requirements"
                :label="$t('hr.policies.gps_requirement')"
                :error-messages="errorsOf('gps_requirement')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <VSwitch
                v-model="form.allow_manual_attendance"
                :label="$t('hr.attendance_setup.allow_manual')"
                :messages="$t('hr.policies.allow_manual_hint')"
                :error-messages="errorsOf('allow_manual_attendance')"
              />
            </VCol>
          </VRow>
        </div>

        <template v-if="needsEffectiveDate">
          <VAlert
            class="mt-6"
            variant="tonal"
            color="warning"
            icon="tabler-calendar-time"
          >
            {{ $t('hr.policies.effective_change_hint') }}
          </VAlert>
          <VAlert
            v-if="policy?.scheduled_version"
            class="mt-2"
            variant="tonal"
            color="info"
            density="compact"
          >
            {{ $t('hr.policies.already_scheduled', { date: policy.scheduled_version.effective_from }) }}
          </VAlert>
          <VRow class="mt-2">
            <VCol
              cols="12"
              sm="5"
            >
              <AppDateTimePicker
                v-model="form.effective_from"
                :label="`${$t('hr.policies.rules_effective_from')} *`"
                :config="{ minDate: 'today' }"
                :error-messages="errorsOf('effective_from')"
              />
            </VCol>
            <VCol
              cols="12"
              sm="7"
            >
              <AppTextField
                v-model="form.reason"
                :label="$t('hr.fields.change_reason')"
                :error-messages="errorsOf('reason')"
              />
            </VCol>
          </VRow>
        </template>
        <VSwitch
          v-if="!isEdit"
          v-model="form.is_active"
          class="mt-4"
          :label="$t('Active')"
          hide-details
        />
      </VCardText>
      <VCardActions>
        <VSpacer />
        <VBtn
          variant="tonal"
          color="secondary"
          @click="close"
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
</template>

<style lang="scss" scoped>
.hr-policy-section {
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  margin-block-start: 1.5rem;
  padding-block-start: 1.25rem;

  &__title {
    display: flex;
    align-items: center;
    font-weight: 500;
    gap: 0.5rem;
    margin-block-end: 0.75rem;
  }
}
</style>
