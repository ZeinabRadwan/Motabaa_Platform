<script setup>
import { useCoursesListStore } from '@/views/apps/courses/useCoursesListStore'
import CourseInfoPanel from '@/views/apps/courses/view/CourseInfoPanel.vue'
import CourseTabDetails from '@/views/apps/courses/view/CourseTabDetails.vue'
import CourseTabPrograms from '@/views/apps/courses/view/CourseTabPrograms.vue'
import CourseTabElectives from '@/views/apps/courses/view/CourseTabElectives.vue'

const coursesListStore = useCoursesListStore()
const route = useRoute()
const courseData = ref()
const courseTab = ref(null)

const tabs = [
  {
    icon: 'tabler-user-check',
    title: 'Details',
  },
  {
    icon: 'tabler-user-check',
    title: 'Programs',
  },
  {
    icon: 'tabler-user-check',
    title: 'Electives',
  },
]

coursesListStore.fetchCourse(Number(route.params.id)).then(response => {
  courseData.value = response.data.data
});

</script>

<template>
  <VRow v-if="courseData">
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <CourseInfoPanel :course-data="courseData" />
    </VCol>

    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <VTabs
        v-model="courseTab"
        class="v-tabs-pill"
      >
        <VTab
          v-for="tab in tabs"
          :key="tab.icon"
        >
          <VIcon
            :size="18"
            :icon="tab.icon"
            class="me-1"
          />
          <span>{{ tab.title }}</span>
        </VTab>
      </VTabs>

      <VWindow
        v-model="courseTab"
        class="mt-6 disable-tab-transition"
        :touch="false"
      >
        <VWindowItem>
          <CourseTabDetails :course-data="courseData"/>
        </VWindowItem>

        <VWindowItem>
          <CourseTabPrograms :course-data="courseData"/>
        </VWindowItem>

        <VWindowItem v-if="courseData.electives.length>0">
          <CourseTabElectives :course-data="courseData"/>
        </VWindowItem>

      </VWindow>
    </VCol>
  </VRow>
</template>
