<script setup>
import { ref } from 'vue';
const { data } = defineProps(['data']);
const open = ref(data.open);

</script>

<template >
  <VRow>
    <VCol cols="12" v-if="data && data.sheets_count>0" v-for="(records, recordsKey) in data.records">
      <VList v-model:opened="open">
        <div>
          <VListGroup v-if="(typeof records)=='object' && records!=null" :value="recordsKey">
            <template #activator="{ props }">
              <VListItem
                prepend-icon="tabler-home"
                v-bind="props"
                :title="records.title"
              />
            </template>

            <div v-for="(record, recordKey) in records">
              <VListGroup v-if="(typeof record)=='object' && record!=null" :value="recordKey">
                <template #activator="{ props }">
                  <VListItem
                    v-bind="props"
                    :title="record.title"
                  />
                </template>
                
                <div v-for="(data, dataKey) in record">
                  <div v-if="(typeof data)=='object' && data!=null && data.type==1">
                    <VListGroup>
                      <template #activator="{ props }">
                        <VListItem
                          v-bind="props"
                          :title="data.title"
                        />
                      </template>
                      
                      <div v-for="(value, valueKey) in data">
                        <div v-if="(typeof value)=='object' && value!=null && value.type==1">
                          <VListGroup>
                            <template #activator="{ props }">
                              <VListItem
                                v-bind="props"
                                :title="value.title"
                              />
                            </template>
                            
                            <div v-for="(lastValue, lastValueKey) in data">
                              <VListItem
                                v-if="(typeof lastValue)=='object' && lastValue!=null"
                                :title="lastValue.title"
                              />
                            </div>
                          </VListGroup>
                        </div>
                        <div v-if="(typeof value)=='object' && value!=null && value.type==2">
                          <VListItem
                            :title="value.title"
                          />
                        </div>
                      </div>
                    </VListGroup>
                  </div>
                  <div v-if="(typeof data)=='object' && data!=null && data.type==2">
                    <VListItem
                      :title="data.title"
                    />
                  </div>
                </div>
              </VListGroup>
            </div>
          </VListGroup>
        </div>
      </VList>
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