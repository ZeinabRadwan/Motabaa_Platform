<script setup>
import { termsApi } from "@/plugins/apis/termsRequest";
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import i18n from '@/plugins/i18n/index.js';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import {
  buildTermOverlapHtml,
  findOverlappingTerm,
  isStartAfterEnd,
  translateApiValidationMessage,
} from '@core/utils/termOverlap';
import {
  requiredValidator
} from '@validators';
import { useRoute, useRouter } from 'vue-router';
import { useSessionStore } from '@/stores/useSessionStore'

const termListStore = termsApi()
const userListStore = useUserListStore()
const sessionStore = useSessionStore()
const route = useRoute()
const router = useRouter()
const refVForm = ref()
const term = ref('')
const title = ref('')
const fromDate = ref('')
const toDate = ref('')
const periodsCount = ref(4)
const evaluationDates = ref(['', '', '', ''])
const assignedUserIds = ref([])
const originalAssignedUserIds = ref([])
const assignAllUsers = ref(false)
const centerStaff = ref([])
const snackbarRef = ref(null);
const isNew = ref(!(Number(route.params.id) > 0));
const existingTerms = ref([])
const serverOverlapAlert = ref('')

const currentUser = computed(() => sessionStore.userData || {})
const currentUserId = computed(() => Number(currentUser.value.id) || null)
const canAssignClassUsers = computed(() => {
  const roleNames = (currentUser.value.roles || []).map(role => String(role.default_name || '').toLowerCase())

  return roleNames.includes('admin') || roleNames.includes('manager')
})

const staffFromItem = item => item?.raw ?? item?.value ?? item

const staffItemTitle = item => {
  const staff = staffFromItem(item)
  if (!staff)
    return ''
  if (staff.title)
    return staff.title
  const role = Array.isArray(staff.roles) && staff.roles.length
    ? (staff.roles[0].name || staff.roles[0].default_name)
    : ''
  const extra = staff.job_title || role

  return extra ? `${staff.name} — ${extra}` : (staff.name || '')
}

const staffItems = computed(() => {
  const map = new Map()
  centerStaff.value.forEach(user => map.set(Number(user.id), user))
  ;(term.value?.users || []).forEach(user => {
    if (!map.has(Number(user.id)))
      map.set(Number(user.id), user)
  })

  return Array.from(map.values())
})

const allStaffIds = computed(() => centerStaff.value.map(user => Number(user.id)))

const mergeAssignedStaffIds = () => {
  const ids = new Set(assignedUserIds.value.map(Number))
  allStaffIds.value.forEach(id => ids.add(id))
  assignedUserIds.value = Array.from(ids)
}

const selectAllStaff = computed({
  get() {
    return assignAllUsers.value
  },
  set(checked) {
    assignAllUsers.value = !!checked
    if (checked) {
      mergeAssignedStaffIds()
      return
    }

    if (isNew.value && currentUserId.value)
      assignedUserIds.value = [currentUserId.value]
    else if (originalAssignedUserIds.value.length)
      assignedUserIds.value = [...originalAssignedUserIds.value]
    else
      assignedUserIds.value = []
  },
})

const isStaffSelected = item => assignedUserIds.value.includes(Number(staffFromItem(item)?.id))

const applyDefaultCreator = () => {
  if (!isNew.value || !currentUserId.value)
    return
  if (!assignedUserIds.value.includes(currentUserId.value))
    assignedUserIds.value = [...assignedUserIds.value, currentUserId.value]
}

const maybePromoteAssignAll = () => {
  if (assignAllUsers.value || isNew.value || !allStaffIds.value.length || !assignedUserIds.value.length)
    return
  if (allStaffIds.value.every(id => assignedUserIds.value.includes(id)))
    assignAllUsers.value = true
}

const loadCenterStaff = () => {
  return userListStore.searchItems({
    excludeParent: true,
    center_staff_only: 1,
    preload: 1,
    limit: 500,
  }).then(response => {
    centerStaff.value = response.data.data || []
    maybePromoteAssignAll()
  }).catch(() => {
    centerStaff.value = []
  })
}

loadCenterStaff()
watch(currentUserId, applyDefaultCreator, { immediate: true })
watch(
  [assignAllUsers, allStaffIds],
  () => {
    if (assignAllUsers.value)
      mergeAssignedStaffIds()
  },
)

const errors = ref({
  email: undefined,
  phone: undefined,
  image: undefined,
})

