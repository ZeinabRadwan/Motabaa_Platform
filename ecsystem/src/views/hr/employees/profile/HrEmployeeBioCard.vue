<script setup>
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'
import { EMPLOYMENT_STATUS_COLORS } from '@/utils/hrFormat'
import HrEmploymentLifecycleDialog from '@/views/hr/employees/HrEmploymentLifecycleDialog.vue'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  employee: { type: Object, required: true },
})

const emit = defineEmits(['changed'])

const { t } = useI18n()

const canEdit = computed(() => can('edit_hr-employees', 'edit_hr-employees'))
const canAdmin = computed(() => can('admin_hr-employees', 'admin_hr-employees'))

const photoInput = ref(null)
const photoBusy = ref(false)
const lifecycleVisible = ref(false)

const initials = computed(() => String(props.employee.name || '?').trim().split(/\s+/).slice(0, 2).map(part => part[0]).join(''))

const notify = message => window.Snackbar?.value?.exposevisibleSnackbar?.(message || t('saved_successfully'), 'success')

const pickPhoto = () => photoInput.value?.click()

const onPhotoSelected = event => {
  const file = event.target.files?.[0]

  event.target.value = ''
  if (!file)
    return
  photoBusy.value = true
  hrEmployeesApi.uploadPhoto(props.employee.id, file).then(response => {
    notify(response.data?.message)
    emit('changed')
  }).catch(() => {}).finally(() => {
    photoBusy.value = false
  })
}

const removePhoto = () => {
  photoBusy.value = true
  hrEmployeesApi.removePhoto(props.employee.id).then(response => {
    notify(response.data?.message)
    emit('changed')
  }).catch(() => {}).finally(() => {
    photoBusy.value = false
  })
}

const openLifecycle = () => {
  lifecycleVisible.value = true
}

const details = computed(() => [
  { icon: 'tabler-id', label: t('hr.fields.employee_number'), value: props.employee.employee_number, ltr: true },
  { icon: 'tabler-building', label: t('hr.fields.branch'), value: props.employee.branch_name },
  { icon: 'tabler-sitemap', label: t('hr.fields.department'), value: props.employee.department_name },
  { icon: 'tabler-user-star', label: t('hr.fields.manager'), value: props.employee.manager?.name },
  { icon: 'tabler-phone', label: t('hr.fields.phone'), value: props.employee.phone, ltr: true },
  { icon: 'tabler-mail', label: t('hr.fields.email'), value: props.employee.email, ltr: true },
  { icon: 'tabler-calendar-event', label: t('hr.fields.employment_start_date'), value: props.employee.employment_start_date, ltr: true },
])
</script>

<template>
  <VCard>
    <VCardText class="text-center pt-8">
      <div class="position-relative d-inline-block">
        <VAvatar
          size="110"
          rounded
          color="primary"
          variant="tonal"
        >
          <VImg
            v-if="employee.photo_url"
            :src="employee.photo_url"
            cover
          />
          <span
            v-else
            class="text-h3"
          >{{ initials }}</span>
        </VAvatar>
      </div>
      <div
        v-if="canEdit"
        class="d-flex justify-center gap-2 mt-3"
      >
        <VBtn
          size="small"
          variant="tonal"
          prepend-icon="tabler-camera"
          :loading="photoBusy"
          @click="pickPhoto"
        >
          {{ $t('hr.employees.change_photo') }}
        </VBtn>
        <VBtn
          v-if="employee.photo_url"
          size="small"
          variant="text"
          color="error"
          :disabled="photoBusy"
          @click="removePhoto"
        >
          {{ $t('hr.employees.remove_photo') }}
        </VBtn>
        <input
          ref="photoInput"
          type="file"
          accept="image/jpeg,image/png,image/webp"
          hidden
          @change="onPhotoSelected"
        >
      </div>

      <h5 class="text-h5 mt-4">
        {{ employee.name }}
      </h5>
      <div class="hr-page__muted mb-2">
        {{ employee.job_title || '—' }}
      </div>
      <VChip
        label
        size="small"
        :color="EMPLOYMENT_STATUS_COLORS[employee.employment_status] || 'secondary'"
      >
        {{ employee.employment_status_label }}
      </VChip>
      <div
        v-if="!employee.is_employed && employee.terminated_at"
        class="hr-page__muted mt-2"
      >
        {{ $t('hr.employees.ended_on', { date: employee.terminated_at, reason: employee.termination_reason_label }) }}
      </div>
    </VCardText>

    <VDivider />

    <VList density="compact">
      <VListItem
        v-for="detail in details"
        :key="detail.label"
      >
        <template #prepend>
          <VIcon
            :icon="detail.icon"
            size="20"
            class="me-2"
          />
        </template>
        <VListItemSubtitle>{{ detail.label }}</VListItemSubtitle>
        <VListItemTitle :dir="detail.ltr ? 'ltr' : undefined">
          {{ detail.value || '—' }}
        </VListItemTitle>
      </VListItem>
    </VList>

    <VCardText
      v-if="canEdit || canAdmin"
      class="d-flex justify-center gap-3"
    >
      <VBtn
        v-if="canEdit"
        prepend-icon="tabler-edit"
        :to="{ name: 'hr-employees-edit-id', params: { id: employee.id } }"
      >
        {{ $t('hr.employees.edit') }}
      </VBtn>
      <VBtn
        v-if="canAdmin"
        variant="tonal"
        :color="employee.is_employed ? 'error' : 'success'"
        :prepend-icon="employee.is_employed ? 'tabler-user-off' : 'tabler-user-check'"
        @click="openLifecycle"
      >
        {{ $t(employee.is_employed ? 'hr.employees.deactivate' : 'hr.employees.activate') }}
      </VBtn>
    </VCardText>

    <HrEmploymentLifecycleDialog
      v-model="lifecycleVisible"
      :employee="employee"
      @saved="emit('changed')"
    />
  </VCard>
</template>
