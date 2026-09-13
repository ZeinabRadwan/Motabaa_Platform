<script setup>
import payments from '@/pages/centers/payments/list/index.vue';
import { centersApi } from "@/plugins/apis/centersRequest";
import i18n from '@/plugins/i18n/index.js';
import chartsTabs from '@/views/dashboards/statistics/chartsTabs.vue';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import { cuntriesIndexed } from "@core/utils/cuntries";
import {
  avatarText,
} from '@core/utils/formatters';
import { can } from '@layouts/plugins/casl';
import { useRoute, useRouter } from 'vue-router';

const centerListStore = centersApi()
const route = useRoute()
const router = useRouter()
const center = ref()
const loadingReport = ref(false)
const isDialogVisible = ref(false)
const snackbarRef = ref(null);

centerListStore.fetchCenter(Number(route.params.id), {details: 'all'}).then(response => {
  center.value = response.data.data
})

const printReport = () => {
  
  loadingReport.value = true;
  centerListStore.fetchCenters({
    id: Number(route.params.id),
    export: 'pdf'
  }).then(response => {
    loadingReport.value = false;
    window.open(response.data.data.url, '_blank');
  }).catch(error => {
    loadingReport.value = false;
    console.error(error)
  })
}

const deleteManager = () => {
  centerListStore.deleteManager(Number(route.params.id)).then(response => {
    if(response.data['status']){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      isDialogVisible.value = false
      centerListStore.fetchCenter(Number(route.params.id), {details: 'all'}).then(response => {
        center.value = response.data.data
      })
    }
  })
}

const resolveCenterStatusVariant = stat => {
  if (stat === 'pending')
    return 'warning'
  if (stat === 'active')
    return 'success'
  if (stat === 'inactive')
    return 'secondary'
  
  return 'primary'
}
</script>

