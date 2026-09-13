<script setup>
import i18n from '@/plugins/i18n/index.js';
import { VDataTable } from 'vuetify/labs/VDataTable'
import { cuntriesIndexed } from "@core/utils/cuntries";
import { useUserListStore } from '@/views/apps/user/useUserListStore'

const { withHeaders } = defineProps(['withHeaders']);
const userListStore = useUserListStore()
const users = ref()

const tableHeader = [
  {
    title: 'Role',
    key: 'role',
  },
  {
    title: 'Names',
    key: 'names',
  },
]

userListStore.fetchRolesUsers({fetch: 'fetch_employees'}).then(response => {
  users.value = response.data.data
})

</script>

<template >
  <VRow>
    <VCol cols="12">
      <VCard>
        <VCardText>
          <VTable>
            <thead>
              <tr style="background-color: #867DEF; font-size: 15px;">
                <th>
                  <span style="color: white;">
                    {{ $t('id') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('name') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('employee_affairs.job_title') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('Department') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('nationality') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('qualification') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('specialization') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('precise_specialization') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('current_work') }}
                  </span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(user, key) in users"
                :key="role"
              >
                <td>
                  <div class="d-flex align-right">
                    {{ (key+1) }}
                  </div>
                </td>
                <td>
                  <div class="d-flex align-right">
                    <RouterLink
                      :to="{ name: 'user-view-id', params: { id: user.id } }"
                      class="font-weight-medium user-list-name"
                    >
                      {{ user.name }}
                    </RouterLink>
                  </div>
                </td>
                <td>
                  <div class="d-flex align-right">
                    {{ user.job_title }}
                  </div>
                </td>
                <td>
                  <div class="d-flex align-right">
                    {{ user.department ? $t(`department.${user.department}`) : '' }}
                  </div>
                </td>
                <td>
                  <div v-if="user.nationality" class="d-flex align-right">
                    {{ cuntriesIndexed[user.nationality][i18n.global.locale.value == "ar" ? "country_arNationality" : "country_enNationality"] }}
                  </div>
                </td>
                <td>
                  <div class="d-flex align-right">
                    {{ user.qualification }}
                  </div>
                </td>
                <td>
                  <div class="d-flex align-right">
                    {{ user.specialization }}
                  </div>
                </td>
                <td>
                  <div class="d-flex align-right">
                    {{ user.precise_specialization }}
                  </div>
                </td>
                <td>
                  <div class="d-flex align-right">
                    {{ user.current_work }}
                  </div>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>

<style scoped >
  .td-style{
    border-left: 1px solid #B0B0B066;
    border-right: 1px solid #B0B0B066;
  }
  .th-style{
    color: white !important;
    background-color: #867DEF !important;
  }
  .user-list-name:not(:hover) {
  color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
}
</style>