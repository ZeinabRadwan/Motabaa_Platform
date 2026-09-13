<script setup>
import { statisticsApi } from "@/plugins/apis/statisticsRequest";
import { centerData } from '@/router/utils';
import { can } from '@layouts/plugins/casl'

const router = useRouter()
const statisticsListStore = statisticsApi()
const statistics = ref()
const center = centerData()
const showAttendance = center && Number(center.package_id) != 2 && Number(center.package_id) != 3
  && can('access_employees-attendance', 'access_employees-attendance')

statisticsListStore.fetchHrDashboard().then(response => {
  statistics.value = response.data.data
}).catch(error => {
  console.error(error)
})

const go = (path) => {
  router.push(path)
}
</script>

<template>
  <section>
    <h4 class="text-h4 mb-6">{{ $t('employee_affairs.dashboard') }}</h4>

    <VRow v-if="statistics">
      <VCol cols="12" sm="6" md="4" lg="3">
        <VCard class="cursor-pointer" color="#1A75CF26" @click="go('/user/list?status=all')">
          <VCardText>
            <VAvatar color="primary" size="42" class="mb-3">
              <VIcon icon="tabler-users" />
            </VAvatar>
            <h5 class="text-h5 mb-1">{{ statistics.total_employees }}</h5>
            <span class="text-sm">{{ $t('employee_affairs.total_employees') }}</span>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4" lg="3">
        <VCard class="cursor-pointer" color="#29CC3933" @click="go('/user/list?status=active')">
          <VCardText>
            <VAvatar color="success" size="42" class="mb-3">
              <VIcon icon="tabler-user-check" />
            </VAvatar>
            <h5 class="text-h5 mb-1">{{ statistics.active_employees }}</h5>
            <span class="text-sm">{{ $t('employee_affairs.active_employees') }}</span>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4" lg="3">
        <VCard class="cursor-pointer" color="#FFCB3326" @click="go('/user/list?hr_alert=contracts_ending')">
          <VCardText>
            <VAvatar color="warning" size="42" class="mb-3">
              <VIcon icon="tabler-file-text" />
            </VAvatar>
            <h5 class="text-h5 mb-1">{{ statistics.expiring_contracts }}</h5>
            <span class="text-sm">{{ $t('employee_affairs.expiring_contracts_kpi') }}</span>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4" lg="3">
        <VCard class="cursor-pointer" color="#E62E2E1F" @click="go('/user/list?hr_alert=expiring_ids_or_docs')">
          <VCardText>
            <VAvatar color="error" size="42" class="mb-3">
              <VIcon icon="tabler-id" />
            </VAvatar>
            <h5 class="text-h5 mb-1">{{ statistics.expiring_ids_or_docs }}</h5>
            <span class="text-sm">{{ $t('employee_affairs.expiring_ids_docs_kpi') }}</span>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4" lg="3">
        <VCard class="cursor-pointer" color="#8833FF33" @click="go('/employee_leaves/list?status=pending')">
          <VCardText>
            <VAvatar color="secondary" size="42" class="mb-3">
              <VIcon icon="tabler-clock" />
            </VAvatar>
            <h5 class="text-h5 mb-1">{{ statistics.pending_leaves }}</h5>
            <span class="text-sm">{{ $t('employee_affairs.pending_leaves') }}</span>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" sm="6" md="4" lg="3">
        <VCard class="cursor-pointer" color="#1A75CF26" @click="go('/employee_leaves/list?current=1')">
          <VCardText>
            <VAvatar color="info" size="42" class="mb-3">
              <VIcon icon="tabler-calendar-off" />
            </VAvatar>
            <h5 class="text-h5 mb-1">{{ statistics.on_leave }}</h5>
            <span class="text-sm">{{ $t('employee_affairs.on_leave') }}</span>
          </VCardText>
        </VCard>
      </VCol>

      <VCol v-if="showAttendance" cols="12" md="6" lg="6">
        <VCard class="cursor-pointer" @click="go('/employees_attendance/list')">
          <VCardText>
            <div class="d-flex align-center gap-3 mb-4">
              <VAvatar color="primary" size="42">
                <VIcon icon="tabler-calendar-check" />
              </VAvatar>
              <div>
                <div class="text-subtitle-1">{{ $t('employee_affairs.attendance_today') }}</div>
                <div class="text-sm text-medium-emphasis">{{ statistics.attendance_today?.date }}</div>
              </div>
            </div>
            <VRow>
              <VCol cols="4">
                <div class="text-sm text-success">{{ $t('employee_affairs.present') }}</div>
                <div class="text-h6">{{ statistics.attendance_today?.present }}</div>
              </VCol>
              <VCol cols="4">
                <div class="text-sm text-error">{{ $t('employee_affairs.absent') }}</div>
                <div class="text-h6">{{ statistics.attendance_today?.absent }}</div>
              </VCol>
              <VCol cols="4">
                <div class="text-sm text-medium-emphasis">{{ $t('employee_affairs.unmarked') }}</div>
                <div class="text-h6">{{ statistics.attendance_today?.unmarked }}</div>
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </section>
</template>

<route lang="yaml">
  meta:
    action: access_users
    subject: access_users
</route>
