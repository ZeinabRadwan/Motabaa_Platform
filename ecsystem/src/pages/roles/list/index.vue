<script setup>
import i18n from '@/plugins/i18n/index.js'
import { can } from '@layouts/plugins/casl'
import { useRoleStore } from '@/views/apps/roles/useRoleStore'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'

const snackbarRef = ref(null)
const roleStore = useRoleStore()

const currentTab = ref(0)
const loading = ref(true)
const saving = ref(false)
const permissionSearch = ref('')

const role = ref('')
const formDialog = ref(false)
const formMode = ref('add')
const formName = ref('')
const formSelected = ref([])
const isDeleteDialogVisible = ref(false)

const roles = ref([])
const permissionModules = ref([])
const selectedRole = ref(null)
const selected = ref([])

const canShowRoles = computed(() => can('show_roles', 'show_roles') || can('access_roles', 'access_roles') || can('edit_roles', 'edit_roles'))
const canEditRoles = computed(() => can('edit_roles', 'edit_roles'))
const canDeleteRoles = computed(() => can('admin_roles', 'admin_roles') || can('edit_roles', 'edit_roles'))

const selectedRoleMeta = computed(() => roles.value.find(item => item.id === role.value) || selectedRole.value)

const formTitle = computed(() => formMode.value === 'edit' ? i18n.global.t('roles.edit_role') : i18n.global.t('roles.add_role'))

watch(role, newValue => {
  if (!newValue) {
    selectedRole.value = null
    selected.value = []

    return
  }

  selectedRole.value = roles.value.find(item => item.id === newValue)
  roleStore.getPermissonsRole(newValue).then(response => {
    selected.value = (response.data.data.permissions || []).map(num => num.toString())
  })
})

const loadRolesPage = () => {
  loading.value = true

  return roleStore.fetchRoles({}).then(response => {
    const data = response.data.data

    roles.value = data.roles || []
    permissionModules.value = data.permission_modules?.length
      ? data.permission_modules
      : Object.entries(data.permissions_groups || {}).map(([label, permissions]) => ({
        key: label,
        label,
        permissions: Object.entries(permissions).map(([id, permLabel]) => ({
          id: Number(id),
          name: permLabel,
          action: 'other',
          label: permLabel,
          roles: [],
        })),
      }))
  }).finally(() => {
    loading.value = false
  })
}

loadRolesPage()

const isValidRoleName = name => {
  if (!name || !String(name).trim()) {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('Role is required.'), 'error')

    return false
  }
  if (Number.isInteger(parseInt(name))) {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.character_not_valid'), 'error')

    return false
  }

  const taken = roles.value.find(item => {
    if (item.name !== name)
      return false
    if (formMode.value === 'edit' && item.id === selectedRoleMeta.value?.id)
      return false

    return true
  })
  if (taken) {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('The role is taken.'), 'error')

    return false
  }

  return true
}

const openAddRole = () => {
  formMode.value = 'add'
  formName.value = ''
  formSelected.value = []
  formDialog.value = true
}

const openEditRole = () => {
  if (!role.value)
    return

  formMode.value = 'edit'
  formName.value = selectedRoleMeta.value?.name || ''
  formSelected.value = [...selected.value]
  formDialog.value = true
}

const closeForm = () => {
  formDialog.value = false
  saving.value = false
}

const saveRole = () => {
  const name = String(formName.value || '').trim()
  if (!isValidRoleName(name))
    return

  saving.value = true
  const payload = { name, permissions: formSelected.value }

  const request = formMode.value === 'edit'
    ? roleStore.updateRole(selectedRoleMeta.value.id, payload)
    : roleStore.addRole(payload)

  request.then(response => {
    snackbarRef.value.exposevisibleSnackbar(
      i18n.global.t(formMode.value === 'edit' ? 'Updated successfully.' : 'Added successfully.'),
      'success',
    )
    const savedId = formMode.value === 'edit' ? selectedRoleMeta.value.id : response.data.data.id
    closeForm()

    return loadRolesPage().then(() => {
      if (role.value === savedId)
        selected.value = [...payload.permissions]
      else
        role.value = savedId
    })
  }).catch(() => {
    saving.value = false
  }).finally(() => {
    saving.value = false
  })
}

const deleteRole = () => {
  roleStore.deleteRole(selectedRoleMeta.value.id).then(() => {
    role.value = ''
    selected.value = []
    const index = roles.value.findIndex(item => item.id === selectedRoleMeta.value.id)
    if (index !== -1)
      roles.value.splice(index, 1)
    isDeleteDialogVisible.value = false
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('Deleted successfully.'), 'success')
    loadRolesPage()
  })
}

const getRolesObject = () => {
  return roles.value.map(value => ({
    title: value.name,
    value: value.id,
  }))
}

const modulePermissionIds = module => (module.permissions || []).map(item => String(item.id))

