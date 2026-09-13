<script setup>

import { VDataTable } from 'vuetify/labs/VDataTable'

const { rowsData, disabledEL } = defineProps(['rowsData', 'disabledEL']);
const selectedRadio = ref('primary')

const colorsRadio = [
  {name:'case_good', color: 'Success', value: "good"},
  {name:'case_med', color: 'Info', value: "med"},
  {name:'case_weak', color: 'Warning', value: "weak"},
  {name:'case_not_able', color: 'Error', value: "notAble"},
]

const trans = {
  "good":"case_good",
  "med":"case_med",
  "weak":"case_weak",
  "notAble":"case_not_able",

}

const tableHeader = [
  {
    title: 'Name',
    key: 'name',
  },
  {
    title: 'Evaluation',
    key: 'evaluation',
  },
  {
    title: 'Notes',
    key: 'notes',
  },
]

const rows = ref(rowsData)


defineExpose({
    rows
})

</script>

<template >
    <VCardText>
        <VCard>
        <VDataTable
        :items="Object.values(rowsData)"
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

        <template #item.name="{ item }">
            <div class="d-flex">
            {{ $t(item.raw.name) }}
            </div>
        </template>
        <template #item.evaluation="{ item }">
            <div v-if="trans[rows[item.raw.name]['evaluation']] == 'case_good'">
            {{  $t('case_good') }}
            </div>
            <div v-if="trans[rows[item.raw.name]['evaluation']] == 'case_med'">
            {{  $t('case_med') }}
            </div>
            <div v-if="trans[rows[item.raw.name]['evaluation']] == 'case_weak'">
            {{  $t('case_weak') }}
            </div>
            <div v-if="trans[rows[item.raw.name]['evaluation']] == 'case_not_able'">
            {{  $t('case_not_able') }}
            </div>
        </template>
        <template #item.notes="{ item }">
            <div class="d-flex">
              {{ rows[item.raw.name]['notes'] }}
            </div>
        </template>
        <!-- TODO Refactor this after vuetify provides proper solution for removing default footer -->
        <template #bottom />
        </VDataTable>
    </VCard>
    </VCardText>
</template>


<style scoped >
  @media (min-width: 300px) and (max-width: 599px) {
    .v-radio-width{
      width: 300px !important;
    }
  }

  .td-style{
    border-left: 1px solid #B0B0B066;
    border-right: 1px solid #B0B0B066;
  }
  .th-style{
    color: white !important;
    background-color: #867DEF !important;
  }
</style>