<template>
  <VRow v-if="center">
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <VRow>
        <VCol
          cols="12"
          md="12"
          lg="12"
        >
          <VCard>
            <VCardText class="text-center pt-5">
              <!-- 👉 Avatar -->
              <VAvatar
                rounded
                :size="100"
                :color="!center.logo ? 'primary' : undefined"
                :variant="!center.logo ? 'tonal' : undefined"
              >
                <VImg
                  v-if="center.logo"
                  :src="center.logo.file_url"
                />
                <span
                  v-else
                  class="text-5xl font-weight-medium"
                >
                  {{ avatarText(center.title) }}
                </span>
              </VAvatar>
            </VCardText>

            <VCardText>
              <!-- 👉 User Details list -->
              <VList class="card-list mt-2">
                <VListItem>
                  <h6 class="text-h6">
                    {{$t('Name')}}:
                    <span style="font-size: 14px;">
                      {{ center.title }}
                    </span>
                  </h6>
                </VListItem>

                <VListItem>
                  <h6 class="text-h6">
                    {{$t('Phone Number')}}:
                    <span style="font-size: 14px;">
                      {{ center.phone }}
                    </span>
                  </h6>
                </VListItem>
                
                <VListItem>
                  <h6 class="text-h6">
                    {{$t('centers.number_of_cases')}}:
                    <span style="font-size: 14px;">
                      {{ center.number_of_cases }}
                    </span>
                  </h6>
                </VListItem>
                
                <VListItem>
                  <h6 class="text-h6">
                    {{$t('centers.country')}}:
                    <span style="font-size: 14px;">
                      {{ cuntriesIndexed[center.country][i18n.global.locale.value == "ar" ? "country_arName" : "country_enName"] }}
                    </span>
                  </h6>
                </VListItem>

                <VListItem>
                  <h6 class="text-h6">
                    {{$t('centers.address')}}:
                    <span style="font-size: 14px;">
                      {{ center.city }}
                    </span>
                  </h6>
                </VListItem>

                <VListItem>
                  <h6 class="text-h6">
                    {{$t('centers.commission')}}:
                    <span style="font-size: 14px;">
                      {{ center.commission }}
                    </span>
                  </h6>
                </VListItem>

                <VListItem>
                  <h6 class="text-h6">
                    {{$t('centers.cr_number')}}:
                    <span style="font-size: 14px;">
                      {{ center.cr_number }}
                    </span>
                  </h6>
                </VListItem>

                <VListItem>
                  <h6 class="text-h6">
                    {{$t('centers.vat_number')}}:
                    <span style="font-size: 14px;">
                      {{ center.vat_number }}
                    </span>
                  </h6>
                </VListItem>

                <VListItem>
                  <h6 class="text-h6">
                    {{$t('centers.email')}}:
                    <span style="font-size: 14px;">
                      {{ center.email }}
                    </span>
                  </h6>
                </VListItem>

                <VListItem>
                  <h6 class="text-h6">
                    {{$t('centers.url')}}:
                    <span style="font-size: 14px;">
                      {{ center.url }}
                    </span>
                  </h6>
                </VListItem>
                
                <VListItem>
                  <h6 class="text-h6">
                    {{$t('centers.managers')}}:
                    <span style="font-size: 14px;" v-for="(manager, key) in center.managers">
                      {{ manager }}<span v-if="key < (center.managers.length-1)" style="color: red;"> , </span>
                    </span>
                  </h6>
                </VListItem>

                <VListItem>
                  <h6 class="text-h6">
                    {{$t('Status')}}:
                    <VChip
                      :color="resolveCenterStatusVariant(center.deleted_at ? 'inactive' : 'active' )"
                      size="small"
                      label
                      class="text-capitalize"
                    >
                      {{ center.deleted_at ? $t('Inactive') :$t('Active_user') }}
                    </VChip>
                  </h6>
                </VListItem>

              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <VCol
          cols="12"
          md="12"
          lg="12"
        >
          <VCard :title="$t('counters')">
            <!-- 👉 Filters -->
            <VCardText class="d-flex align-center justify-space-between">
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                >
                  <VCard height="130" color="#867DEF">
                    <VCardText>
                      <div class="d-flex gap-x-3">
                        <span style="font-size: 15px; color: white;">{{ $t('centers.cases') }}</span>
                      </div>
                      <div class="mt-2">
                        <h6 style="font-size: 35px; color: white;" class="font-weight-medium">{{ center.n_cases }}</h6>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <VCard height="130" color="#867DEF">
                    <VCardText>
                      <div class="d-flex gap-x-3">
                        <span style="font-size: 15px; color: white;">{{ $t('centers.staff') }}</span>
                      </div>
                      <div class="mt-2">
                        <h6 style="font-size: 35px; color: white;" class="font-weight-medium">{{ center.n_staff }}</h6>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <VCard height="130" color="#867DEF">
                    <VCardText>
                      <div class="d-flex gap-x-3">
                        <span style="font-size: 15px; color: white;">{{ $t('centers.storage') }}</span>
                      </div>
                      <div class="mt-2">
                        <h6 style="font-size: 35px; color: white;" class="font-weight-medium"></h6>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                >
                  <VCard height="130" color="#867DEF">
                    <VCardText>
                      <div class="d-flex gap-x-3">
                        <span style="font-size: 15px; color: white;">{{ $t('centers.whats_app_messages') }}</span>
                      </div>
                      <div class="mt-2">
                        <h6 style="font-size: 35px; color: white;" class="font-weight-medium">{{ center.whats_app_messages }}</h6>
                      </div>
                    </VCardText>
                  </VCard>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </VCol>

    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <VRow>
        <VCol md="12">
          <VCard :title="center.package_name">
            <VCardSubtitle>{{ $t('centers.next_subscription_date') }} {{ center.next_subscription_date }}</VCardSubtitle>
            <VCardText>
              {{ $t('centers.To learn more about the package’s features, other packages, and offers, go to Packages') }}
              <br/>
              <br/>
              <VBtn
                v-if="+center.can_pay"
                class="ml-2 mb-2"
                @click="()=> router.push('/centers/payments/put/'+center.id+'/'+center.package_id)"
              >
                {{ $t('centers.pay_subscription') }}
              </VBtn>
              <VBtn
                class="ml-2 mb-2"
                @click="()=> router.push('/centers/packages/'+center.id)"
              >
                {{ $t('centers.change_subscription') }}
              </VBtn>
            </VCardText>
          </VCard>
        </VCol>

        <VCol md="12">
          <VCard :title="$t('centers.payments')">
            <payments :center-data="center" itemsPerPage="8"/>
          </VCard>
        </VCol>
      </VRow>
    </VCol>

    <VCol
      cols="6"
      sm="6"
    >
      <chartsTabs :daily="center.goals_daily" :monthly="center.goals_monthly" title="activities"/>
    </VCol>

    <VCol
      cols="6"
      sm="6"
    >
      <chartsTabs :daily="center.whats_app_daily" :monthly="center.whats_app_monthly" title="messages"/>
    </VCol>

    <VCol md="12">
      <VCard :title="$t('counters')">
        <VCardText>
          <VRow>
            <VCol md="4">
              <VCard height="140" color="#1A75CF26" @click="printReport()" :loading="loadingReport">
                <VCardText>
                  <div class="mb-4">
                    <VAvatar
                      color="#1A75CFCC"
                      size="50"
                    >
                      <VIcon style="color: white;" icon="tabler-printer" />
                    </VAvatar>
                  </div>
                  <div class="d-flex gap-x-2">
                    <span style="font-size: 16px;">{{ $t('centers.center_report') }}</span>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
            
            <VCol md="4">
              <VCard height="140" color="#8833FF33" @click="()=> router.push('/centers/put/'+center.id)">
                <VCardText>
                  <div class="mb-4">
                    <VAvatar
                      color="#8833FFCC"
                      size="50"
                    >
                      <VIcon style="color: white;" icon="tabler-edit" />
                    </VAvatar>
                  </div>
                  <div class="d-flex gap-x-2">
                    <span style="font-size: 16px;">{{ $t('centers.edit_data') }}</span>
                  </div>
                </VCardText>
              </VCard>
            </VCol>

            <VCol md="4" v-if="can('admin', 'admin')">
              <VCard :disabled="center.managers.length==0" height="140" color="#E62E2E1F" @click="isDialogVisible = true">
                <VCardText>
                  <div class="mb-4">
                    <VAvatar
                      color="#E62E2ECC"
                      size="50"
                    >
                      <VIcon style="color: white;" icon="tabler-trash" />
                    </VAvatar>
                  </div>
                  <div class="d-flex gap-x-2">
                    <span style="font-size: 16px;">{{ $t('centers.delete_manager') }}</span>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>
    </VCol>

    <VDialog
      v-model="isDialogVisible"
      persistent
      class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isDialogVisible = !isDialogVisible" />

      <!-- Dialog Content -->
      <VCard>
        <VCardText>
          {{ $t('centers.Are you sure you want to delete this center manager?') }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn @click="deleteManager">
            {{ $t('delete') }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isDialogVisible = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
        </VCardText>
      </VCard>
    </VDialog>
    <SnackbarComponent ref="snackbarRef" />
  </VRow>
</template>
<route lang="yaml">
  meta:
    action: show_centers
    subject: show_centers
</route>