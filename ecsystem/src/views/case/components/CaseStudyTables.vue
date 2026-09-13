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
            <div class="v-radio-width">
            <VRadioGroup
                class=""
                :disabled="disabledEL"
                v-model="rows[item.raw.name]['evaluation']"
                inline
            >
                <VRadio
                    v-for="radio in colorsRadio"
                    :key="radio"
                    :label="$t(radio.name)"
                    :color="radio.color.toLocaleLowerCase()"
                    :value="radio.value"
                />
            </VRadioGroup>
            </div>
        </template>
        <template #item.notes="{ item }">
            <div class="d-flex">
            <AppTextField
                :disabled="disabledEL"
                v-model="rows[item.raw.name]['notes']"
                style="width: 250px;"
                :placeholder="$t('Note')"
            />
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