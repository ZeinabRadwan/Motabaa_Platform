<script setup>
import i18n from '@/plugins/i18n/index.js'
import {
  fetchAccountProfile,
  updateAccountPassword,
  updateAccountProfile,
} from '@/plugins/apis/accountRequest'
import { useSessionStore } from '@/stores/useSessionStore'
import UserInfoView from '@/views/user/UserInfoView.vue'
import SnackbarComponent from '@core/components/SnackbarCustom.vue'
import cuntries from '@core/utils/cuntries'
import { genderTypes } from '@core/utils/generalItems'
import avatar from '@images/avatars/avatar-16.png'
import {
  emailValidator,
  integerValidator,
  lengthValidator,
  passwordValidator,
  requiredValidator,
  confirmedValidator,
} from '@validators'

const sessionStore = useSessionStore()
const snackbarRef = ref(null)
const loading = ref(true)
const saving = ref(false)
const savingPassword = ref(false)
const isEditing = ref(false)
const activeTab = ref('info')
const userData = ref(null)

const refProfileForm = ref()
const refPasswordForm = ref()

const file = ref([])
const fileUpload = ref()
const chooseImage = ref(avatar)
const currentImage = ref('')

const form = ref({
  name: '',
  email: '',
  phone: '',
  birthdate: '',
  gender: null,
  id_or_residence_number: '',
  nationality: '',
  address_building: '',
  address_street: '',
  address_area: '',
  address_city: '',
  address_zipcode: '',
  address_number: '',
  address_unit: '',
})

const passwordForm = ref({
  password: '',
  password_confirmation: '',
})

const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const formErrors = ref({})

const nationalityItems = computed(() => cuntries.map(item => ({
  title: i18n.global.locale.value === 'ar' ? item.country_arNationality : item.country_enNationality,
  value: item.country_code,
})))

const genderOptions = computed(() => genderTypes())

function applyUserToForm(user) {
  userData.value = user
  form.value = {
    name: user.name || '',
    email: user.email || '',
    phone: user.phone || '',
    birthdate: user.birthdate || '',
    gender: user.gender ?? null,
    id_or_residence_number: user.id_or_residence_number ?? '',
    nationality: user.nationality || '',
    address_building: user.address_building || '',
    address_street: user.address_street || '',
    address_area: user.address_area || '',
    address_city: user.address_city || '',
    address_zipcode: user.address_zipcode || '',
    address_number: user.address_number || '',
    address_unit: user.address_unit || '',
  }
  chooseImage.value = user.picture?.file_url || avatar
  currentImage.value = user.picture?.file_url || ''
}

function mergeSessionUser(user) {
  const merged = {
    ...sessionStore.userData,
    ...user,
    fullName: user.name,
    role: sessionStore.userData?.role,
  }

  sessionStore.patchUserData(merged)
}

async function loadProfile() {
  loading.value = true
  try {
    const { data } = await fetchAccountProfile()
    applyUserToForm(data.data)
  }
  finally {
    loading.value = false
  }
}

function openFilePicker() {
  fileUpload.value?.click()
}

function onFileSelected() {
  if (!file.value?.[0])
    return

  const reader = new FileReader()

  reader.onload = () => {
    chooseImage.value = reader.result
  }
  reader.readAsDataURL(file.value[0])
}

function cancelEdit() {
  isEditing.value = false
  formErrors.value = {}
  if (userData.value)
    applyUserToForm(userData.value)
  file.value = []
}

async function saveProfile() {
  const { valid } = await refProfileForm.value?.validate() ?? { valid: false }
  if (!valid)
    return

  if (file.value?.[0]?.size >= 10_000_000) {
    snackbarRef.value?.exposevisibleSnackbar(i18n.global.t('Avatar size should be less than 10 MB!'), 'error')

    return
  }

  saving.value = true
  formErrors.value = {}

  const payload = new FormData()

  Object.entries(form.value).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '')
      payload.append(key, value)
  })

  if (file.value?.[0])
    payload.append('image', file.value[0])

  if (currentImage.value)
    payload.append('current_image', currentImage.value)

  try {
    const { data } = await updateAccountProfile(payload)
    applyUserToForm(data.data)
    mergeSessionUser(data.data)
    isEditing.value = false
    file.value = []
    snackbarRef.value?.exposevisibleSnackbar(i18n.global.t('user_updated'), 'success')
  }
  catch (e) {
    formErrors.value = e.response?.data?.errors || {}
  }
  finally {
    saving.value = false
  }
}

async function savePassword() {
  const { valid } = await refPasswordForm.value?.validate() ?? { valid: false }
  if (!valid)
    return

  savingPassword.value = true
  try {
    await updateAccountPassword(passwordForm.value)
    passwordForm.value = { password: '', password_confirmation: '' }
    snackbarRef.value?.exposevisibleSnackbar(i18n.global.t('The password has been changed.'), 'success')
  }
  catch (e) {
    formErrors.value = e.response?.data?.errors || {}
  }
  finally {
    savingPassword.value = false
  }
}

