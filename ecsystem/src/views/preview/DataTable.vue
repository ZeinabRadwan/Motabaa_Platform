<script setup>
import { VDataTable } from 'vuetify/labs/VDataTable'

const { headers, data } = defineProps(['headers', 'data']);
const search = ref('')
</script>

<template >
  <VRow>
    <VCol cols="12" v-if="data">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              offset-md="8"
              md="4"
            >
              <AppTextField
                v-model="search"
                density="compact"
                :placeholder="$t('Search')"
                append-inner-icon="tabler-search"
                single-line
                hide-details
                dense
                outlined
              />
            </VCol>
          </VRow>
        </VCardText>
        <VDataTable 
          class="text-no-wrap dataTable-hidescroller-y" 
          :headers="headers"
          :items="data"
          fixed-header
          :search="search"
          :items-per-page="-1"
          density="compact"
        >
          <template #item.errors="{ item }">
            <span 
              v-for="(error, errorKey) in item.raw.errors"
              :key="errorKey"
              style="color: rgb(var(--v-theme-error));"
              class="d-flex pt-1 mb-1"
            >
              {{ error }}<br/>
            </span>
          </template>
          
          <template #bottom>
          </template>
        </VDataTable>
    </VCol>
    <VCol cols="12" v-else>
      <span class="d-flex gap-4 text-h4 justify-center gap-3 pa-3 pt-8">
        {{ $t('no_new_data_available') }}
      </span>
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