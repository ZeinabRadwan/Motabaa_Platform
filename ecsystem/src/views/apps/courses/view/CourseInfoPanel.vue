<script setup>
import pages1 from '@images/pages/1.png'

const props = defineProps({
  courseData: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <VRow>
    <!-- SECTION User Details -->
    <VCol cols="12">
      <VCard>
        <VImg
          :src="pages1"
          cover
        />
        <VCardItem>
          <VCardTitle>{{props.courseData.code}}</VCardTitle>
        </VCardItem>

        <VCardText>
          {{props.courseData.name}}
        </VCardText>
        <VCardText class="text-subtitle-1">
          <span>Credit Hours:</span> <span class="font-weight-medium">{{props.courseData.CH}}</span>
        </VCardText>
      </VCard>
    </VCol>
    <!-- !SECTION -->
  </VRow>
</template>

<style lang="scss" scoped>
</style>
