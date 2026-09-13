<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import i18n from '@/plugins/i18n/index.js'
import { useRoute, useRouter } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { paginationMeta } from '@/@fake-db/utils'
import { can } from '@layouts/plugins/casl'
import { avatarText } from '@core/utils/formatters'
import { employeesAttendanceApi } from "@/plugins/apis/employeesAttendanceRequest"
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import {
  attendanceItems
} from '@core/utils/generalItems';

const rules = [fileList => !fileList || !fileList.length || fileList[0].size < (100 * 1024 * 1024) || 'validtion.less_than_100MB']

const route = useRoute()
const router = useRouter()
const attendanceReqest = employeesAttendanceApi()
const userListStore = useUserListStore()

const today = new Date();
const formattedToday = today.toLocaleDateString('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
});

const refVForm = ref()
const snackbarRef = ref(null);
const searchQuery = ref('')
const search = ref()
const date = ref(formattedToday);
const totalUsers = ref(0)
const users = ref([])
const status = ref({});
const timeFrom = ref({})
const timeTo = ref({})
const fileUpload = ref('')
const fileAttendance = ref()
const file = ref('')
const fileName = ref('file')
const uploading = ref(false)
const uploadPercentage = ref(0)
const errorsMessage = ref({
  file: undefined,
})
const exportLoading = ref(false)

const tomorrow = new Date(today);
tomorrow.setDate(today.getDate() + 1);
const formattedTomorrow = tomorrow.toLocaleDateString('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
});

const loading = ref({
  users: false
})

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})


const translatedHeaders = () => {
  let headers = [
    {
      title: 'employee_attendance.employee',
      key: 'user_name',
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
      width: '35%',
      sortable: false,
    },
    {
      title: 'Created By',
      width: '20%',
      key: 'created_by_name',
      sortable: false,
    },
  ]
  let translatedHeaders = headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }));

  return translatedHeaders;
}

const fetchUsers = () => {
  if(date.value != '' && date.value != null){
    attendanceReqest.fetchAll({
      q: searchQuery.value,
      attendance_at: date.value,
      options: options.value,
      page: options.value.page,
    }).then(response => {
      users.value = response.data.data;
      totalUsers.value = response.data.total
      options.value.page = response.data.currentPage

      users.value.forEach(item => {
        status.value[item.user_id] = item.status;
        timeFrom.value[item.user_id] = item.from;
        timeTo.value[item.user_id] = item.to;
      });

    }).catch(error => {
      loading.value.users = false;
      console.error(error)
    })
  }
};

// 👉 Export Cases
const exportAttendance = () => {
  exportLoading.value = true;
  if(date.value != '' && date.value != null){
    attendanceReqest.fetchAll({
      q: searchQuery.value,
      attendance_at: date.value,
      export: 'export_attendance',
    }).then(response => {
      exportLoading.value = false;
      window.open(response.data.data.url, '_blank');
    }).catch(error => {
      console.error(error)
    })
  }
}

const update = (attendance, fromOrTo) => {

  const formData = new FormData();
  formData.append('id', attendance.id);
  formData.append('from_or_to', fromOrTo);
  formData.append('user_id', attendance.user_id);
  formData.append('attendance_at', date.value);
  formData.append('status', status.value[attendance.user_id]);

  if(fromOrTo == 'from') {
    formData.append('time', timeFrom.value[attendance.user_id]);
  }
  else if(fromOrTo == 'to') {
    formData.append('time', timeTo.value[attendance.user_id]);
  }

  attendanceReqest.putAttendance(formData).then(response => {
    if(response.status == 200){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      users.value.find(item => item.id === attendance.id).status = status.value[attendance.user_id]
    }
  }).catch(error => {
    timeFrom.value[attendance.user_id] = null;
    timeTo.value[attendance.user_id] = null;
    const user_old_attendance = users.value.find(item => item.user_id === attendance.user_id);

    if(user_old_attendance && user_old_attendance.from)
      timeFrom.value[attendance.user_id] = user_old_attendance.from;
    else
      timeFrom.value[attendance.user_id] = null;

    if(user_old_attendance && user_old_attendance.from)
      timeTo.value[attendance.user_id] = user_old_attendance.to;
    else
      timeTo.value[attendance.user_id] = null;
  })
}

const fileUploadfun = (attendance) => {
  fileAttendance.value = attendance
  fileUpload.value.click()
}

const onFileSelected = () => {
  if (file.value[0]) {
    fileName.value = file.value[0].name;
    if(file.value[0].size <= (100 * 1024 * 1024)){

      uploading.value = true
      const formData = new FormData();
      formData.append('id', fileAttendance.value.id);
      formData.append('user_id', fileAttendance.value.user_id);
      formData.append('attendance_at', date.value);
      formData.append('status', fileAttendance.value.status);
      formData.append('file', file.value[0]);

      attendanceReqest.putAttendance(formData, (progress) => {
        uploadPercentage.value = progress
      })
      .then(response => {
        if(response.status == 200) {
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
          userListStore.fetchImage(fileAttendance.value.user_id, {
            attendance_at: date.value
          }).then(response => {
            if(response.data.data) {
              users.value.find(item => item.id === fileAttendance.value.id).absent_file = response.data.data
            }
          })
          uploading.value = false
        }
        file.value = null;
      }).catch(error => {
        errorsMessage.value = error.response.data.errors
        uploading.value = false
      })
    }else{
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.less_than_100MB'), 'error');
    }
  }else{
      fileName.value = 'file';
  }
  errorsMessage.value.file = { file: undefined,};
}

