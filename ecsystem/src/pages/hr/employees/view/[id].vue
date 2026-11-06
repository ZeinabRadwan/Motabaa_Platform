<script setup>
import { hrEmployeesApi } from '@/plugins/apis/hrRequest'
import HrEmployeeAttendanceTab from '@/views/hr/employees/profile/HrEmployeeAttendanceTab.vue'
import HrEmployeeBioCard from '@/views/hr/employees/profile/HrEmployeeBioCard.vue'
import HrEmployeeDocumentsTab from '@/views/hr/employees/profile/HrEmployeeDocumentsTab.vue'
import HrEmployeeEmploymentTab from '@/views/hr/employees/profile/HrEmployeeEmploymentTab.vue'
import HrEmployeeFinancialTab from '@/views/hr/employees/profile/HrEmployeeFinancialTab.vue'
import HrEmployeeHistoryTab from '@/views/hr/employees/profile/HrEmployeeHistoryTab.vue'
import HrEmployeePersonalTab from '@/views/hr/employees/profile/HrEmployeePersonalTab.vue'
import { can } from '@layouts/plugins/casl'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const employee = ref(null)
const loading = ref(true)
const historyTab = ref(null)

const tabs = computed(() => [
  { value: 'personal', icon: 'tabler-user', title: 'hr.employees.tabs.personal', visible: true },
  { value: 'employment', icon: 'tabler-briefcase', title: 'hr.employees.tabs.employment', visible: true },
  { value: 'financial', icon: 'tabler-cash', title: 'hr.employees.tabs.financial', visible: !!employee.value?.can_view_compensation },
  { value: 'attendance', icon: 'tabler-clock', title: 'hr.employees.tabs.attendance', visible: true },
  { value: 'documents', icon: 'tabler-files', title: 'hr.employees.tabs.documents', visible: can('access_hr-documents', 'access_hr-documents') },
  { value: 'history', icon: 'tabler-history', title: 'hr.employees.tabs.history', visible: true },
].filter(tab => tab.visible))

const activeTab = computed({
  get: () => (tabs.value.some(tab => tab.value === route.query.tab) ? route.query.tab : 'personal'),
  set: tab => router.replace({ query: { ...route.query, tab } }),
})

const load = () => hrEmployeesApi.show(route.params.id).then(response => {
  employee.value = response.data.data
}).catch(() => {
  if (!employee.value)
    router.replace({ name: 'hr-employees-list' })
}).finally(() => {
  loading.value = false
})

// Any change (salary, shift, lifecycle, attendance) adds history rows and may change the summary.
const onChanged = () => {
  load()
  historyTab.value?.refresh()
}

watch(() => route.params.id, id => {
  if (!id)
    return
  employee.value = null
  loading.value = true
  load()
}, { immediate: true })
</script>

<template>
  <section class="hr-page">
    <div
      v-if="loading && !employee"
      class="d-flex justify-center pa-12"
    >
      <VProgressCircular
        indeterminate
        color="primary"
      />
    </div>

    <VRow v-else-if="employee">
      <VCol
        cols="12"
        md="4"
        lg="3"
      >
        <HrEmployeeBioCard
          :employee="employee"
          @changed="onChanged"
        />
      </VCol>

      <VCol
        cols="12"
        md="8"
        lg="9"
      >
        <VTabs
          v-model="activeTab"
          class="v-tabs-pill mb-4"
          show-arrows
        >
          <VTab
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
          >
            <VIcon
              :icon="tab.icon"
              start
            />
            {{ $t(tab.title) }}
            <VChip
              v-if="tab.value === 'documents' && employee.documents_count"
              size="x-small"
              class="ms-2"
            >
              {{ employee.documents_count }}
            </VChip>
          </VTab>
        </VTabs>

        <VWindow
          v-model="activeTab"
          :touch="false"
        >
          <VWindowItem value="personal">
            <HrEmployeePersonalTab :employee="employee" />
          </VWindowItem>
          <VWindowItem value="employment">
            <HrEmployeeEmploymentTab
              :employee="employee"
              @changed="onChanged"
            />
          </VWindowItem>
          <VWindowItem
            v-if="employee.can_view_compensation"
            value="financial"
          >
            <HrEmployeeFinancialTab
              :employee="employee"
              @changed="onChanged"
            />
          </VWindowItem>
          <VWindowItem value="attendance">
            <HrEmployeeAttendanceTab
              :employee="employee"
              @changed="onChanged"
            />
          </VWindowItem>
          <VWindowItem
            v-if="tabs.some(tab => tab.value === 'documents')"
            value="documents"
          >
            <HrEmployeeDocumentsTab
              :employee="employee"
              @changed="onChanged"
            />
          </VWindowItem>
          <VWindowItem value="history">
            <HrEmployeeHistoryTab
              ref="historyTab"
              :employee="employee"
            />
          </VWindowItem>
        </VWindow>
      </VCol>
    </VRow>
  </section>
</template>

<route lang="yaml">
meta:
  action: access_hr-employees
  subject: access_hr-employees
  pageTitleKey: hr.employees.profile
  pageSubtitleKey: hr.employees.profile_subtitle
</route>

<style lang="scss">
@import '@styles/hr-module.scss';
</style>