const overlapAlert = computed(() => {
  if (!fromDate.value || !toDate.value || isStartAfterEnd(fromDate.value, toDate.value))
    return serverOverlapAlert.value

  const overlapping = findOverlappingTerm(
    existingTerms.value,
    fromDate.value,
    toDate.value,
    Number(route.params.id) || 0,
  )

  if (!overlapping)
    return serverOverlapAlert.value

  return buildTermOverlapHtml(
    overlapping.title,
    overlapping.starts_at,
    overlapping.ends_at,
    i18n.global.t('terms.Term date overlapped with another term'),
    i18n.global.locale.value,
  )
})

const startEndOrderValidator = () => {
  if (!fromDate.value || !toDate.value)
    return true

  return !isStartAfterEnd(fromDate.value, toDate.value) || i18n.global.t('Start Date after End Date')
}

const noOverlapValidator = () => {
  if (!overlapAlert.value)
    return true

  return i18n.global.t('terms.Term date overlapped with another term')
}

const termsReady = ref(false)

const loadExistingTerms = () => {
  return termListStore.items().then(response => {
    existingTerms.value = response.data.data || []
  }).catch(() => {
    existingTerms.value = []
  }).finally(() => {
    termsReady.value = true
  })
}

loadExistingTerms()

watch([fromDate, toDate], () => {
  serverOverlapAlert.value = ''
})

if(Number(route.params.id)>0) {
  termListStore.fetchTerm(Number(route.params.id)).then(response => {
    term.value = response.data.data
    title.value = term.value['title'];
    fromDate.value = term.value['starts_at'];
    toDate.value = term.value['ends_at'];
    const dates = Array.isArray(term.value['evaluation_dates']) && term.value['evaluation_dates'].length
      ? [...term.value['evaluation_dates']]
      : [
        term.value['first_evaluation_at'],
        term.value['second_evaluation_at'],
        term.value['third_evaluation_at'],
        term.value['final_evaluation_at'],
      ].filter(Boolean)
    periodsCount.value = Number(term.value['periods_count']) || dates.length || 4
    evaluationDates.value = dates
    assignedUserIds.value = Array.isArray(term.value.user_ids)
      ? term.value.user_ids.map(Number)
      : (Array.isArray(term.value.users) ? term.value.users.map(user => Number(user.id)) : [])
    originalAssignedUserIds.value = [...assignedUserIds.value]
    assignAllUsers.value = !!term.value.assign_all_users
    maybePromoteAssignAll()
  }).catch(() => {
    term.value = null
  })
} else {
  applyDefaultCreator()
}

const resizePeriods = () => {
  let count = Number(periodsCount.value)
  if (!Number.isFinite(count) || count < 1)
    count = 1
  if (count > 12)
    count = 12
  periodsCount.value = count

  const dates = [...evaluationDates.value]
  if (dates.length > count) {
    evaluationDates.value = dates.slice(0, count)
    return
  }

  while (dates.length < count)
    dates.push('')

  evaluationDates.value = dates
}

const onSubmit = () => {
  refVForm.value?.validate().then(async ({ valid: isValid }) => {
    if(!isValid)
      return

    if (!termsReady.value)
      await loadExistingTerms()

    if (isStartAfterEnd(fromDate.value, toDate.value)) {
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('Start Date after End Date'), 'error', 8000)
      return
    }

    if (overlapAlert.value) {
      snackbarRef.value.exposevisibleSnackbar(overlapAlert.value, 'error', 8000)
      return
    }

    const formData = new FormData();

    // Append form field data to the FormData object
    formData.append('center_id', Number(localStorage.getItem('center')));
    formData.append('id', Number(route.params.id));
    formData.append('title', title.value);
    formData.append('starts_at', fromDate.value);
    formData.append('ends_at', toDate.value);
    formData.append('periods_count', periodsCount.value);
    evaluationDates.value.forEach((date, index) => {
      formData.append(`evaluation_dates[${index}]`, date || '')
    })
    formData.append('assign_all_users', assignAllUsers.value ? '1' : '0')
    assignedUserIds.value.forEach(id => {
      formData.append('user_ids[]', id)
    })

    termListStore.putTerm(formData).then(response => {
      if(response.data['status']){
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
        setTimeout(() => {router.push(route.query.to ? String(route.query.to) : '/classes/list')}, window.timeOutAfterSubmit);
      }

    }).catch((e=>{
      const formErrors = e.response?.data?.errors
      errors.value = formErrors
      const raw = formErrors?.error?.[0]
      if (raw && String(raw).includes('Term date overlapped with another term')) {
        serverOverlapAlert.value = translateApiValidationMessage(
          raw,
          key => i18n.global.t(key),
          i18n.global.locale.value,
        )
        return
      }
      snackbarRef.value.exposevisibleSnackbar(
        raw
          ? translateApiValidationMessage(raw, key => i18n.global.t(key), i18n.global.locale.value)
          : (e.response?.data?.message || i18n.global.t('Server Error - 500')),
        'error',
        8000,
      )
    }))
  })
}
</script>

