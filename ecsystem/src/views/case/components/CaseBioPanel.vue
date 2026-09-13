<script setup>
import i18n from '@/plugins/i18n/index.js'
import {
avatarText,
kFormatter,
} from '@core/utils/formatters'
import {casesApi} from "@/plugins/apis/casesReqest"
import {convertGregorianToHijri} from "@core/utils/helper";
import { termItems } from '@core/utils/generalItems'
import { can } from '@layouts/plugins/casl'
import { useRoute, useRouter } from 'vue-router';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';

const casesReqest = casesApi()

const props = defineProps({
  userData: {
    type: Object,
    required: true,
  },
})

const route = useRoute();
const router = useRouter();
const reportLoading = ref(false);
const planType = ref();
const snackbarRef = ref(null);

const termTitle = item => item?.title || item?.name || ''
const centerTerms = ref([])

const termOptions = computed(() => {
  const seen = new Map()
  const add = item => {
    if (!item || item.id == null || item.id === '')
      return
    const id = Number(item.id)
    const title = termTitle(item)
    if (!id || !title || seen.has(id))
      return
    seen.set(id, { id, value: id, title })
  }

  centerTerms.value.forEach(add)
  ;(props.userData.terms || []).forEach(add)
  add(props.userData.current_term)

  return Array.from(seen.values())
})

const currentTermId = Number(props.userData.current_term?.id) || null
const term = ref(currentTermId)

termItems().then(data => {
  centerTerms.value = Array.isArray(data) ? data : []
}).catch(() => {
  centerTerms.value = []
})

const deletedCase = ref(props.userData.deleted_at != null && props.userData.deleted_at != '');

const resolveUserStatusVariant = stat => {
  if (stat === 'pending')
    return 'warning'
  if (stat === 'active')
    return 'success'
  if (stat === 'inactive')
    return 'secondary'
  
  return 'primary'
}

const deleteCase = () => {
  casesReqest.delete(Number(route.params.id)).then(response => {
    deletedCase.value = true
  })
}

const restoreCase = () => {
  casesReqest.restore(Number(route.params.id)).then(response => {
    deletedCase.value = false
  })
}

const pdfFile = pdfType => {
  
  reportLoading.value = true;
  casesReqest.pdfFile(Number(route.params.id), pdfType).then(response => {
    window.open(response.data.data.url, '_blank');
    reportLoading.value = false;
  }).catch(error => {
    reportLoading.value = false;
    console.error(error)
  })
}

if(props.userData.plans_types.length>0) {
  planType.value = props.userData.plans_types[0].category
}

const goToServicePage = () => {
  if(term.value != null && term.value != '' && planType.value != null && planType.value != ''){
    if(planType.value == 'educational')
      router.push({ name: 'goals-list-case-term', params: { case: Number(route.params.id), term: term.value } });
    else if(planType.value == 'independent')
      router.push({ name: 'independent_goals-list-case-term', params: { case: Number(route.params.id), term: term.value } });
    else
      router.push({ name: 'services-list-case-term-service_type', params: { case: Number(route.params.id), term: term.value, service_type: planType.value } });
  }
  else {
    snackbarRef.value.exposevisibleSnackbar(i18n.global.t('cases.you must choose term and plan'), 'error');
  }
}

if (!props.userData.picture) {
  casesReqest.fetchImage(Number(route.params.id)).then(response => {
    if(response.data.data) {
      props.userData.picture = response.data.data
    }
  })
}
</script>