const isModuleFullySelected = (module, ids) => {
  const moduleIds = modulePermissionIds(module)

  return moduleIds.length > 0 && moduleIds.every(id => ids.includes(id))
}

const selectModule = module => {
  const moduleIds = modulePermissionIds(module)
  formSelected.value = Array.from(new Set([...formSelected.value, ...moduleIds]))
}

const clearModule = module => {
  const moduleIds = modulePermissionIds(module)
  formSelected.value = formSelected.value.filter(id => !moduleIds.includes(id))
}

const assignedModules = computed(() => {
  return permissionModules.value
    .map(module => ({
      ...module,
      permissions: (module.permissions || []).filter(item => selected.value.includes(String(item.id))),
    }))
    .filter(module => module.permissions.length > 0)
})

const filteredModules = computed(() => {
  const q = permissionSearch.value.trim().toLowerCase()
  if (!q)
    return permissionModules.value

  return permissionModules.value
    .map(module => {
      const permissions = module.permissions.filter(item => {
        return [module.label, item.label, item.name, item.action]
          .filter(Boolean)
          .some(value => String(value).toLowerCase().includes(q))
      })

      return { ...module, permissions }
    })
    .filter(module => module.permissions.length > 0)
})

const actionColor = action => {
  if (action === 'admin')
    return 'error'
  if (action === 'edit')
    return 'warning'
  if (action === 'show')
    return 'info'
  if (action === 'apply')
    return 'secondary'

  return 'primary'
}
</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <h2 class="mb-4">
          {{ $t('Roles & Permissions') }}
        </h2>

        <VCard v-if="canShowRoles">
          <VCardText>
            <VTabs
              v-model="currentTab"
              class="v-tabs-pill"
            >
              <VTab>
                <VIcon
                  start
                  icon="tabler-briefcase"
                />
                {{ $t('Roles') }}
              </VTab>
              <VTab>
                <VIcon
                  start
                  icon="tabler-settings"
                />
                {{ $t('Permissions') }}
              </VTab>
            </VTabs>
          </VCardText>
          <VDivider />
          <VProgressLinear
            v-if="loading"
            indeterminate
            color="primary"
          />

          <VWindow v-model="currentTab">
            <VWindowItem>
              <VCardText>
                <p class="text-body-1 text-medium-emphasis mb-6">
                  {{ $t('roles.roles_tab_hint') }}
                </p>

                <VCard
                  variant="outlined"
                  class="mb-6"
                >
                  <VCardText>
                    <VRow style="display: flex; align-items: center">
                      <VCol
                        lg="6"
                        md="6"
                        cols="12"
                      >
                        <AppSelect
                          v-model="role"
                          :selectLabel="$t('roles.selectRole')"
                          :items="getRolesObject()"
                        />
                      </VCol>
                      <VCol
                        v-if="canEditRoles"
                        lg="2"
                        md="6"
                        cols="12"
                      >
                        <VBtn
                          block
                          color="success"
                          @click="openAddRole"
                        >
                          {{ $t('roles.add_role') }}
                        </VBtn>
                      </VCol>
                      <VCol
                        v-if="canEditRoles"
                        lg="2"
                        md="6"
                        cols="12"
                      >
                        <VBtn
                          block
                          color="primary"
                          :disabled="!role"
                          @click="openEditRole"
                        >
                          {{ $t('roles.edit_role') }}
                        </VBtn>
                      </VCol>
                      <VCol
                        v-if="canDeleteRoles"
                        lg="2"
                        md="6"
                        cols="12"
                      >
                        <VBtn
                          block
                          color="error"
                          :disabled="!role || selectedRoleMeta?.is_delectable == 0"
                          @click="isDeleteDialogVisible = true"
                        >
                          {{ $t('roles.delete_role') }}
                        </VBtn>
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>

                <div v-if="!role">
                  <p class="text-medium-emphasis">
                    {{ $t('roles.select_role_to_assign') }}
                  </p>
                </div>

                <div v-else>
                  <h4 class="text-h5 mb-1">
                    {{ $t('roles.assigned_permissions') }}
                  </h4>
                  <p class="text-body-2 text-medium-emphasis mb-4">
                    {{ selectedRoleMeta?.name }}
                  </p>

                  <VAlert
                    v-if="!assignedModules.length"
                    type="info"
                    variant="tonal"
                  >
                    {{ $t('roles.no_permissions_for_role') }}
                  </VAlert>

                  <VRow v-else>
                    <VCol
                      v-for="module in assignedModules"
                      :key="`assigned-${module.key}`"
                      lg="4"
                      md="6"
                      cols="12"
                    >
                      <VCard>
                        <template #title>
                          {{ module.label }}
                        </template>
                        <VCardText>
                          <div class="d-flex flex-wrap gap-1">
                            <VChip
                              v-for="permission in module.permissions"
                              :key="permission.id"
                              size="small"
                              color="success"
                              variant="tonal"
                            >
                              {{ permission.label }}
                            </VChip>
                          </div>
                        </VCardText>
                      </VCard>
                    </VCol>
                  </VRow>
                </div>
              </VCardText>
            </VWindowItem>

            <VWindowItem>
              <VCardText>
                <p class="text-body-1 text-medium-emphasis mb-4">
                  {{ $t('roles.permissions_tab_hint') }}
                </p>

                <AppTextField
                  v-model="permissionSearch"
                  class="mb-6"
                  :placeholder="$t('roles.search_permissions')"
                  prepend-inner-icon="tabler-search"
                />

                <VRow>
                  <VCol
                    v-for="module in filteredModules"
                    :key="`catalog-${module.key}`"
                    cols="12"
                  >
                    <VCard>
                      <template #title>
                        {{ module.label }}
                      </template>
                      <VCardText>
                        <VTable>
                          <thead>
                            <tr>
                              <th>{{ $t('roles.permission_name') }}</th>
                              <th>{{ $t('roles.permission_action') }}</th>
                              <th>{{ $t('roles.permission_key') }}</th>
                              <th>{{ $t('roles.assigned_roles') }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr
                              v-for="permission in module.permissions"
                              :key="permission.id"
                            >
                              <td>{{ permission.label }}</td>
                              <td>
                                <VChip
                                  size="small"
                                  :color="actionColor(permission.action)"
                                >
                                  {{ $t(`roles.action_${permission.action}`) }}
                                </VChip>
                              </td>
                              <td>
                                <code>{{ permission.name }}</code>
                              </td>
                              <td>
                                <div class="d-flex flex-wrap gap-1">
                                  <VChip
                                    v-for="assignedRole in permission.roles"
                                    :key="`${permission.id}-${assignedRole.id}`"
                                    size="small"
                                    variant="tonal"
                                  >
                                    {{ assignedRole.name }}
                                  </VChip>
                                  <span
                                    v-if="!permission.roles?.length"
                                    class="text-medium-emphasis"
                                  >
                                    {{ $t('roles.no_roles_assigned') }}
                                  </span>
                                </div>
                              </td>
                            </tr>
                          </tbody>
                        </VTable>
                      </VCardText>
                    </VCard>
                  </VCol>
                </VRow>
              </VCardText>
            </VWindowItem>
          </VWindow>
        </VCard>
      </VCol>
    </VRow>

    <VDialog
      v-model="formDialog"
      persistent
      scrollable
      max-width="960"
    >
      <VCard :title="formTitle">
        <DialogCloseBtn @click="closeForm" />
        <VCardText>
          <AppTextField
            v-model="formName"
            class="mb-6"
            :label="$t('roles.roleName')"
            :placeholder="$t('roles.roleName')"
          />

          <div class="d-flex align-center justify-space-between mb-3">
            <h4 class="text-h6">
              {{ $t('Permissions') }}
            </h4>
          </div>

          <VRow>
            <VCol
              v-for="module in permissionModules"
              :key="`form-${module.key}`"
              cols="12"
              md="6"
            >
              <VCard variant="outlined">
                <VCardItem>
                  <VCardTitle class="text-body-1">
                    {{ module.label }}
                  </VCardTitle>
                  <template #append>
                    <div class="d-flex gap-1">
                      <VBtn
                        size="x-small"
                        variant="tonal"
                        color="primary"
                        :disabled="isModuleFullySelected(module, formSelected)"
                        @click="selectModule(module)"
                      >
                        {{ $t('roles.select_all') }}
                      </VBtn>
                      <VBtn
                        size="x-small"
                        variant="tonal"
                        color="secondary"
                        @click="clearModule(module)"
                      >
                        {{ $t('roles.clear_module') }}
                      </VBtn>
                    </div>
                  </template>
                </VCardItem>
                <VCardText>
                  <div
                    v-for="permission in module.permissions"
                    :key="permission.id"
                  >
                    <VCheckbox
                      v-model="formSelected"
                      :label="permission.label"
                      :value="String(permission.id)"
                      hide-details
                      density="compact"
                    />
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>
        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn
            color="secondary"
            variant="tonal"
            @click="closeForm"
          >
            {{ $t('Close') }}
          </VBtn>
          <VBtn
            color="success"
            :loading="saving"
            @click="saveRole"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>

    <VDialog
      v-model="isDeleteDialogVisible"
      persistent
      class="v-dialog-sm"
    >
      <DialogCloseBtn @click="isDeleteDialogVisible = !isDeleteDialogVisible" />

      <VCard>
        <VCardText>
          {{ $t('roles.Are you sure you want to delete this role?') }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn @click="deleteRole">
            {{ $t('delete') }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteDialogVisible = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>

<route lang="yaml">
    meta:
      action: access_roles
      subject: access_roles
</route>
