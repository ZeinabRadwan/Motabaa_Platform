<script setup>
import i18n from '@/plugins/i18n/index.js'
import { cuntriesIndexed } from '@core/utils/cuntries'
import { convertGregorianToHijri } from '@core/utils/helper'

const props = defineProps({
  userData: {
    type: Object,
    required: true,
  },
})

const nationalityLabel = computed(() => {
  const code = props.userData.nationality
  if (!code)
    return ''

  const country = cuntriesIndexed[code]

  return country
    ? country[i18n.global.locale.value === 'ar' ? 'country_arNationality' : 'country_enNationality']
    : code
})

function formatDate(value) {
  if (!value)
    return ''

  return convertGregorianToHijri(String(value).split('T')[0])
}

const fields = computed(() => {
  const u = props.userData

  return [
    { icon: 'tabler-phone', label: 'phone', value: u.phone, ltr: true },
    { icon: 'tabler-mail', label: 'Email', value: u.email, ltr: true },
    { icon: 'tabler-calendar', label: 'birthdate', value: formatDate(u.birthdate) },
    { icon: 'tabler-id', label: 'id_or_residence_number', value: u.id_or_residence_number, ltr: true },
    { icon: 'tabler-gender-bigender', label: 'gender', value: u.gender_type },
    { icon: 'tabler-user-check', label: 'has_user_account', value: u.can_login != null ? (u.can_login ? i18n.global.t('yes') : i18n.global.t('no')) : '' },
    { icon: 'tabler-briefcase', label: 'Roles', value: u.roles?.map(r => r.name).join(' · ') },
    { icon: 'tabler-calendar-check', label: 'employee_affairs.annual_leave_entitlement', value: u.annual_leave_entitlement != null ? String(u.annual_leave_entitlement) : '' },
    { icon: 'tabler-flag', label: 'nationality', value: nationalityLabel.value },
    { icon: 'tabler-building', label: 'Building number', value: u.address_building },
    { icon: 'tabler-road', label: 'Street name', value: u.address_street },
    { icon: 'tabler-map-pin', label: 'El Hay', value: u.address_area },
    { icon: 'tabler-building-community', label: 'City', value: u.address_city },
    { icon: 'tabler-mailbox', label: 'Zip', value: u.address_zipcode },
    { icon: 'tabler-door', label: 'Unit number', value: u.address_unit },
  ].filter(row => row.value !== null && row.value !== undefined && row.value !== '')
})

const displayFields = computed(() => {
  const rolesText = props.userData.roles?.map(r => r.name).join(' · ')

  return fields.value.filter(row => {
    if (row.label === 'Name')
      return false

    if (row.label === 'Roles' && rolesText)
      return false

    return true
  })
})
</script>

<template>
  <article class="account-profile__details-card">
    <header class="account-profile__details-head">
      <span class="account-profile__details-head-icon-wrap">
        <VIcon
          icon="tabler-id-badge-2"
          size="18"
        />
      </span>
      <span>{{ $t('account.tab_info') }}</span>
    </header>

    <div class="account-profile__fields-grid">
      <div
        v-for="(field, index) in displayFields"
        :key="index"
        class="account-profile__field-tile"
      >
        <span class="account-profile__field-tile-icon">
          <VIcon
            :icon="field.icon"
            size="18"
          />
        </span>
        <div class="account-profile__field-tile-body">
          <span class="account-profile__field-tile-label">{{ $t(field.label) }}</span>
          <span
            class="account-profile__field-tile-value"
            :dir="field.ltr ? 'ltr' : undefined"
          >{{ field.value }}</span>
        </div>
      </div>
    </div>
  </article>
</template>

<style lang="scss">
@import '@/styles/account-profile.scss';
</style>
