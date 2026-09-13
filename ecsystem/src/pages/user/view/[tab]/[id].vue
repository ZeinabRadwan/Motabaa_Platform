<script setup>
import { useRoute } from 'vue-router'
import { useUserListStore } from '@/views/apps/user/useUserListStore'
import UserBioPanel from '@/views/apps/user/view/UserBioPanel.vue'
import UserInfoView from '@/views/user/UserInfoView.vue';
import UserFiles from '@/views/user/UserFiles.vue';
import UserAttendance from '@/views/user/UserAttendance.vue';
import UserLeaves from '@/views/user/UserLeaves.vue';

const userListStore = useUserListStore()
const route = useRoute()
const userData = ref()
const userTab = ref(null)

if(route.params.tab && route.params.tab == 'info') {
  userTab.value = 0
}
else if(route.params.tab && route.params.tab == 'attendance') {
  userTab.value = 1
}
else if(route.params.tab && route.params.tab == 'files') {
  userTab.value = 2
}
else if(route.params.tab && route.params.tab == 'leaves') {
  userTab.value = 3
}

const tabs = [
  {
    icon: 'tabler-user',
    title: 'البيانات',
  },
  {
    icon: 'tabler-calendar-check',
    title: 'الحضور',
  },
  {
    icon: 'tabler-file-description',
    title: 'المرفقات',
  },
  {
    icon: 'tabler-calendar-off',
    title: 'الإجازات',
  },
]

// 👉 Fetching files
const fetchUser = () => {

  userListStore.fetchUser(Number(route.params.id)).then(response => {
    userData.value = response.data.data
    userData.value.from_view = 'user'
  })
}

watchEffect(fetchUser)
</script>

<template>
  <VRow v-if="userData">
    <VCol
      cols="12"
      md="5"
      lg="4"
    >
      <UserBioPanel :user-data="userData" />
    </VCol>

    <VCol
      cols="12"
      md="7"
      lg="8"
    >
      <VCol
        cols="12"
        md="9"
        lg="9"
      >
        <VTabs
          v-model="userTab"
          grow
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
      </VCol>

      <VWindow
        v-model="userTab"
        class="disable-tab-transition"
        :touch="false"
      >
        <VWindowItem>
          <UserInfoView :user-data="userData" />
        </VWindowItem>

        <VWindowItem>
          <UserAttendance :user-data="userData" />
        </VWindowItem>

        <VWindowItem>
          <UserFiles/>
        </VWindowItem>

        <VWindowItem>
          <UserLeaves :user-id="userData.id" />
        </VWindowItem>
      </VWindow>
    </VCol>
  </VRow>
</template>
<route lang="yaml">
  meta:
    action: all-users
    subject: Auth
</route>