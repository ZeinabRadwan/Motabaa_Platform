<script setup>
import { VDataTable } from 'vuetify/labs/VDataTable'
import { useUserListStore } from '@/views/apps/user/useUserListStore'
const { withHeaders } = defineProps(['withHeaders']);

const userListStore = useUserListStore()
const users = ref()
const nSpecialists = ref()
const nSASpecialists = ref()
const nSpecialistsTotal = ref(0)
const nSASpecialistsTotal = ref(0)
const nTeachers = ref()
const nSATeachers = ref()
const nTeachersTotal = ref(0)
const nSATeachersTotal = ref(0)
const nRoles = ref()

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

userListStore.fetchRolesUsers({fetch: 'fetch_roles_users', exclude_roles: ['parent']}).then(response => {
  users.value = response.data.data
})

userListStore.fetchRolesUsers({fetch: 'fetch_users_count', like_roles: ['specialist']}).then(response => {
  nSpecialists.value = response.data.data
  nSpecialists.value.forEach(item => {
    nSpecialistsTotal.value += parseInt(item.users_count)
  });
})

userListStore.fetchRolesUsers({fetch: 'fetch_users_count', like_roles: ['specialist'], nationality: 'SA'}).then(response => {
  nSASpecialists.value = response.data.data
  nSASpecialists.value.forEach(item => {
    nSASpecialistsTotal.value += parseInt(item.users_count)
  });
})

userListStore.fetchRolesUsers({fetch: 'fetch_users_count', like_roles: ['teacher']}).then(response => {
  nTeachers.value = response.data.data
  nTeachers.value.forEach(item => {
    nTeachersTotal.value += parseInt(item.users_count)
  });
})

userListStore.fetchRolesUsers({fetch: 'fetch_users_count', like_roles: ['teacher'], nationality: 'SA'}).then(response => {
  nSATeachers.value = response.data.data
  nSATeachers.value.forEach(item => {
    nSATeachersTotal.value += parseInt(item.users_count)
  });
})

userListStore.fetchRolesUsers({fetch: 'fetch_users_count', exclude_roles: ['admin', 'parent'], not_like_roles: ['specialist', 'teacher']}).then(response => {
  nRoles.value = response.data.data
})

</script>

<template >
  <VRow>
    <VCol cols="12">
      <VCard>
        <VCardText>
          <span class="v-col v-col-6 d-flex gap-4 text-h4"> {{ $t('operational_plan.workers') }}</span>
          <VTable>
            <thead>
              <tr style="background-color: #867DEF; font-size: 15px;">
                <th>
                  <span style="color: white;">
                    {{ $t('operational_plan.role') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('operational_plan.names') }}
                  </span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(names, role) in users"
                :key="role"
              >
                <td  width="20%">
                  <div class="d-flex align-right">
                    {{ $t(role) }}
                  </div>
                </td>
                <td  width="80%">
                  <span v-for="(user, id) in names" :key="id" class="gap-1 pa-1 pt-1" style="font-size: 12px;">
                    <RouterLink
                      :to="{ name: 'user-view-id', params: { id: user.id } }"
                      class="font-weight-medium user-list-name"
                    >
                      {{ user.name }}
                    </RouterLink>,
                  </span>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
        
        <VCardText>
          <span class="v-col v-col-6 d-flex gap-4 text-h4"> {{ $t('operational_plan.specialists') }}</span>
          <VTable>
            <thead>
              <tr style="background-color: #867DEF; font-size: 15px;">
                <th>
                  <span style="color: white;">
                    {{ $t('operational_plan.role_type') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('operational_plan.count') }}
                  </span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <th width="15%">
                  <span class="d-flex align-right">
                    {{ $t('operational_plan.sa') }}
                  </span>
                </th>
                <td width="85%">
                  <div class="d-flex align-right">
                    {{ nSASpecialistsTotal }}
                  </div>
                </td>
              </tr>

              <tr>
                <th width="15%">
                  <span class="d-flex align-right">
                    {{ $t('operational_plan.not_sa') }}
                  </span>
                </th>
                <td width="85%">
                  <div class="d-flex align-right">
                    {{ (parseInt(nSpecialistsTotal) - parseInt(nSASpecialistsTotal)) }}
                  </div>
                </td>
              </tr>

              <tr>
                <th width="15%">
                  <span class="d-flex align-right">
                    {{ $t('operational_plan.total') }}
                  </span>
                </th>
                <td width="85%">
                  <div class="d-flex align-right">
                    {{ nSpecialistsTotal }}
                  </div>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
        
        <VCardText>
          <span class="v-col v-col-6 d-flex gap-4 text-h4"> {{ $t('operational_plan.specialties') }}</span>
          <VTable>
            <thead>
              <tr style="background-color: #867DEF; font-size: 15px;">
                <th>
                  <span style="color: white;">
                    {{ $t('operational_plan.role_type') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('operational_plan.count') }}
                  </span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(roles_count, id) in nSpecialists"
                :key="id"
              >
                <td width="15%">
                  <div class="d-flex align-right">
                    {{ roles_count.name }}
                  </div>
                </td>
                <td width="85%">
                  <div class="d-flex align-right">
                    {{ roles_count.users_count }}
                  </div>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
        
        <VCardText>
          <span class="v-col v-col-6 d-flex gap-4 text-h4"> {{ $t('operational_plan.teachers') }}</span>
          <VTable>
            <thead>
              <tr style="background-color: #867DEF; font-size: 15px;">
                <th>
                  <span style="color: white;">
                    {{ $t('operational_plan.role_type') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('operational_plan.count') }}
                  </span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <th width="15%">
                  <span class="d-flex align-right">
                    {{ $t('operational_plan.sa') }}
                  </span>
                </th>
                <td width="85%">
                  <div class="d-flex align-right">
                    {{ nSATeachersTotal }}
                  </div>
                </td>
              </tr>

              <tr>
                <th width="15%">
                  <span class="d-flex align-right">
                    {{ $t('operational_plan.not_sa') }}
                  </span>
                </th>
                <td width="85%">
                  <div class="d-flex align-right">
                    {{ (parseInt(nTeachersTotal) - parseInt(nSATeachersTotal)) }}
                  </div>
                </td>
              </tr>

              <tr>
                <th width="15%">
                  <span class="d-flex align-right">
                    {{ $t('operational_plan.total') }}
                  </span>
                </th>
                <td width="85%">
                  <div class="d-flex align-right">
                    {{ nTeachersTotal }}
                  </div>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
        
        <VCardText>
          <span class="v-col v-col-6 d-flex gap-4 text-h4"> {{ $t('operational_plan.specialties') }}</span>
          <VTable>
            <thead>
              <tr style="background-color: #867DEF; font-size: 15px;">
                <th>
                  <span style="color: white;">
                    {{ $t('operational_plan.role_type') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('operational_plan.count') }}
                  </span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(roles_count, id) in nTeachers"
                :key="id"
              >
                <td width="15%">
                  <div class="d-flex align-right">
                    {{ roles_count.name }}
                  </div>
                </td>
                <td width="85%">
                  <div class="d-flex align-right">
                    {{ roles_count.users_count }}
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