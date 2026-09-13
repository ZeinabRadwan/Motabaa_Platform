<script setup>
import { VDataTable } from 'vuetify/labs/VDataTable'


const { rowsData, withHeaders } = defineProps(['rowsData', 'withHeaders']);

const tableHeader = [
  {
    title: 'Name',
    key: 'name',
  },
  {
    title: 'Statement',
    key: 'statement',
  },
]

const rows = ref(rowsData)

</script>

<template >
    <VCard>
        <VDataTable
        :items="rowsData"
        disable-pagination
        :headers="tableHeader"
        :items-per-page="-1"
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
            <div class="d-flex">
            {{ $t(item.raw.name) }}
            </div>
        </template>
        <template #item.statement="{ item }">
          <div v-if="Array.isArray(item.raw.statement)" >
                <div v-for="word in item.raw.statement" :key="word">{{ $t(word) }}</div>
            </div>
            <div  v-else-if="item.raw.statement != '' && item.raw.statement != null && item.raw.statement != 'null' && item.raw.statement != [] && item.raw.statement != '[]'" class="d-flex">
              <span v-if="item.raw.is_parent">
                <span v-for="(parent, key) in item.raw.parents">
                  <RouterLink
                    :to="{ name: 'parents-view-id', params: { id: parent.id } }"
                    class="font-weight-medium user-list-name"
                  >
                    {{ parent.name }}
                  </RouterLink>
                  <span v-if="key != Object.keys(item.raw.parents).length - 1">, </span>
                </span>
              </span>
              <span v-else-if="item.raw.is_user">
                <RouterLink
                  :to="{ name: 'user-view-tab-id', params: { id: item.raw.id, tab: 'info' } }"
                  class="font-weight-medium user-list-name"
                >
                  {{ item.raw.statement }}
                </RouterLink>
              </span>
              <span v-else>{{ $t(item.raw.statement) }}</span>
            </div>
        </template>
        <!-- TODO Refactor this after vuetify provides proper solution for removing default footer -->
        <template #bottom />
        </VDataTable>
    </VCard>
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
  .user-list-name:not(:hover) {
    color: rgba(var(--v-theme-on-background), var(--v-medium-emphasis-opacity));
  }
</style>