watch(date, query => {
  if(query == null || query == '')
    {users.value = []}
})

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchUsers, {
  search: searchQuery,
  filters: () => [date.value],
  options,
})

</script>

<template>
  <section>
    <VRow class="mb-4">
      <VCol cols="12">
        <VCard>
          <!-- 👉 Filters -->
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                sm="4"
              > 
                <AppDateTimePicker
                  v-model="date"
                  :label="$t('date')"
                  :config="{ enableTime: false, dateFormat: 'Y-m-d', disable: [{ from: `${formattedTomorrow}`, to: `9999-12-31` }] }"
                />
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
                v-if="can('edit_employees-attendance','edit_employees-attendance') && date != '' && date != null"
                color="success" 
                :loading="exportLoading"
                @click="exportAttendance()"
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
            :items="users"
            :loading="loading.users"
            :items-length="totalUsers"
            :headers="translatedHeaders()"
            class="dataTable-hidescroller-y"
            @update:options="onTableOptions"
          >          
            <!-- User -->
            <template #item.user_name="{ item }">
              <div class="d-flex align-right">
                <VAvatar
                  size="34"
                  :variant="!item.raw.user_picture ? 'tonal' : undefined"
                  class="me-3"
                >
                  <VImg
                    v-if="item.raw.user_picture"
                    :src="item.raw.user_picture.file_url"
                  />
                  <span v-else>{{ avatarText(item.raw.user_name) }}</span>
                </VAvatar>

                <div class="d-flex flex-column">
                  <h6 class="text-base">
                    <RouterLink
                      :to="{ name: 'user-view-tab-id', params: { id: item.raw.user_id, tab: 'attendance' } }"
                      class="font-weight-medium user-list-name"
                    >
                      {{ item.raw.user_name }}
                    </RouterLink>
                  </h6>
                </div>
              </div>
            </template>

            <template #item.status="{ item }">
              <div class="align-center" v-if="can('admin_employees-attendance','admin_employees-attendance')">
                <AppSelect
                  v-model="status[item.raw.user_id]"
                  :items="attendanceItems()"
                  :item-title="'title'"
                  :item-value="'value'"
                  :disabled="!can('edit_employees-attendance','edit_employees-attendance')"
                  bg-color="red"
                  @update:modelValue="update(item.raw, null)"
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
                  v-model="status[item.raw.user_id]"
                  :items="attendanceItems()"
                  :item-title="'title'"
                  :item-value="'value'"
                  :disabled="!can('edit_employees-attendance','edit_employees-attendance')"
                  bg-color="red"
                  @update:modelValue="update(item.raw, null)"
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
              <VRow v-if="can('edit_employees-attendance','edit_employees-attendance')">
                  <VCol cols="6" v-if="item.raw.status === 1">
                    <AppTextField
                      v-model="timeFrom[item.raw.user_id]"
                      type="time"
                      clearable
                      clear-icon="tabler-x"
                      style="width: 170px;"
                      :placeholder="$t('from')"
                      @update:modelValue="update(item.raw, 'from')"
                    />
                  </VCol>
                  <VCol cols="6" v-if="item.raw.status === 1">
                    <AppTextField
                      v-model="timeTo[item.raw.user_id]"
                      type="time"
                      :disabled="!timeFrom[item.raw.user_id]"
                      clearable
                      clear-icon="tabler-x"
                      style="width: 170px;"
                      :placeholder="$t('to')"
                      @update:modelValue="update(item.raw, 'to')"
                    />
                  </VCol>
                  <VCol cols="6" v-if="item.raw.status === 0">
                    <VBtn
                      v-if="item.raw.absent_file"
                      :href="item.raw.absent_file.file_url"
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
                  </VCol>
                  <VCol cols="6" v-if="item.raw.status === 0">
                    <VBtn
                      v-if="can('edit_employees-attendance','edit_employees-attendance')"
                      color="error"
                      @click="fileUploadfun(item.raw)"
                    >
                    {{ $t('attendances.Attach an excuse') }}<span v-if="uploading" > ({{ uploadPercentage }}%)</span>
                    </VBtn>
                  </VCol>
              </VRow>
            </template>

            <!-- pagination -->
            <template #bottom>
              <VDivider />
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalUsers) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :disabled="loading.cases"
                  total-visible="5"
                  :length="Math.ceil(totalUsers / options.itemsPerPage)"
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
      </vcol>
    </vrow>
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
    action: access_employees-attendance
    subject: access_employees-attendance
</route>