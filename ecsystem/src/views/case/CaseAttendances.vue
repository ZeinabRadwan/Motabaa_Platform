<script setup>
import { watchServerTableFetch } from '@core/utils/tableFetch'
import {
avatarText,
kFormatter,
} from '@core/utils/formatters'
import { VDataTable } from 'vuetify/labs/VDataTable'
import {casesApi} from "@/plugins/apis/casesReqest"
import {attendancesApi} from "@/plugins/apis/attendancesReqest"
import { can } from '@layouts/plugins/casl'
import {
  termItems,
  attendanceItems
} from '@core/utils/generalItems';

const casesReqest = casesApi()
const attendancesReqest = attendancesApi()

const props = defineProps({
  caseData: {
    type: Object,
    required: true,
  },
})


const itemsTerm = ref(null);
const selectedTerm = ref(null);
const selectedFrom = ref(null);
const selectedTo = ref(null);

const defaultFrom = ref('');
const defaultTo = ref('');
const items = ref([]);

const attendanceDateConfig = computed(() => {
  const config = { enableTime: false, dateFormat: 'Y-m-d' }
  if (defaultFrom.value && defaultTo.value) {
    config.enable = [{ from: `${defaultFrom.value}`, to: `${defaultTo.value}` }]
  }
  return config
})

const loading = ref({
  items: false,
  cases: false,
  export: false,
})

const tableHeader = [
  {
    title: 'date',
    key: 'date',
  },
  {
    title: 'attendances.excuse',
    key: 'excuse',
  },
  {
    title: 'attendances.absence',
    key: 'absence',
  },
  {
    title: 'Created By',
    key: 'created_by_name',
  },
]

const fetchAttendances = () => {
  if(selectedTo.value != '' && selectedTo.value != null && selectedFrom.value != '' && selectedFrom.value != null){
    loading.value.items = true;
    attendancesReqest.fetchAllCase({
        case_id: props.caseData.id,
        date_from: selectedFrom.value,
        date_to: selectedTo.value,
        options: { itemsPerPage: 100 },
    }).then(response => {
        loading.value.items = false;
        items.value = response.data.data;

    }).catch(error => {
      loading.value.items = false;
      console.error(error)
    })
  }
};

// 👉 Export Attendances
const exportAttendances = () => {
  loading.value.export = true;
  if(selectedTo.value != '' && selectedTo.value != null && selectedFrom.value != '' && selectedFrom.value != null){
    attendancesReqest.fetchAllCase({
      case_id: props.caseData.id,
      date_from: selectedFrom.value,
      date_to: selectedTo.value,
      export: 'export_attendances',
    }).then(response => {
      loading.value.export = false;
      window.open(response.data.data.url, '_blank');
    }).catch(error => {
      loading.value.export = false;
      console.error(error)
    })
  }
}

watch(selectedTerm, query => {
    if(Number.isInteger(query)){
        const term  = itemsTerm.value.filter((i) => i.id == query);
        selectedFrom.value = term[0].starts_at
        selectedTo.value = term[0].ends_at
        defaultFrom.value = term[0].starts_at
        defaultTo.value = term[0].ends_at
    }else{
        selectedFrom.value = null
        selectedTo.value = null
        defaultFrom.value = ''
        defaultTo.value = ''
        items.value = []
    }
})

watch(selectedFrom, query => {
    if(query == null || query == ''){
        selectedFrom.value = defaultFrom.value
    }
})
watch(selectedTo, query => {
    if(query == null || query == ''){
        selectedTo.value = defaultTo.value
    }
})

watchServerTableFetch(fetchAttendances, {
  filters: () => [selectedFrom.value, selectedTo.value],
})
onMounted(() => {
  termItems().then(data => {
    itemsTerm.value = data
  })

});

</script>

<template>
  <div>
    <VCard>

        <VCardText>
            <VRow>
                <VCol
                cols="12"
                sm="6"
              >
                <AppSelect
                  v-model="selectedTerm"
                  :label="$t('term')"
                  :items="itemsTerm"
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <VLabel class="mb-1">{{ $t('date') }}</VLabel>
                <VRow>
                  <VCol cols="6">
                    <AppDateTimePicker
                      v-model="selectedFrom"
                      :key="defaultFrom"
                      :config="attendanceDateConfig"
                      :placeholder="$t('from')"
                    />
                  </VCol>
                  <VCol cols="6">
                    <AppDateTimePicker
                      v-model="selectedTo"
                      :key="defaultTo"
                      :config="attendanceDateConfig"
                      :placeholder="$t('to')"
                    />
                  </VCol>
                </VRow>
              </VCol>
            </VRow>
        </VCardText>

        <VDivider />

        <VCardText 
          v-if="selectedTerm != '' && selectedTerm != null"
          class="d-flex flex-wrap py-4 gap-4 justify-end"
        >
          <VBtn
            color="success" 
            :loading="loading.export"
            @click="exportAttendances()"
          >
            {{ $t('export_data') }}
          </VBtn>
        </VCardText>

        <VDivider />

        <VCardText>
          <VDataTable
              :items="items"
              items-per-page="-1"
              page="1"
              :loading="loading.items"
              :headers="tableHeader"
              hide-default-footer
          >

          <template v-slot:headers="{ headers }">
          <tr style="background-color: #f0f0f0; color: #ff0000;">
              <th
              class="th-style"
              v-for="header in headers[0]"
              :key="header.key"
              >
              {{ $t(header.title) }}
              </th>
          </tr>
          </template>

          <template #item.date="{ item }">
              <b>
                  {{ item.raw.attendance_at }}
              </b>
          </template>
          <template #item.excuse="{ item }">
              <div >
                  <div class="align-center" v-if="item.raw.status == 0">
                      <VBtn
                          v-if="item.raw.absent_file != null && item.raw.absent_file"
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
              </div>
          </template>
          <template #item.absence="{ item }">
              <span :class="attendanceItems().filter((i) => i.value == item.raw.status)[0].class">
                  {{ attendanceItems().filter((i) => i.value == item.raw.status)[0].title }}
              </span>
          </template>
          <!-- TODO Refactor this after vuetify provides proper solution for removing default footer -->
          <template #bottom />
          </VDataTable>
        </VCardText>
    </VCard>
  </div>

</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 0.75rem;
}

.text-capitalize {
  text-transform: capitalize !important;
}
</style>
