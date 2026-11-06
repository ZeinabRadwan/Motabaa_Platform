<script setup>
import i18n from '@/plugins/i18n'
import {
  addUserPhoneNumber,
  fetchUserPhoneNumbers,
  removeUserPhoneNumber,
  setPrimaryUserPhoneNumber,
  updateUserPhoneNumber,
  verifyUserPhoneNumber,
} from '@/plugins/apis/userPhoneNumbersRequest'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'
import { can } from '@layouts/plugins/casl'
import { requiredValidator } from '@validators'

const props = defineProps({
  userId: {
    type: Number,
    required: true,
  },
  initialNumbers: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['changed'])

const t = key => i18n.global.t(key)

const numbers = ref([...props.initialNumbers])
const loading = ref(false)
const busyId = ref(null)
const snackbarRef = ref(null)
const canEdit = computed(() => can('edit_parents', 'edit_parents'))

const dialogOpen = ref(false)
const dialogForm = ref({ id: null, phone: '', label: '', verified: false })
const dialogErrors = ref({})
const dialogSaving = ref(false)
const refDialogForm = ref()

const confirmState = ref({ open: false, message: '', action: null })

function applyResponse(response) {
  const data = response?.data?.data
  if (data?.phone_numbers)
    numbers.value = data.phone_numbers
  emit('changed', data)

  return response?.data?.message
}

function errorMessage(error) {
  const errors = error?.response?.data?.errors

  return errors?.phone?.[0] || errors?.error?.[0] || error?.response?.data?.message || t('parent_phone_numbers.error')
}

async function reload() {
  loading.value = true
  try {
    applyResponse(await fetchUserPhoneNumbers(props.userId))
  }
  catch (e) {
    snackbarRef.value?.exposevisibleSnackbar(errorMessage(e), 'error')
  }
  finally {
    loading.value = false
  }
}

function openAdd() {
  dialogForm.value = { id: null, phone: '', label: '', verified: false }
  dialogErrors.value = {}
  dialogOpen.value = true
}

function openEdit(item) {
  dialogForm.value = { id: item.id, phone: item.display_phone || item.phone, label: item.label || '', verified: item.verified, is_primary: item.is_primary }
  dialogErrors.value = {}
  dialogOpen.value = true
}

async function saveDialog() {
  const { valid } = await refDialogForm.value?.validate() ?? { valid: false }
  if (!valid)
    return

  dialogSaving.value = true
  dialogErrors.value = {}
  try {
    const form = dialogForm.value

    const response = form.id
      ? await updateUserPhoneNumber(props.userId, form.id, form.is_primary ? { label: form.label } : { phone: form.phone, label: form.label })
      : await addUserPhoneNumber(props.userId, { phone: form.phone, label: form.label, verified: form.verified })

    const message = applyResponse(response)

    dialogOpen.value = false
    if (message)
      snackbarRef.value?.exposevisibleSnackbar(message, 'success')
  }
  catch (e) {
    dialogErrors.value = { phone: [errorMessage(e)] }
  }
  finally {
    dialogSaving.value = false
  }
}

function askConfirm(message, action) {
  confirmState.value = { open: true, message, action }
}

async function runConfirmed() {
  const action = confirmState.value.action

  confirmState.value = { open: false, message: '', action: null }
  if (action)
    await action()
}

async function runAction(item, request) {
  busyId.value = item.id
  try {
    const message = applyResponse(await request())
    if (message)
      snackbarRef.value?.exposevisibleSnackbar(message, 'success')
  }
  catch (e) {
    snackbarRef.value?.exposevisibleSnackbar(errorMessage(e), 'error')
  }
  finally {
    busyId.value = null
  }
}

function makePrimary(item) {
  const markVerified = !item.verified

  askConfirm(
    t(markVerified ? 'parent_phone_numbers.confirm_primary_verify' : 'parent_phone_numbers.confirm_primary'),
    () => runAction(item, () => setPrimaryUserPhoneNumber(props.userId, item.id, markVerified)),
  )
}

function toggleVerified(item) {
  runAction(item, () => verifyUserPhoneNumber(props.userId, item.id, !item.verified))
}

function removeNumber(item) {
  askConfirm(
    t('parent_phone_numbers.confirm_remove'),
    () => runAction(item, () => removeUserPhoneNumber(props.userId, item.id)),
  )
}

function methodLabel(method) {
  if (!method)
    return ''
  const key = `parent_phone_numbers.method.${method}`
  const label = t(key)

  return label === key ? method : label
}

watch(() => props.initialNumbers, value => {
  numbers.value = [...(value || [])]
})
</script>

<template>
  <VCard class="user-phone-numbers">
    <VCardItem>
      <VCardTitle class="d-flex align-center gap-2">
        <VIcon
          icon="tabler-phone"
          size="20"
        />
        {{ $t('parent_phone_numbers.title') }}
      </VCardTitle>
      <VCardSubtitle class="text-wrap">
        {{ $t('parent_phone_numbers.subtitle') }}
      </VCardSubtitle>
      <template #append>
        <div class="d-flex gap-2">
          <IconBtn
            :title="$t('parent_phone_numbers.refresh')"
            :disabled="loading"
            @click="reload"
          >
            <VIcon icon="tabler-refresh" />
          </IconBtn>
          <VBtn
            v-if="canEdit"
            size="small"
            prepend-icon="tabler-plus"
            @click="openAdd"
          >
            {{ $t('parent_phone_numbers.add') }}
          </VBtn>
        </div>
      </template>
    </VCardItem>

    <VProgressLinear
      v-if="loading"
      indeterminate
      color="primary"
    />

    <VCardText v-if="numbers.length === 0">
      {{ $t('parent_phone_numbers.empty') }}
    </VCardText>

    <VList
      v-else
      lines="two"
      class="py-0"
    >
      <VListItem
        v-for="item in numbers"
        :key="item.id ?? `virtual-${item.phone}`"
        class="user-phone-numbers__item"
      >
        <VListItemTitle class="d-flex flex-wrap align-center gap-2">
          <span
            dir="ltr"
            class="font-weight-medium"
          >{{ item.display_phone || item.phone }}</span>
          <VChip
            v-if="item.is_primary"
            size="x-small"
            color="primary"
            label
          >
            {{ $t('parent_phone_numbers.primary') }}
          </VChip>
          <VChip
            size="x-small"
            :color="item.verified ? 'success' : 'warning'"
            variant="tonal"
            :prepend-icon="item.verified ? 'tabler-circle-check' : 'tabler-alert-circle'"
          >
            {{ item.verified ? $t('parent_phone_numbers.verified') : $t('parent_phone_numbers.unverified') }}
          </VChip>
        </VListItemTitle>
        <VListItemSubtitle>
          <span v-if="item.label">{{ item.label }}</span>
          <span v-if="item.label && item.verification_method"> · </span>
          <span v-if="item.verification_method">{{ methodLabel(item.verification_method) }}</span>
          <span v-if="item.is_virtual"> · {{ $t('parent_phone_numbers.legacy_hint') }}</span>
        </VListItemSubtitle>

        <template
          v-if="canEdit && !item.is_virtual"
          #append
        >
          <div class="d-flex align-center gap-1">
            <VProgressCircular
              v-if="busyId === item.id"
              indeterminate
              size="18"
              width="2"
            />
            <IconBtn
              v-if="!item.is_primary"
              :title="$t('parent_phone_numbers.set_primary')"
              @click="makePrimary(item)"
            >
              <VIcon icon="tabler-star" />
            </IconBtn>
            <IconBtn
              v-if="!item.is_primary"
              :title="item.verified ? $t('parent_phone_numbers.unverify') : $t('parent_phone_numbers.verify')"
              @click="toggleVerified(item)"
            >
              <VIcon :icon="item.verified ? 'tabler-shield-x' : 'tabler-shield-check'" />
            </IconBtn>
            <IconBtn
              :title="$t('parent_phone_numbers.edit')"
              @click="openEdit(item)"
            >
              <VIcon icon="tabler-edit" />
            </IconBtn>
            <IconBtn
              :title="item.is_primary ? $t('parent_phone_numbers.primary_locked') : $t('parent_phone_numbers.remove')"
              :disabled="item.is_primary"
              @click="removeNumber(item)"
            >
              <VIcon
                icon="tabler-trash"
                :color="item.is_primary ? undefined : 'error'"
              />
            </IconBtn>
          </div>
        </template>
      </VListItem>
    </VList>

    <VDialog
      v-model="dialogOpen"
      max-width="480"
    >
      <VCard>
        <VCardTitle class="pa-4">
          {{ dialogForm.id ? $t('parent_phone_numbers.edit') : $t('parent_phone_numbers.add') }}
        </VCardTitle>
        <VCardText>
          <VForm
            ref="refDialogForm"
            @submit.prevent="saveDialog"
          >
            <AppTextField
              v-model="dialogForm.phone"
              dir="ltr"
              :label="$t('parent_phone_numbers.phone')"
              placeholder="05XXXXXXXX"
              :disabled="Boolean(dialogForm.is_primary)"
              :rules="[requiredValidator]"
              :error-messages="dialogErrors.phone"
              class="mb-4"
            />
            <AppTextField
              v-model="dialogForm.label"
              :label="$t('parent_phone_numbers.label')"
              :placeholder="$t('parent_phone_numbers.label_hint')"
              maxlength="50"
              class="mb-2"
            />
            <VCheckbox
              v-if="!dialogForm.id"
              v-model="dialogForm.verified"
              :label="$t('parent_phone_numbers.mark_verified')"
              hide-details
            />
          </VForm>
        </VCardText>
        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn
            variant="tonal"
            :disabled="dialogSaving"
            @click="dialogOpen = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            :loading="dialogSaving"
            @click="saveDialog"
          >
            {{ $t('Save') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="confirmState.open"
      max-width="440"
    >
      <VCard>
        <VCardText class="pt-6">
          {{ confirmState.message }}
        </VCardText>
        <VCardActions class="pa-4 pt-0">
          <VSpacer />
          <VBtn
            variant="tonal"
            @click="confirmState.open = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            color="primary"
            @click="runConfirmed"
          >
            {{ $t('parent_phone_numbers.confirm') }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <SnackbarComponent ref="snackbarRef" />
  </VCard>
</template>

<style lang="scss" scoped>
.user-phone-numbers__item + .user-phone-numbers__item {
  border-block-start: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
