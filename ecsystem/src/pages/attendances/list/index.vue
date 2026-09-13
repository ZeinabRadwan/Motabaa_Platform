<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import i18n from '@/plugins/i18n/index.js'
import { useRoute, useRouter } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { paginationMeta } from '@/@fake-db/utils'
import { can } from '@layouts/plugins/casl'
import { returnIdUserIfNotAdmin } from "@core/utils/helper";
import { avatarText } from '@core/utils/formatters'
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import { attendancesApi } from "@/plugins/apis/attendancesReqest"
import { termsApi } from "@/plugins/apis/termsRequest";
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import {
  attendanceItems
} from '@core/utils/generalItems';

const rules = [fileList => !fileList || !fileList.length || fileList[0].size < (100 * 1024 * 1024) || 'validtion.less_than_100MB']

const route = useRoute()
const router = useRouter()
const attendancesReqest = attendancesApi()
const userListStore = useUserListStore()
const termListStore = termsApi()

const today = new Date();
const formattedToday = today.toLocaleDateString('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
});

const refVForm = ref()
const snackbarRef = ref(null);
const searchQuery = ref('')
const totalCases = ref(0)
const cases = ref([])
const terms = ref([])
const idRow = ref(null)
const search = ref()
const fileUpload = ref('')
const date = ref(formattedToday);
const teacherItems = ref([])
const teacher = ref(returnIdUserIfNotAdmin() ?? null)
const status = ref({});
const file = ref('')
const fileName = ref('file')
const uploading = ref(false)
const uploadPercentage = ref(0)
const errorsMessage = ref({
  file: undefined,
  image: undefined,
})
const exportLoading = ref(false)
const exportTermLoading = ref(false)
const isExportDialogVisible = ref(false)
const exportedTerm = ref()
const exportedFrom = ref()
const exportedTo = ref()
const defaultExportedFrom = ref()
const defaultExportedTo = ref()

const exportedDateConfig = computed(() => {
  const config = { enableTime: false, dateFormat: 'Y-m-d' }
  if (defaultExportedFrom.value && defaultExportedTo.value) {
    config.enable = [{ from: `${defaultExportedFrom.value}`, to: `${defaultExportedTo.value}` }]
  }
  return config
})

const tomorrow = new Date(today);
tomorrow.setDate(today.getDate() + 1);
const formattedTomorrow = tomorrow.toLocaleDateString('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
});

const loading = ref({
  users: true,
  cases: false,
})

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})


const searchTeachers = params => userListStore.searchItems({
  ...params,
  roles: ['teacher'],
})

watch(date, query => {
  if(query == null || query == '') {
    cases.value = []
    return
  }
  if (query > formattedToday)
    date.value = formattedToday
})

watch(exportedTerm, query => {
    if(Number.isInteger(query)){
        const term  = terms.value.filter((i) => i.id == query);
        exportedFrom.value = term[0].starts_at
        exportedTo.value = term[0].ends_at
        defaultExportedFrom.value = term[0].starts_at
        defaultExportedTo.value = term[0].ends_at
    }else{
        exportedFrom.value = null
        exportedTo.value = null
        defaultExportedFrom.value = ''
        defaultExportedTo.value = ''
    }
})

const fetchCases = () => {
  if(date.value != '' && date.value != null){
    loading.value.cases = true;
    attendancesReqest.fetchAll({
        q: searchQuery.value,
        teacher_id: teacher.value,
        attendance_at: date.value,
        options: options.value,
        page: options.value.page,
    }).then(response => {
        loading.value.cases = false;
        cases.value = response.data.data;
        totalCases.value = response.data.total
        options.value.page = response.data.currentPage

        status.value = []
        cases.value.forEach(item => {
          if(item.attendance[0] && (item.attendance[0].status || item.attendance[0].status == 0))
            status.value[item.id] =  parseInt(item.attendance[0].status);
        });

    }).catch(error => {
      loading.value.cases = false;
      console.error(error)
    })
  }
};

// 👉 Fetch Terms
termListStore.items().then(response => {
  terms.value = response.data.data
}).catch(() => {
})

