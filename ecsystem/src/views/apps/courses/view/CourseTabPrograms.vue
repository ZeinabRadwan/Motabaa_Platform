<script setup>
import { VDataTable } from 'vuetify/labs/VDataTable'

const programsTableHeaders = [
  {
    title: 'Code',
    key: 'code',
  },
  {
    title: 'Name',
    key: 'name',
  },
  {
    title: 'Term',
    key: 'term',
  },
  {
    title: 'Elective',
    key: 'elective_code',
  }
]

const options = ref({
  itemsPerPage: -1,
  page: 1,
})

const props = defineProps({
  courseData: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard>
        <VCardText>
          <VDataTable
            v-model:page="options.page"
            :headers="programsTableHeaders"
            :items="props.courseData.programs"      
            :items-per-page="options.itemsPerPage"
          >
          </VDataTable>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
