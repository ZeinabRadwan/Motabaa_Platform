<script setup>
import {
avatarText,
kFormatter,
} from '@core/utils/formatters'
import {messagesApi} from "@/plugins/apis/messagesReqest"
import { can } from '@layouts/plugins/casl'
import Message from '@/views/messages/Message.vue';
import InfiniteLoading from "v3-infinite-loading";
import "v3-infinite-loading/lib/style.css";

const messagesReqest = messagesApi()

const props = defineProps({
  caseData: {
    type: Object,
    required: true,
  },
})

const InfiniteLoadingShow = ref(true);
const messages = ref([]);
const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})
const loading = ref({
  items: false,
  cases: false,
})

const fetch = () => {
  messagesReqest.fetchAll({
      case_id: props.caseData.id,
      options: options.value,
      page: options.value.page,
  }).then(response => {
    if(response.data.data.length == 0){
      InfiniteLoadingShow.value = false
    }
    messages.value = messages.value.concat(response.data.data)
    options.value.page = options.value.page +1
  }).catch(error => {
    console.error(error)
  })
}

onMounted(() => {
  fetch()

});

</script>

<template>
  <div>
    <Message v-for="message in messages" :message="message" :key="message.id" class="mb-4"></Message>
  </div>

</template>

<style lang="scss" scoped>
.card-list {
  --v-card-list-gap: 0.75rem;
}

.text-capitalize {
  text-transform: capitalize !important;
}
</style>