onMounted(loadProfile)
</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
          <div>
            <h4 class="text-h4 mb-1">
              {{ $t('account.profile_title') }}
            </h4>
            <p class="text-body-2 text-medium-emphasis mb-0">
              {{ $t('account.profile_subtitle') }}
            </p>
          </div>
          <VBtn
            v-if="activeTab === 'info' && !isEditing && !loading"
            color="primary"
            prepend-icon="tabler-edit"
            @click="isEditing = true"
          >
            {{ $t('Edit') }}
          </VBtn>
        </div>
      </VCol>

      <VCol
        v-if="loading"
        cols="12"
        class="d-flex justify-center py-16"
      >
        <VProgressCircular
          indeterminate
          color="primary"
        />
      </VCol>

      <template v-else-if="userData">
        <VCol
          cols="12"
          md="4"
          lg="3"
        >
          <VCard class="pa-6 text-center">
            <VAvatar
              size="96"
              class="mb-4"
            >
              <VImg
                :src="isEditing ? chooseImage : (userData.picture?.file_url || chooseImage)"
                cover
              />
            </VAvatar>
            <h5 class="text-h5 mb-1">
              {{ userData.name }}
            </h5>
            <p class="text-body-2 text-medium-emphasis mb-4">
              {{ userData.roles?.map(r => r.name).join(' · ') }}
            </p>
            <template v-if="isEditing">
              <input
                ref="fileUpload"
                type="file"
                accept="image/*"
                hidden
                @change="onFileSelected"
              >
              <VBtn
                variant="tonal"
                size="small"
                prepend-icon="tabler-camera"
                @click="openFilePicker"
              >
                {{ $t('Change photo') }}
              </VBtn>
            </template>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="8"
          lg="9"
        >
          <VTabs
            v-model="activeTab"
            class="mb-4"
          >
            <VTab value="info">
              {{ $t('account.tab_info') }}
            </VTab>
            <VTab value="password">
              {{ $t('Change Password') }}
            </VTab>
          </VTabs>

          <VWindow v-model="activeTab">
            <VWindowItem value="info">
              <VCard v-if="!isEditing">
                <VCardText>
                  <UserInfoView :user-data="userData" />
                </VCardText>
              </VCard>

              <VCard v-else>
                <VCardText>
                  <VForm
                    ref="refProfileForm"
                    @submit.prevent="saveProfile"
                  >
                    <VRow>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          v-model="form.name"
                          :label="$t('Name')"
                          :rules="[requiredValidator]"
                          :error-messages="formErrors.name"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          v-model="form.phone"
                          dir="ltr"
                          :label="$t('phone')"
                          :rules="[requiredValidator, lengthValidator(form.phone, 9)]"
                          :error-messages="formErrors.phone"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          v-model="form.email"
                          dir="ltr"
                          :label="$t('Email')"
                          :rules="[emailValidator]"
                          :error-messages="formErrors.email"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppDateTimePicker
                          v-model="form.birthdate"
                          :label="$t('birthdate')"
                          :config="{ dateFormat: 'Y-m-d' }"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppSelect
                          v-model="form.gender"
                          :label="$t('gender')"
                          :items="genderOptions"
                          item-title="title"
                          item-value="value"
                          clearable
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          v-model="form.id_or_residence_number"
                          :label="$t('id_or_residence_number')"
                          :rules="[integerValidator]"
                        />
                      </VCol>
                      <VCol cols="12">
                        <AppAutocomplete
                          v-model="form.nationality"
                          :label="$t('nationality')"
                          :items="nationalityItems"
                          item-title="title"
                          item-value="value"
                          clearable
                        />
                      </VCol>

                      <VCol cols="12">
                        <p class="text-subtitle-1 font-weight-medium mb-2">
                          {{ $t('Address') }}
                        </p>
                      </VCol>
                      <VCol
                        cols="12"
                        md="4"
                      >
                        <AppTextField
                          v-model="form.address_building"
                          :label="$t('Building number')"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="4"
                      >
                        <AppTextField
                          v-model="form.address_street"
                          :label="$t('Street name')"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="4"
                      >
                        <AppTextField
                          v-model="form.address_area"
                          :label="$t('El Hay')"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="4"
                      >
                        <AppTextField
                          v-model="form.address_city"
                          :label="$t('City')"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="4"
                      >
                        <AppTextField
                          v-model="form.address_zipcode"
                          :label="$t('Zip')"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="4"
                      >
                        <AppTextField
                          v-model="form.address_unit"
                          :label="$t('Unit number')"
                        />
                      </VCol>
                    </VRow>

                    <div class="d-flex flex-wrap gap-3 mt-6">
                      <VBtn
                        type="submit"
                        color="primary"
                        :loading="saving"
                      >
                        {{ $t('Save') }}
                      </VBtn>
                      <VBtn
                        variant="tonal"
                        :disabled="saving"
                        @click="cancelEdit"
                      >
                        {{ $t('Cancel') }}
                      </VBtn>
                    </div>
                  </VForm>
                </VCardText>
              </VCard>
            </VWindowItem>

            <VWindowItem value="password">
              <VCard>
                <VCardText>
                  <VForm
                    ref="refPasswordForm"
                    @submit.prevent="savePassword"
                  >
                    <VRow>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          v-model="passwordForm.password"
                          dir="ltr"
                          :label="$t('New Password')"
                          :type="isPasswordVisible ? 'text' : 'password'"
                          :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                          :rules="[requiredValidator, passwordValidator]"
                          :error-messages="formErrors.password"
                          @click:append-inner="isPasswordVisible = !isPasswordVisible"
                        />
                      </VCol>
                      <VCol
                        cols="12"
                        md="6"
                      >
                        <AppTextField
                          v-model="passwordForm.password_confirmation"
                          dir="ltr"
                          :label="$t('Confirm Password')"
                          :type="isConfirmPasswordVisible ? 'text' : 'password'"
                          :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                          :rules="[requiredValidator, confirmedValidator(passwordForm.password, passwordForm.password_confirmation)]"
                          @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                        />
                      </VCol>
                    </VRow>
                    <VBtn
                      type="submit"
                      color="primary"
                      class="mt-4"
                      :loading="savingPassword"
                    >
                      {{ $t('Save') }}
                    </VBtn>
                  </VForm>
                </VCardText>
              </VCard>
            </VWindowItem>
          </VWindow>
        </VCol>
      </template>
    </VRow>

    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>

<route lang="yaml">
meta:
  action: read
  subject: Auth
</route>
