<script setup>
import { VDataTable } from 'vuetify/labs/VDataTable'

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
          <table>
            <tr>
              <td class="pe-6 pb-1">
                Code:
              </td>
              <td class="pb-1">
                {{ props.courseData.code }}                
              </td>
            </tr>
            <tr>
              <td class="pe-6 pb-1">
                Name:
              </td>
              <td class="pb-1">
                {{ props.courseData.name }}                
              </td>
            </tr>
            <tr>
              <td class="pe-6 pb-1">
                Credit Hours:
              </td>
              <td class="pb-1">
                {{ props.courseData.CH }}                
              </td>
            </tr>
            <tr>
              <td class="pe-6 pb-1">
                Lecture Hours:
              </td>
              <td class="pb-1">
                {{ props.courseData.LCT }}                
              </td>
            </tr>
            <tr>
              <td class="pe-6 pb-1">
                Tutorial Hours:
              </td>
              <td class="pb-1">
                {{ props.courseData.TUT }}
              </td>
            </tr>
            <tr>
              <td class="pe-6 pb-1">
                Laboratory Hours:
              </td>
              <td class="pb-1">
                {{ props.courseData.LAB }}
              </td>
            </tr>
            <tr>
              <td class="pe-6 pb-1">
                Total Hours:
              </td>
              <td class="pb-1">
                {{ props.courseData.TH }}
              </td>
            </tr>
            <tr>
              <td class="pe-6 pb-1">
                Level:
              </td>
              <td class="pb-1">
                {{ props.courseData.level }}
              </td>
            </tr>
            <tr>
              <td class="pe-6 pb-1">
                Total Degree:
              </td>
              <td class="pb-1">
                {{ props.courseData.total }}
              </td>
            </tr>
          </table>
        </VCardText>
      </VCard>
    </VCol>
  </VRow>
</template>