<template>
  <section>
    <VRow>
      <!-- SECTION User Details -->
      <VCol cols="12">
        <VCard v-if="props.userData">
          <VCardText class="text-center pt-15">
            <!-- 👉 Avatar -->
            <VAvatar
              rounded
              :size="100"
              :color="!props.userData.avatar ? 'primary' : undefined"
              :variant="!props.userData.avatar ? 'tonal' : undefined"
            >
              <VImg
                v-if="props.userData.picture"
                :src="props.userData.picture.file_url"
              />
              <span
                v-else
                class="text-5xl font-weight-medium"
              >
                {{ avatarText(props.userData.name) }}
              </span>
            </VAvatar>


            <!-- 👉 Role chip -->
            <!-- <VChip
              label
              :color="resolveUserRoleVariant(props.userData.role).color"
              size="small"
              class="text-capitalize mt-3"
            >
              {{ props.userData.role }}
            </VChip> -->
          </VCardText>

          <VCardText>
            <!-- 👉 User Details list -->
            <VList class="card-list mt-2">
              <VListItem>
                <VListItemTitle>
                  <h6 class="text-h6">
                    {{$t('Name')}}:
                    <span class="text-body-1">
                      {{ props.userData.name }}
                    </span>
                  </h6>
                </VListItemTitle>
              </VListItem>

              <VListItem>
                <VListItemTitle>
                  <h6 class="text-h6">
                    {{ $t('Birth date')}}:

                    <span class="text-body-1" dir="rtl">
                      {{ convertGregorianToHijri(props.userData.birthdate) }}
                    </span>
                  </h6>
                </VListItemTitle>
              </VListItem>

              <VListItem>
                <VListItemTitle>
                  <h6 class="text-h6">
                    {{$t('Types of disability')}}:
                    <span class="text-capitalize text-body-1">{{ props.userData.disability_type_names.toString() }}</span>
                  </h6>
                </VListItemTitle>
              </VListItem>

              <VListItem>
                <VListItemTitle>
                  <h6 class="text-h6">
                    {{$t('Services provided')}}:
                    <span class="text-body-1">
                      {{ props.userData.services.toString() }}
                    </span>
                  </h6>
                </VListItemTitle>
              </VListItem>

              <VListItem>
                <VListItemTitle>
                  <h6 class="text-h6">
                    {{$t('Registration type')}}:
                    <span v-if="props.userData.beneficiary_number != null && props.userData.beneficiary_number != '' && props.userData.beneficiary_number != 'null'" class="text-body-1">{{ $t('Beneficiary') }}</span>
                    <span v-else class="text-body-1">{{ $t('Not beneficiary') }}</span>
                  </h6>
                </VListItemTitle>
              </VListItem>

              <VListItem v-if="props.userData.beneficiary_number != null && props.userData.beneficiary_number != '' && props.userData.beneficiary_number != 'null'" >
                <VListItemTitle>
                  <h6 class="text-h6">
                    {{$t('Insurance Number')}}:
                    <span class="text-body-1">{{ props.userData.beneficiary_number }}</span>
                  </h6>
                </VListItemTitle>
              </VListItem>

              <VListItem>
                <VListItemTitle>
                  <h6 class="text-h6">
                    {{$t('Status')}}:
                    <VChip
                      :color="resolveUserStatusVariant(deletedCase? 'inactive' : 'active' )"
                      size="small"
                      label
                      class="text-capitalize"
                    >
                    {{ deletedCase? $t('Inactive') :$t('Active_user') }}
                  </VChip>
                  </h6>
                </VListItemTitle>
              </VListItem>
            </VList>
          </VCardText>

          <!-- 👉 Edit and Suspend button -->
          <VCardText class="d-flex justify-center">
            <VBtn
              v-if="can('edit_cases','edit_cases')"
              variant="elevated"
              class="me-4"
              :to="{ name:'cases-edit-id', params:{ id:props.userData.id }, query:{ to:route.path} }"
            >
              {{ $t('Edit') }}
            </VBtn>

            <VBtn
              variant="tonal"
              color="error"
              @click="deleteCase"
              class="me-4"
              v-if="!deletedCase && can('admin_cases','admin_cases')"
            >
              {{ $t('delete') }}
            </VBtn>
            <VBtn
              variant="tonal"
              color="success"
              @click="restoreCase"
              class="me-4"
              v-if="deletedCase && can('admin_cases','admin_cases')"
            >
              {{ $t('Active') }}
            </VBtn>

            <VBtn
              v-if="can('edit_cases','edit_cases')"
              variant="tonal"
              color="secondary"
              :loading="reportLoading"
              >
              {{ $t('PDF Files') }}

                <VMenu activator="parent">
                  <VList>
                    <VListItem @click="pdfFile('general_data')">
                      <VListItemTitle>{{ $t('General Data') }}</VListItemTitle>
                    </VListItem>
                    <VListItem @click="pdfFile('case_study')">
                      <VListItemTitle>{{ $t('Case Study') }}</VListItemTitle>
                    </VListItem>
                    <VListItem @click="pdfFile('psychological_study')">
                      <VListItemTitle>{{ $t('Psychological study') }}</VListItemTitle>
                    </VListItem>
                  </VList>
                </VMenu>
              </VBtn>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12">
        <VCard v-if="props.userData">
          <VCardText class="pt-15">
            <VRow>
              <VCol
                cols="12"
                sm="12"
              >
                <AppAutocomplete
                  v-model="term"
                  :items="termOptions"
                  item-value="id"
                  item-title="title"
                  :label="$t('term')"
                />
              </VCol>

              <VCol
                cols="12"
                sm="12"
              >
                <AppAutocomplete
                  v-model="planType"
                  :items="props.userData.plans_types"
                  item-value="category"
                  :item-title="item => $t('cases.'+item.category)"
                  :label="$t('cases.plan_type')"
                />
              </VCol>
            </VRow>
          </VCardText>
          <VCardText class="d-flex">
            <VBtn
              variant="elevated"
              class="me-4"
              @click="goToServicePage"
            >
              {{ $t('View') }}
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
      <!-- !SECTION -->
    </VRow>
    <SnackbarComponent ref="snackbarRef" />
  </section>
</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 0.75rem;
}

.text-capitalize {
  text-transform: capitalize !important;
}
</style>
