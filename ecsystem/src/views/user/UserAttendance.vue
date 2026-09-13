<script setup>
import { watchServerTableFetch } from '@core/utils/tableFetch'
import i18n from '@/plugins/i18n/index.js'
import { useRoute, useRouter } from 'vue-router'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { paginationMeta } from '@/@fake-db/utils'
import { can } from '@layouts/plugins/casl'
import { avatarText } from '@core/utils/formatters'
import { employeesAttendanceApi } from "@/plugins/apis/employeesAttendanceRequest"
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import {
  attendanceItems
} from '@core/utils/generalItems';

const route = useRoute()
const router = useRouter()
const attendanceReqest = employeesAttendanceApi()

const props = defineProps({
  userData: {
    type: Object,
    required: true,
  },
})

const today = new Date();
const formattedToday = today.toLocaleDateString('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
});

const tomorrow = new Date(today);
tomorrow.setDate(today.getDate() + 1);
const formattedTomorrow = tomorrow.toLocaleDateString('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
});

const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
const formattedFirstDay = firstDay.toLocaleDateString('en-CA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
});

const selectedFrom = ref(formattedFirstDay);
const selectedTo = ref(formattedToday);
const totalAttendance = ref(0)
const attendance = ref([])
const exportLoading = ref(false)

const loading = ref({
  attendance: false
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
      title: 'date',
      key: 'attendance_at',
      sortable: false,
    },
    {
      title: '',
      key: 'status',
      sortable: false,
    },
    {
      title: 'attendance',
      key: 'attendance',
      sortable: false,
    },
    {
      title: 'Created By',
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

const fetchAttendance = () => {
  if(selectedFrom.value != '' && selectedFrom.value != null){
    attendanceReqest.fetchAll({
      user_id: props.userData.id,
      from_date: selectedFrom.value,
      to_date: selectedTo.value,
      options: options.value,
      page: options.value.page,
    }).then(response => {
      attendance.value = response.data.data
      totalAttendance.value = response.data.total
      options.value.page = response.data.currentPage

    }).catch(error => {
      loading.value.attendance = false;
      console.error(error)
    })
  }
  else {
    attendance.value = []
  }
};

// 👉 Export Cases
const exportAttendance = () => {
  exportLoading.value = true;
  if(selectedFrom.value != '' && selectedFrom.value != null){
    attendanceReqest.fetchAll({
      user_id: props.userData.id,
      from_date: selectedFrom.value,
      to_date: selectedTo.value,
      export: 'export_attendance',
    }).then(response => {
      exportLoading.value = false;
      window.open(response.data.data.url, '_blank');
    }).catch(error => {
      exportLoading.value = false;
      console.error(error)
    })
  }
}

watch(selectedFrom, query => {
  if(query == null || query == ''){
      selectedFrom.value = formattedFirstDay
  }
})

watch(selectedTo, query => {
  if(query == null || query == ''){
    selectedTo.value = formattedToday
  }
})

watchServerTableFetch(fetchAttendance, {
  filters: () => [selectedFrom.value, selectedTo.value],
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
                  v-model="selectedFrom"
                  :label="$t('from')"
                  :config="{ enableTime: false, dateFormat: 'Y-m-d', disable: [{ from: `${formattedTomorrow}`, to: `9999-12-31` }] }"
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              > 
                <AppDateTimePicker
                  v-model="selectedTo"
                  :label="$t('to')"
                  :key="selectedFrom"
                  :config="{ enableTime: false, dateFormat: 'Y-m-d', enable: [{ from: `${selectedFrom}`, to: `${formattedToday}` }] }"
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
            :items="attendance"
            :loading="loading.attendance"
            :items-length="totalAttendance"
            :headers="translatedHeaders()"
            @update:options="options = $event"
          >
            <template #item.status="{ item }">
              <span :class="attendanceItems().filter((i) => i.value == item.raw.status)[0].class">
                {{ attendanceItems().filter((i) => i.value == item.raw.status)[0].title }}
            </span>
            </template>

            <template #item.attendance="{ item }">
              <div 
                v-if="can('edit_employees-attendance','edit_employees-attendance') && item.raw.status === 1" 
                class="d-flex align-center" 
                style="width: 200px; white-space: pre-wrap;"
              >
                {{ item.raw.from ? $t('from') : '' }} {{ item.raw.from }} {{ item.raw.to ? $t('to') : '' }} {{ item.raw.to }}
              </div>
              <div class="align-center" v-if="item.raw.status === 0">
                <VBtn
                  v-if="item.raw.absent_file"
                  :href="item.raw.absent_file.file_url || item.raw.absent_file"
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
              </div>
            </template>

            <!-- pagination -->
            <template #bottom>
              <VDivider />
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalAttendance) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalAttendance / options.itemsPerPage)"
                  :total-visible="$vuetify.display.xs ? 1 : Math.ceil(totalAttendance / options.itemsPerPage)"
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