// 👉 Export Cases
const exportAttendance = (type) => {
  let exportData = {};
  if(type == 'attendances') {
    exportLoading.value = true;
    exportData = {
        q: searchQuery.value,
        teacher_id: teacher.value,
        attendance_at: date.value,
        export: 'attendances',
    };
  }
  else if(type == 'term_attendances') {
    exportTermLoading.value = true;
    exportData = {
        date_from: exportedFrom.value,
        date_to: exportedTo.value,
        export: 'term_attendances',
    };
  }
  
  if(date.value != '' && date.value != null){
    attendancesReqest.export(exportData).then(response => {
      if(type == 'attendances') {
        exportLoading.value = false;
      }
      else if(type == 'term_attendances') {
        exportTermLoading.value = false;
        isExportDialogVisible.value = false;
      }
      window.open(response.data.data.url, '_blank');
    }).catch(error => {
      if(type == 'attendances') {
        exportLoading.value = false;
      }
      else if(type == 'term_attendances') {
        exportTermLoading.value = false;
        isExportDialogVisible.value = false;
      }
      console.error(error)
    })
  }
}

const exportTermDialog = () => {
  exportedTerm.value = null
  exportedFrom.value = null
  exportedTo.value = null
  defaultExportedFrom.value = null
  defaultExportedTo.value = null
  isExportDialogVisible.value = true
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchCases, {
  search: searchQuery,
  filters: () => [teacher.value, date.value],
  options,
})

const translatedHeaders = () => {
  let headers = [
    {
      title: 'Cases',
      key: 'case',
      width: '30%',
      sortable: false,
    },
    {
      title: 'attendance',
      key: 'status',
      width: '15%',
      sortable: false,
    },
    {
      title: '',
      key: 'attendance',
      width: '30%',
      sortable: false,
    },
    {
      title: 'Created By',
      width: '25%',
      key: 'created_by_name',
    },
  ]
  let translatedHeaders = headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }));

  return translatedHeaders;
}

const update = (event, child_case, id) => {
  let data = {
    case_id: child_case.id,
    attendance_at: date.value,
    status: event,
  }
  attendancesReqest.put(data, id)
  .then(response => {
    if(response.status == 200){
      cases.value.find(item => item.id === response.data.data.case_id).attendance = [response.data.data]
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('updated_successfully'), 'success');
    }
  }).catch(error => {
    const old_attendance_case = cases.value.find(item => item.id === child_case.id).attendance;
    old_attendance_case.length > 0 ? status.value[child_case.id] = old_attendance_case[0].status : status.value[child_case.id] = null
  })

}

const fileUploadfun = (clickRow) => {
  idRow.value = clickRow
  fileUpload.value.click()
}

const onFileSelected = () => {
  if (file.value[0]) {
    fileName.value = file.value[0].name;
    if(file.value[0].size <= (100 * 1024 * 1024)){

      uploading.value = true
      const formData = new FormData();
      formData.append('file', file.value[0]);

      attendancesReqest.putFile(idRow.value, formData, (progress) => {
        uploadPercentage.value = progress
      })
      .then(response => {
        if(response.status == 200){
          let attendanceData = response.data.data
          const row = cases.value.find(item => item.id === attendanceData.case_id)
          if(row) {
            row.attendance = [attendanceData]
          }
          uploading.value = false
        }
        file.value = null;
      }).catch(error => {
        errorsMessage.value = error.response.data.errors
      })
    }else{
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.less_than_100MB'), 'error');
    }
  }else{
      fileName.value = 'file';
  }
  errorsMessage.value.file = { file: undefined,};
}

</script>

