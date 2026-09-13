<script setup>
import { VDataTable } from 'vuetify/labs/VDataTable'
import { fieldsObj, formData } from '@/views/plans/fields/planDataFields';


const { rowsData, withHeaders } = defineProps(['rowsData', 'withHeaders']);

const tableHeader = [];
tableHeader['building'] = [
  {
    title: 'Name',
    key: 'name',
    width: '20%',
  },
  {
    title: 'value',
    key: 'value',
    width: '80%',
  },
]

tableHeader['diagnosing_reality_swot_analysis'] = [
  {
    title: 'Name',
    key: 'name',
    width: '15%',
  },
  {
    title: 'value',
    key: 'value',
    width: '85%',
  },
]

let tables = {};
const tablesNames = [
  'building',
  'diagnosing_reality_swot_analysis'
];

if(rowsData && (rowsData.information != null || rowsData.information != '' || rowsData.information != 'null')) {
  tablesNames.forEach((keyTable) => {
    tables[keyTable] = Object.keys(rowsData.information.form[keyTable]).map(key => ({ name: key, value: rowsData.information.form[keyTable][key] }));
  })
}
else {
  tablesNames.forEach((keyTable) => {
    tables[keyTable] = Object.keys(formData[keyTable]).map(key => ({ name: key, value: formData[keyTable][key] }));
  })
}

</script>

<template >
  <VRow>
    <VCol cols="12">
      <div v-if="rowsData.information" v-for="(item, index) in tables" :key="index">
        <VCol cols="12">
          <h4 class="text-h4 mt-4 mb-2"> {{ $t('operational_plan.'+index) }}</h4>
          <VCard>
            <VDataTable
              :items="item"
              disable-pagination
              :headers="tableHeader[index]"
              :items-per-page="-1"
              class="dataTable-hidescroller-y"
              hide-default-footer
            >
              <template v-slot:headers="{ headers }">
              <tr v-if="withHeaders" style="background-color: #f0f0f0; color: #ff0000;">
                  <th
                  class="th-style"
                  v-for="header in headers[0]"
                  :key="header.key"
                  >
                  {{ $t(header.title) }}
                  </th>
              </tr>
              </template>

              <template #item.name="{ item }">
                {{ $t('operational_plan.'+item.raw.name) }}
              </template>
              <template #item.value="{ item }">
                <div v-if="Array.isArray(item.raw.value)" class="align-right">
                      <div v-for="word in item.raw.value" :key="word">{{ $t(word) }}</div>
                  </div>
                  <div  
                    v-else-if="item.raw.value != '' && item.raw.value != null && item.raw.value != 'null' && item.raw.value != [] && item.raw.value != '[]'" 
                    class="d-flex align-right justify-sm-space-between justify-right flex-wrap gap-3 pa-5 pt-3"
                  >
                      {{ $t(item.raw.value) }}
                  </div>
              </template>
              <!-- TODO Refactor this after vuetify provides proper solution for removing default footer -->
              <template #bottom />
            </VDataTable>
          </VCard>
        </VCol>
      </div>
    </VCol>
  </VRow>
</template>


<style scoped >

  .td-style{
    border-left: 1px solid #B0B0B066;
    border-right: 1px solid #B0B0B066;
  }
  .th-style{
    color: white !important;
    background-color: #867DEF !important;
  }
</style>