<script setup>
import i18n from '@/plugins/i18n/index.js'
import {
  fetchAccountProfile,
  updateAccountPassword,
  updateAccountProfile,
} from '@/plugins/apis/accountRequest'
import { useSessionStore } from '@/stores/useSessionStore'
import AccountProfileOverview from '@/views/account/AccountProfileOverview.vue'
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

const avatarSrc = computed(() => {
  if (isEditing.value)
    return chooseImage.value

  return userData.value?.picture?.file_url || chooseImage.value
})

const roleSummaryLabel = computed(() => userData.value?.roles?.map(r => r.name).join(' · ') || '')

function setActiveTab(tab) {
  if (tab === 'password')
    isEditing.value = false

  activeTab.value = tab
}

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

function onFileSelected(event) {
  const selected = event.target?.files?.[0]
  if (!selected)
    return

  file.value = [selected]
  const reader = new FileReader()

  reader.onload = () => {
    chooseImage.value = reader.result
  }
  reader.readAsDataURL(selected)
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
  <div class="account-profile">
    <header class="account-profile__header">
      <div class="account-profile__header-text">
        <h1 class="account-profile__title">
          {{ $t('account.profile_title') }}
        </h1>
      </div>

      <VBtn
        v-if="!loading && userData && activeTab === 'info' && !isEditing"
        color="primary"
        prepend-icon="tabler-edit"
        class="account-profile__edit-btn"
        @click="isEditing = true"
      >
        {{ $t('Edit') }}
      </VBtn>
    </header>

    <div
      v-if="loading"
      class="d-flex justify-center py-16"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="48"
      />
    </div>

    <template v-else-if="userData">
      <nav
        class="account-profile__tabs"
        aria-label="Profile sections"
      >
        <button
          type="button"
          class="account-profile__tab"
          :class="{ 'account-profile__tab--active': activeTab === 'info' }"
          @click="setActiveTab('info')"
        >
          <VIcon
            icon="tabler-user"
            size="18"
          />
          {{ $t('account.tab_info') }}
        </button>
        <button
          type="button"
          class="account-profile__tab"
          :class="{ 'account-profile__tab--active': activeTab === 'password' }"
          @click="setActiveTab('password')"
        >
          <VIcon
            icon="tabler-lock"
            size="18"
          />
          {{ $t('Change Password') }}
        </button>
      </nav>

      <VRow
        v-if="activeTab === 'info'"
        class="account-profile__columns"
        align="start"
      >
        <VCol
          cols="12"
          md="4"
          lg="4"
          class="account-profile__col-summary"
        >
          <VCard
            class="account-profile__summary-card"
            variant="flat"
            rounded="lg"
          >
            <div class="account-profile__cover">
              <svg
                class="account-profile__cover-wave"
                viewBox="0 0 400 40"
                preserveAspectRatio="none"
                aria-hidden="true"
              >
                <path d="M0,20 Q100,40 200,15 T400,25 L400,40 L0,40 Z" />
              </svg>
            </div>
            <div class="account-profile__summary-body">
              <div class="account-profile__avatar-wrap">
                <VAvatar
                  size="120"
                  class="account-profile__avatar-lg"
                >
                  <VImg
                    :src="avatarSrc"
                    cover
                  />
                </VAvatar>
                <span
                  v-if="!isEditing"
                  class="account-profile__avatar-status"
                  aria-hidden="true"
                />
                <VBtn
                  v-if="isEditing"
                  icon
                  size="x-small"
                  color="primary"
                  class="account-profile__avatar-edit"
                  @click="openFilePicker"
                >
                  <VIcon
                    icon="tabler-camera"
                    size="16"
                  />
                </VBtn>
                <input
                  ref="fileUpload"
                  type="file"
                  accept="image/*"
                  hidden
                  @change="onFileSelected"
                >
              </div>
              <h2 class="account-profile__summary-name">
                {{ userData.name }}
              </h2>
              <span
                v-if="roleSummaryLabel"
                class="account-profile__role-badge"
              >
                {{ roleSummaryLabel }}
              </span>
            </div>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="8"
          lg="8"
          class="account-profile__col-details"
        >
          <AccountProfileOverview
            v-if="!isEditing"
            :user-data="userData"
          />

          <VCard
            v-else
            class="account-profile__form-card"
            variant="flat"
            rounded="lg"
          >
            <div class="account-profile__form-head">
              {{ $t('Edit') }} — {{ $t('account.tab_info') }}
            </div>
            <div class="account-profile__form-body">
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
                    <p class="text-subtitle-2 font-weight-bold mb-2">
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

                <div class="account-profile__actions">
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
            </div>
          </VCard>
        </VCol>
      </VRow>

      <VRow
        v-else
        class="account-profile__columns"
        align="start"
      >
        <VCol cols="12">
          <VCard
            class="account-profile__form-card"
            variant="flat"
            rounded="lg"
          >
            <div class="account-profile__form-head">
              <VIcon
                icon="tabler-lock"
                size="20"
                class="me-1"
              />
              {{ $t('Change Password') }}
            </div>
            <div class="account-profile__form-body">
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
                  class="mt-2"
                  :loading="savingPassword"
                >
                  {{ $t('Save') }}
                </VBtn>
              </VForm>
            </div>
          </VCard>
        </VCol>
      </VRow>
    </template>

    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>

<style lang="scss">
@import '@/styles/account-profile.scss';
</style>

<route lang="yaml">
meta:
  action: read
  subject: Auth
</route>
