<script setup>
import { applyServerTableOptions, watchServerTableFetch } from '@core/utils/tableFetch'
import i18n from '@/plugins/i18n/index.js'
import { paginationMeta } from '@/@fake-db/utils'
import { useCoursesListStore } from '@/views/apps/courses/useCoursesListStore'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const coursesListStore = useCoursesListStore()
const searchQuery = ref('')
const selectedProgram = ref()
const selectedDepartment = ref()
const selectedElective = ref()
const selectedStatus = ref()
const selectedTerm = ref()
const totalPages = ref(1)
const totalCourses = ref(0)
const courses = ref([])
const programs = ref([])
const departments = ref([])
const electives = ref([])
const terms = ref([])

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

// 👉 Fetching courses
const fetchCourses = () => {

  coursesListStore.fetchCourses({
    q: searchQuery.value,
    program: selectedProgram.value,
    department: selectedDepartment.value,
    elective: selectedElective.value,
    status: selectedStatus.value,
    term: selectedTerm.value,
    options: options.value,
    page: options.value.page,

  }).then(response => {
    programs.value = response.data.more.programs
    departments.value = response.data.more.departments
    electives.value = response.data.more.electives
    terms.value = response.data.more.terms
    courses.value = response.data.data    
    totalPages.value = Math.ceil(response.data.total / response.data.perPage)
    totalCourses.value = response.data.total
    options.value.page = response.data.currentPage
  }).catch(error => {
    console.error(error)
  })
}

let headers = [
  {
    title: 'Code',
    key: 'code',
    sortable: false,
  },
  {
    title: 'Name',
    key: 'name',
    sortable: false,
  },
  {
    title: 'Term',
    key: 'term',
    sortable: false,
  },
  {
    title: 'Program',
    key: 'program_code',
    sortable: false,
  },
  {
    title: 'Actions',
    key: 'actions',
    sortable: false,
  },
]

const statusItems = () => {
  let items = [
    {
      title: 'All',
      value: 'all',
    },
    {
      title: 'Active_user',
      value: 'active',
    },
    {
      title: 'Inactive',
      value: 'inactive',
    },
  ];
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }));

  return translatedItems;
};

const deleteCourse = id => {
  coursesListStore.deleteCourse(id).then(() =>{
    fetchCourses()
  })
}

const restoreCourse = id => {
  coursesListStore.restoreCourse(id).then(() =>{
    fetchCourses()
  })
}

const onTableOptions = incoming => applyServerTableOptions(options, incoming)

watchServerTableFetch(fetchCourses, {
  search: searchQuery,
  filters: () => [
    selectedProgram.value,
    selectedDepartment.value,
    selectedElective.value,
    selectedStatus.value,
    selectedTerm.value,
  ],
  options,
})
</script>

<template>
  <section>
    <VRow>
      <VCol cols="12">
        <VCard>
          <!-- 👉 Filters -->
          <VCardText>
            <VRow>
              <!-- 👉 Select Program -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedProgram"
                  :label="$t('Programs')"
                  :items="programs"
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>
              <!-- 👉 Select Department -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedDepartment"
                  :label="$t('Department')"
                  :items="departments"
                  clearable
                  clear-icon="tabler-x"
                />
              </VCol>
              <!-- 👉 Select Type -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedElective"
                  :label="$t('Elective')"
                  :items="electives"
                  clearable
                  clear-icon="tabler-x"
                  
                >
                </AppSelect>
              </VCol>
              <!-- 👉 Select Term -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedTerm"
                  :label="$t('Term')"
                  :items="terms"
                  clearable
                  clear-icon="tabler-x"
                  
                >
                </AppSelect>
              </VCol>
              <!-- 👉 Select Status -->
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="selectedStatus"
                  :label="$t('Active')"
                  :items="statusItems()"
                  clearable
                  clear-icon="tabler-x"
                  
                >
                </AppSelect>
              </VCol>
            </VRow>
          </VCardText>

          <VDivider />
          <VCardText class="d-flex flex-wrap py-4 gap-4">
            <div class="me-3 d-flex gap-3">
              <AppSelect
                :model-value="options.itemsPerPage"
                :items="[
                  { value: 10, title: '10' },
                  { value: 25, title: '25' },
                  { value: 50, title: '50' },
                  { value: 100, title: 'All' },
                ]"
                style="width: 6.25rem;"
                @update:model-value="options.itemsPerPage = parseInt($event, 10)"
              />
            </div>
            <VSpacer />

            <div class="app-user-search-filter d-flex align-center flex-wrap gap-4">
              <!-- 👉 Search  -->
              <div style="inline-size: 20rem;">
                <AppTextField
                  v-model="searchQuery"
                  :placeholder="$t('Search')"
                  density="compact"
                />
              </div>
            </div>
          </VCardText>   
          
          <VDivider />

          <VDataTableServer
              v-model:items-per-page="options.itemsPerPage"
              v-model:page="options.page"
              :items="courses"
              :items-length="totalCourses"
              :headers="headers"
              class="text-no-wrap"
              @update:options="onTableOptions"
            >

            <!-- Actions -->
            <template #item.actions="{ item }">

              <IconBtn  @click="()=> router.replace('/courses/view/'+item.raw.id)">
                <VIcon icon="tabler-eye" />
              </IconBtn>

              <IconBtn v-if="!item.raw.deleted_at" @click="()=> router.replace('/courses/edit/'+item.raw.id)">
                <VIcon icon="tabler-edit" />
              </IconBtn>

              <VBtn
                icon
                variant="text"
                size="small"
                color="medium-emphasis"
              >
                <VIcon
                  size="24"
                  icon="tabler-dots-vertical"
                />

                <VMenu activator="parent">
                  <VList>                    
                    <VListItem v-if="!item.raw.deleted_at" @click="deleteCourse(item.raw.id)">
                      <template #prepend>
                        <VIcon icon="tabler-trash" />
                      </template>
                      <VListItemTitle>{{ $t('delete_user') }}</VListItemTitle>
                    </VListItem>
                    <VListItem v-if="item.raw.deleted_at" @click="restoreCourse(item.raw.id)">
                      <template #prepend>
                        <VIcon icon="tabler-refresh" />
                      </template>
                      <VListItemTitle>{{ $t('restore_user') }}</VListItemTitle>
                    </VListItem>

                  </VList>
                </VMenu>
              </VBtn>
            </template>

            <!-- pagination -->
            <template #bottom>
              <VDivider />
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalCourses) }}
                </p>

                <VPagination
                v-model="options.page"
                total-visible="5"
                :length="Math.ceil(totalCourses / options.itemsPerPage)"
                />
              </div>
            </template>
          </VDataTableServer>
        </VCard>
      </VCol>
    </VRow>
  </section>
</template>

<style lang="scss">
</style>
