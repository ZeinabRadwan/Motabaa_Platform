<script setup>
import { questionnairesApi } from '@/plugins/apis/questionnairesRequest'

const router = useRouter()
const questionnaireListStore = questionnairesApi()
const tasks = ref([])
const loading = ref(false)

const loadTasks = () => {
  loading.value = true
  questionnaireListStore.fetchRespondentAvailableTasks().then(response => {
    tasks.value = response.data?.data ?? []
  }).catch(() => {
    tasks.value = []
  }).finally(() => {
    loading.value = false
  })
}

loadTasks()
</script>

<template>
  <section>
    <VCard :loading="loading">
      <VCardText>
        <h4 class="text-h5 font-weight-bold mb-4">
          {{ $t('questionnaires.respondent_list_heading') }}
        </h4>
        <div
          v-if="!loading && !tasks.length"
          class="text-body-1 text-medium-emphasis py-6 text-center"
        >
          {{ $t('questionnaires.respondent_list_empty') }}
        </div>
        <VList v-else>
          <VListItem
            v-for="task in tasks"
            :key="task.id"
            :title="task.title || task.questionnaire_title"
            :subtitle="task.term_title || task.target_role_name || ''"
            @click="router.push(`/questionnaires/questions/${task.id}`)"
          />
        </VList>
      </VCardText>
    </VCard>
  </section>
</template>

<route lang="yaml">
  meta:
    action: read
    subject: Auth
</route>
