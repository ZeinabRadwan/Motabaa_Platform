<script setup>
import HrInfoGrid from '@/components/hr/HrInfoGrid.vue'
import i18n from '@/plugins/i18n'
import { cuntriesIndexed } from '@core/utils/cuntries'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  employee: { type: Object, required: true },
})

const { t } = useI18n()

const nationality = computed(() => {
  const country = cuntriesIndexed[props.employee.nationality]
  if (!country)
    return props.employee.nationality

  return i18n.global.locale.value === 'ar' ? country.country_arNationality : country.country_enNationality
})

const items = computed(() => {
  const employee = props.employee
  const address = employee.address ?? {}

  return [
    { label: t('hr.fields.full_name'), value: employee.name },
    { label: t('hr.fields.nationality'), value: nationality.value },
    { label: t('hr.fields.gender'), value: employee.gender ? t(employee.gender === 1 ? 'male' : 'female') : null },
    { label: t('hr.fields.birthdate'), value: employee.birthdate, ltr: true },
    { label: t('hr.fields.phone'), value: employee.phone, ltr: true },
    { label: t('hr.fields.email'), value: employee.email, ltr: true },
    { label: t(employee.id_type === 'iqama' ? 'hr.fields.iqama_number' : 'hr.fields.national_id'), value: employee.id_or_residence_number, ltr: true },
    { label: t('hr.fields.id_expiry_date'), value: employee.id_expiry_date, ltr: true },
    { label: t('hr.fields.city'), value: address.city },
    { label: t('hr.fields.district'), value: address.area },
    { label: t('hr.fields.street'), value: address.street },
    { label: t('hr.fields.building_number'), value: address.building, ltr: true },
    { label: t('hr.fields.additional_number'), value: address.number, ltr: true },
    { label: t('hr.fields.unit'), value: address.unit, ltr: true },
    { label: t('hr.fields.postal_code'), value: address.zipcode, ltr: true },
  ]
})
</script>

<template>
  <VCard :title="$t('hr.employees.tabs.personal')">
    <VCardText>
      <HrInfoGrid :items="items" />
    </VCardText>
  </VCard>
</template>