<template>
  <section>
    <VRow class="mb-4">
      <VCol cols="12">
        <VCard>
          <VCardText>
            <VRow>
              <VCol cols="8">
                <span class="text-h4">{{ $t('Attendance') }}</span>
              </VCol>
              <VCol cols="4" class="d-flex gap-4 justify-end"> 
                <VBtn
                  v-if="can('edit_attendance','edit_attendance')"
                  color="success" 
                  :loading="exportTermLoading"
                  @click="exportTermDialog()"
                >
                  {{ $t('report_monthly_or_termly') }}
                </VBtn>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12">
        <VCard>
          <!-- 👉 Filters -->
          <VCardText>
            <VRow>
              <VCol
                v-if="!returnIdUserIfNotAdmin() || can('admin_attendance','admin_attendance')"
                cols="12"
                sm="4"
              > 
                <AppAutocomplete
                    v-model="teacher"
                    :server-search="searchTeachers"
                    :item-title="'name'"
                    :item-value="'id'"
                    :label="$t('teacher')"
                    clear-icon="tabler-x"
                    clearable
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <VLabel class="mb-1 text-body-2 text-high-emphasis">
                  {{ $t('date') }}
                </VLabel>
                <AppDateTimePicker
                  v-model="date"
                  :placeholder="$t('Gregorian')"
                  :config="{ enableTime: false, dateFormat: 'Y-m-d', disable: [{ from: `${formattedTomorrow}`, to: `9999-12-31` }] }"
                />
                <div class="mt-3">
                  <AppHijriDate
                    v-model="date"
                    :label="'Hijri'"
                  />
                </div>
              </VCol>
              <VCol
                cols="12"
                sm="4"
              > 
              <AppTextField
                  v-model="searchQuery"
                  :label="$t('Search')"
                  density="compact"
                />
              </VCol>
            </VRow>
          </VCardText>

          <!-- <VDivider />

          <VCardText class="d-flex flex-wrap py-4 gap-4">
            <div class="me-3 d-flex gap-3">
              <h5 class="text-h5">{{$t('goals.goal_analysis')}}</h5>
            </div>
            <VSpacer />
          </VCardText> -->

          <VDivider />

          <VCardText class="d-flex flex-wrap py-4 gap-4">
            <div class="me-3 d-flex gap-3">
              <AppSelect
                :model-value="options.itemsPerPage"
                :items="[
                  { value: 10, title: '10' },
                  { value: 25, title: '25' },
                  { value: 50, title: '50' },
                  { value: 100, title: 'All' },
                ]"
                style="width: 6.25rem;"
                @update:model-value="options.itemsPerPage = parseInt($event, 10)"
              />
            </div>
            <VSpacer />

            <div class="justify-end d-flex align-center flex-wrap gap-4">
              <!-- 👉 Add user button -->
              <VBtn
                v-if="can('edit_attendance','edit_attendance') && date != '' && date != null"
                color="success" 
                :loading="exportLoading"
                @click="exportAttendance('attendances')"
              >
                {{ $t('export_data') }}
              </VBtn>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="cases"
            :loading="loading.cases"
            :items-length="totalCases"
            :headers="translatedHeaders()"
            class="dataTable-hidescroller-y dataTable"
            @update:options="onTableOptions"
          >
            <!-- Case -->
            <template #item.case="{ item }">
              <div class="d-flex align-center">
                <VAvatar
                  size="34"
                  :variant="!item.raw.picture ? 'tonal' : undefined"
                  class="me-3"
                >
                  <VImg
                    v-if="item.raw.picture"
                    :src="item.raw.picture.file_url"
                  />
                  <span v-else>{{ avatarText(item.raw.name) }}</span>
                </VAvatar>

                <div class="d-flex flex-column">
                  <h6 class="text-base">
                    <RouterLink
                      :to="{ name: 'cases-view-tab-id', params: { id: item.raw.id, tab: 'attendance' } }"
                      class="font-weight-medium user-list-name"
                    >
                      {{ item.raw.name }}
                    </RouterLink>
                  </h6>
                </div>
              </div>
            </template>

            <template #item.status="{ item }">
              <div class="align-center" v-if="can('admin_attendance','admin_attendance')">
                <AppSelect
                  v-model="status[item.raw.id]"
                  :items="attendanceItems()"
                  :item-title="'title'"
                  :item-value="'value'"
                  :disabled="!can('edit_attendance','edit_attendance')"
                  bg-color="red"
                  @update:modelValue="update($event, item.raw, item.raw.attendance[0] ? item.raw.attendance[0].id : '-1')"
                  clear-icon="tabler-x"
                  clearable
                >
                  <template v-slot:selection="{ item }">
                    <span :class="item.raw.class" class="font-weight-bold">
                      {{ item.title }}
                    </span>
                  </template>
                </AppSelect>
              </div>
              <div class="align-center" v-else>
                <AppSelect
                  v-model="status[item.raw.id]"
                  :items="attendanceItems()"
                  :item-title="'title'"
                  :item-value="'value'"
                  :disabled="!can('edit_attendance','edit_attendance')"
                  bg-color="red"
                  @update:modelValue="update($event, item.raw, item.raw.attendance[0] ? item.raw.attendance[0].id : '-1')"
                >
                  <template v-slot:selection="{ item }">
                    <span :class="item.raw.class" class="font-weight-bold">
                      {{ item.title }}
                    </span>
                  </template>
                </AppSelect>
              </div>
            </template>

            <template #item.attendance="{ item }">
              <div class="d-flex align-center" v-if="status[item.raw.id] == 0">
                <VBtn
                  v-if="item.raw.attendance.length > 0 && item.raw.attendance != [] && item.raw.attendance != null && item.raw.attendance[0].absent_file"
                  :href="item.raw.attendance[0].absent_file.file_url"
                  target="_blank"
                  color="error"
                  variant="text"
                  rel="noopener noreferrer"
                >
                  {{ $t('attendances.Download the excuse') }}
                  <VIcon
                    end
                    icon="tabler-download"
                  />
                </VBtn>
                <VBtn
                    v-else
                    color="error"
                    variant="text"
                  >
                    {{ $t('attendances.Without excuse') }}
                </VBtn>
                <VBtn
                  v-if="can('edit_attendance','edit_attendance')"
                  color="error"
                  @click="fileUploadfun(item.raw.attendance[0].id)"
                >
                  {{ $t('attendances.Attach an excuse') }}<span v-if="uploading" > ({{ uploadPercentage }}%)</span>
                </VBtn>
              </div>
            </template>

            <template #item.created_by_name="{ item }">
              <div v-if="item.raw.attendance.length > 0 && item.raw.attendance != [] && item.raw.attendance != null">
                {{ item.raw.attendance[0].created_by_name }}
              </div>
            </template>

            <!-- pagination -->
            <template #bottom>
              <VDivider />
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalCases) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :disabled="loading.cases"
                  total-visible="5"
                  :length="Math.ceil(totalCases / options.itemsPerPage)"
                >
                  <template #prev="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      Previous
                    </VBtn>
                  </template>

                  <template #next="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      Next
                    </VBtn>
                  </template>
                </VPagination>
              </div>
            </template>
          </VDataTableServer>
          <!-- SECTION -->
        </VCard>
      </VCol>
    </VRow>
    
    <VDialog
      v-model="isExportDialogVisible"
      persistent
      class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isExportDialogVisible = !isExportDialogVisible" />

      <VCard :title="$t('report_monthly_or_termly')">
        <VForm 
            ref="refVForm"
            @submit.prevent="exportAttendance('term_attendances')"
          >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                sm="12"
              >
                <VRow>
                  <VCol
                    cols="12"
                    class="mt-1"
                  >
                    <AppSelect
                      v-model="exportedTerm"
                      :label="$t('Term')"
                      :items="terms"
                      :item-title="item => item.title"
                      :item-value="item => item.id"
                      clear-icon="tabler-x"
                      clearable
                      class="pa-1"
                    >
                    </AppSelect>
                  </VCol>

                  <VCol cols="6">
                    <AppDateTimePicker
                      v-model="exportedFrom"
                      :key="`gregorian-from-${defaultExportedFrom}`"
                      :config="exportedDateConfig"
                      :label="$t('from')"
                      :placeholder="$t('Gregorian')"
                    />
                    <div class="mt-3">
                      <AppHijriDate
                        :key="`hijri-from-${defaultExportedFrom}`"
                        v-model="exportedFrom"
                        :label="'Hijri'"
                      />
                    </div>
                  </VCol>

                  <VCol cols="6">
                    <AppDateTimePicker
                      v-model="exportedTo"
                      :key="`gregorian-to-${defaultExportedTo}`"
                      :config="exportedDateConfig"
                      :label="$t('to')"
                      :placeholder="$t('Gregorian')"
                    />
                    <div class="mt-3">
                      <AppHijriDate
                        :key="`hijri-to-${defaultExportedTo}`"
                        v-model="exportedTo"
                        :label="'Hijri'"
                      />
                    </div>
                  </VCol>
                </VRow>
              </VCol>
            </VRow>
          </VCardText>
          <VCardText class="d-flex justify-end gap-3 flex-wrap">
              <VBtn type="submit" :disabled="exportedTerm == '' || exportedTerm == null">
                  {{ $t('export') }}
              </VBtn>
              <VBtn
              color="secondary"
              variant="tonal"
              @click="isExportDialogVisible = false"
              >
                  {{ $t('Close') }}
              </VBtn>
          </VCardText>
        </VForm>
      </VCard>
    </VDialog>

    <VFileInput v-show="false" v-model="file" ref="fileUpload" @change="onFileSelected" />
    <SnackbarComponent ref="snackbarRef" />
  </section>
</template>

<style lang="scss">
.app-user-search-filter {
  inline-size: 31.6rem;
}

.user-list-name:not(:hover) {
  color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
}

</style>
<route lang="yaml">
  meta:
    action: access_attendance
    subject: access_attendance
</route>