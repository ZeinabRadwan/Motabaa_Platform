<script setup>
import { VDataTable } from 'vuetify/labs/VDataTable'
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

userListStore.fetchRolesUsers({fetch: 'fetch_planning_team'}).then(response => {
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
                    {{ $t('planning_team.team_member') }}
                  </span>
                </th>
                <th>
                  <span style="color: white;">
                    {{ $t('planning_team.role') }}
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
                    <span class="text-capitalize text-body-1" v-html="user.roles.map(obj => obj.name).toString().replaceAll(',', ', ')"></span>
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