<template>
  <div v-if="isNew || term">
    <VRow>
      <VCol cols="12">
        <!-- 👉 Multiple Column -->
        <VForm 
              ref="refVForm"
              @submit.prevent="onSubmit"
            >
        <VCard class="padding-30p"> 
        <template v-slot:title>
          <VRow>
            <span class="v-col v-col-6 d-flex gap-4"> {{ isNew ? $t('Add Term') : $t('Edit Term') }}</span>

            <VCol
              cols="6"
              class="d-flex gap-4 justify-end"
            >
              <VBtn type="submit">
                {{ isNew ? $t('Save') : $t('Update') }}
              </VBtn>
            </VCol>
          </VRow>

        </template>   
        <VCard class="border-1p">
          <VCardText>
              <VRow>
                <VCol cols="12" md="4">
                  <AppTextField
                    v-model="title"
                    :label="$t('Name')"
                    :rules="[requiredValidator]"
                  />
                </VCol>
                
                <VCol cols="12" md="4">
                  <AppDateTimePicker
                    v-model="fromDate"
                    :label="$t('from')"
                    :config="{ enableTime: false, dateFormat: 'Y-m-d' }"
                    :rules="[requiredValidator, startEndOrderValidator, noOverlapValidator]"
                  />
                </VCol>
                
                <VCol cols="12" md="4">
                  <AppDateTimePicker
                    v-model="toDate"
                    :label="$t('to')"
                    :config="{ enableTime: false, dateFormat: 'Y-m-d' }"
                    :rules="[requiredValidator, startEndOrderValidator, noOverlapValidator]"
                  />
                </VCol>

                <VCol
                  v-if="overlapAlert"
                  cols="12"
                >
                  <VAlert
                    type="error"
                    variant="tonal"
                  >
                    <div v-html="overlapAlert"></div>
                  </VAlert>
                </VCol>

                <VCol cols="12">
                  <VLabel class="mb-2 text-body-2 text-high-emphasis">
                    {{ $t('Class staff') }}
                  </VLabel>
                  <VCheckbox
                    v-if="canAssignClassUsers"
                    v-model="selectAllStaff"
                    :label="$t('Select all class staff')"
                    :disabled="!allStaffIds.length"
                    hide-details
                    class="mb-2"
                  />
                  <AppAutocomplete
                    v-model="assignedUserIds"
                    :items="staffItems"
                    :item-title="staffItemTitle"
                    :item-value="'id'"
                    :label="$t('Select class staff manually')"
                    :disabled="!canAssignClassUsers || assignAllUsers"
                    closable-chips
                    clearable
                    multiple
                    chips
                  >
                    <template #item="{ item, props: itemProps }">
                      <VListItem v-bind="itemProps || {}">
                        <template #prepend>
                          <VCheckboxBtn
                            :model-value="isStaffSelected(item)"
                            :ripple="false"
                            tabindex="-1"
                          />
                        </template>
                      </VListItem>
                    </template>
                  </AppAutocomplete>
                </VCol>

                <VCol cols="12" md="4">
                  <AppTextField
                    v-model.number="periodsCount"
                    type="number"
                    min="1"
                    max="12"
                    :label="$t('goals.periods_count')"
                    :rules="[requiredValidator]"
                    @update:model-value="resizePeriods"
                  />
                </VCol>
                
                <VCol
                  v-for="(_, index) in evaluationDates"
                  :key="index"
                  cols="12"
                  md="4"
                >
                  <AppDateTimePicker
                    v-model="evaluationDates[index]"
                    :label="index === evaluationDates.length - 1 ? $t('goals.final_period') : $t('goals.period_n', { n: index + 1 })"
                    :config="{ enableTime: false, dateFormat: 'Y-m-d' }"
                    :rules="[requiredValidator]"
                  />
                </VCol>
              </VRow>
            </VCardText>
        </VCard>
        </VCard>
      </VForm>
      </VCol>
    </VRow>
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>
<route lang="yaml">
  meta:
    action: edit_qualifying-classes
    subject: edit_qualifying-classes
</route>