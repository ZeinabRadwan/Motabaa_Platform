<script setup>
import i18n from '@/plugins/i18n/index.js';
import { cuntriesIndexed } from "@core/utils/cuntries";
import { convertGregorianToHijri } from "@core/utils/helper";

const props = defineProps({
  userData: {
    type: Object,
    required: true,
  },
})

const nationalityLabel = computed(() => {
  const code = props.userData.nationality
  if (!code) return ''

  const country = cuntriesIndexed[code]
  if (!country) return code

  return country[i18n.global.locale.value == 'ar' ? 'country_arNationality' : 'country_enNationality']
})
</script>

<template>
  <VTable>
    <tbody>
      <tr v-if="props.userData.name">
        <td width="20%">
          {{ $t('Name') }}
        </td>
        <td>
          {{ props.userData.name }}
        </td>
      </tr>

      <tr v-if="props.userData.phone">
        <td>
          {{ $t('phone') }}
        </td>
        <td>
          {{ props.userData.phone }}
        </td>
      </tr>

      <tr v-if="props.userData.email">
        <td>
          {{ $t('Email') }}
        </td>
        <td>
          {{ props.userData.email }}
        </td>
      </tr>

      <tr v-if="props.userData.birthdate">
        <td>
          {{ $t('birthdate') }}
        </td>
        <td>
          {{ convertGregorianToHijri(props.userData.birthdate) }}
        </td>
      </tr>

      <tr v-if="props.userData.id_or_residence_number">
        <td>
          {{ $t('id_or_residence_number') }}
        </td>
        <td>
          {{ props.userData.id_or_residence_number }}
        </td>
      </tr>

      <tr v-if="props.userData.gender_type">
        <td>
          {{ $t('gender') }}
        </td>
        <td>
          {{ props.userData.gender_type }}
        </td>
      </tr>

      <tr v-if="nationalityLabel">
        <td>
          {{ $t('nationality') }}
        </td>
        <td>
          {{ nationalityLabel }}
        </td>
      </tr>

      <tr>
        <td>
          {{ $t('has_user_account') }}
        </td>
        <td>
          {{ props.userData.can_login ? $t('yes') : $t('no') }}
        </td>
      </tr>

      <tr v-if="props.userData.roles">
        <td>
          {{ $t('Roles') }}
        </td>
        <td>
          {{ props.userData.roles.map(obj => obj.name).toString().replaceAll(',', ', ') }}
        </td>
      </tr>

      <tr v-if="props.userData.qualification">
        <td>{{ $t('qualification') }}</td>
        <td>{{ props.userData.qualification }}</td>
      </tr>
      <tr v-if="props.userData.specialization">
        <td>{{ $t('specialization') }}</td>
        <td>{{ props.userData.specialization }}</td>
      </tr>
      <tr v-if="props.userData.precise_specialization">
        <td>{{ $t('precise_specialization') }}</td>
        <td>{{ props.userData.precise_specialization }}</td>
      </tr>
      <tr v-if="props.userData.current_work">
        <td>{{ $t('current_work') }}</td>
        <td>{{ props.userData.current_work }}</td>
      </tr>
      <tr v-if="props.userData.job_title">
        <td>{{ $t('employee_affairs.job_title') }}</td>
        <td>{{ props.userData.job_title }}</td>
      </tr>
      <tr v-if="props.userData.department_label || props.userData.department">
        <td>{{ $t('Department') }}</td>
        <td>{{ props.userData.department_label || $t(`department.${props.userData.department}`) }}</td>
      </tr>
      <tr v-if="props.userData.work_shift_label">
        <td>{{ $t('employee_affairs.work_shift') }}</td>
        <td>{{ props.userData.work_shift_label }}</td>
      </tr>
      <tr v-if="props.userData.contract_type_label || props.userData.contract_type">
        <td>{{ $t('employee_affairs.contract_type') }}</td>
        <td>{{ props.userData.contract_type_label || $t(`employee_affairs.contract_types.${props.userData.contract_type}`) }}</td>
      </tr>
      <tr v-if="props.userData.hire_date">
        <td>{{ $t('employee_affairs.hire_date') }}</td>
        <td>{{ convertGregorianToHijri(String(props.userData.hire_date).split('T')[0]) }}</td>
      </tr>
      <tr v-if="props.userData.contract_end_date">
        <td>{{ $t('employee_affairs.contract_end_date') }}</td>
        <td>{{ convertGregorianToHijri(String(props.userData.contract_end_date).split('T')[0]) }}</td>
      </tr>
      <tr v-if="props.userData.id_expiry_date">
        <td>{{ $t('employee_affairs.id_expiry_date') }}</td>
        <td>{{ convertGregorianToHijri(String(props.userData.id_expiry_date).split('T')[0]) }}</td>
      </tr>
      <tr v-if="props.userData.annual_leave_entitlement != null">
        <td>{{ $t('employee_affairs.annual_leave_entitlement') }}</td>
        <td>{{ props.userData.annual_leave_entitlement }}</td>
      </tr>

      <tr v-if="props.userData.address_building">
        <td>
          {{ $t('Building number') }}
        </td>
        <td>
          {{ props.userData.address_building }}
        </td>
      </tr>

      <tr v-if="props.userData.address_street">
        <td>
          {{ $t('Street name') }}
        </td>
        <td>
          {{ props.userData.address_street }}
        </td>
      </tr>

      <tr v-if="props.userData.address_area">
        <td>
          {{ $t('El Hay') }}
        </td>
        <td>
          {{ props.userData.address_area }}
        </td>
      </tr>

      <tr v-if="props.userData.address_city">
        <td>
          {{ $t('City') }}
        </td>
        <td>
          {{ props.userData.address_city }}
        </td>
      </tr>

      <tr v-if="props.userData.address_zipcode">
        <td>
          {{ $t('Zip') }}
        </td>
        <td>
          {{ props.userData.address_zipcode }}
        </td>
      </tr>

      <tr v-if="props.userData.address_number">
        <td>
          {{ $t('Second Zip') }}
        </td>
        <td>
          {{ props.userData.address_number }}
        </td>
      </tr>

      <tr v-if="props.userData.address_unit">
        <td>
          {{ $t('Unit number') }}
        </td>
        <td>
          {{ props.userData.address_unit }}
        </td>
      </tr>
    </tbody>
  </VTable>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 0.75rem;
}

.text-capitalize {
  text-transform: capitalize !important;
}
</style>
