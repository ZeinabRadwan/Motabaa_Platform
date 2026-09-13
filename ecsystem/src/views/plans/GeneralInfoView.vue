<script setup>
import { useRoute } from 'vue-router';
import { can } from '@layouts/plugins/casl'
import { fieldsObj, formData } from '@/views/plans/fields/planDataFields';
import infoTable from '@/views/plans/components/infoTable.vue';
import { operationalPlansApi } from "@/plugins/apis/operationalPlansRequest";

const route = useRoute();
const planListStore = operationalPlansApi()
const props = defineProps({
  plan: {
    type: Object,
    required: true,
  },
})

const loading = ref({
  plan_pdf: false,
})

const printPlan = (planID) => {
    loading.value.plan_pdf = true;
    planListStore.printPlan(planID).then(response => {
      loading.value.plan_pdf = false;
      window.open(response.data.data.url, '_blank');
    }).catch(error => {
      loading.value.plan_pdf = false;
    })
}
</script>

<template>
  <VRow>
    <VCol
      cols="12"
      md="12"
      lg="12"
    >
      <VCard class="padding-30p">
        <VRow>
          <span class="v-col v-col-6 d-flex gap-4 text-h4">{{ $t('Operational plan') }} {{ props.plan ? '('+props.plan.term.title+')' : '' }}</span>
          <VCol
            cols="6"
            class="d-flex gap-4 justify-end"
          >
            <VBtn 
              v-if="can('edit_operation-plans','edit_operation-plans')"
              color="success"
              class="me-4"
              @click="printPlan(Number(route.params.id))"
            >
              {{ $t('print') }}
            </VBtn>

            <VBtn
              v-if="can('edit_operation-plans','edit_operation-plans')"
              variant="elevated"
              class="me-4"
              :to="{ name:'plans-put-id', params:{ id: Number(route.params.id) } }"
            >
              {{ $t('Edit') }}
            </VBtn>
          </VCol>

          <VCardText v-if="props.plan && props.plan && props.plan.information">
            <VRow>
              <VCol cols="12">
                <AppTextarea 
                  :label="$t('operational_plan.center_vision')" 
                  auto-grow 
                  v-model="props.plan.information.form.general_info.center_vision" 
                  disabled
                />
              </VCol>

              <VCol cols="12">
                <AppTextarea 
                  :label="$t('operational_plan.center_message')" 
                  auto-grow 
                  v-model="props.plan.information.form.general_info.center_message" 
                  disabled
                />
              </VCol>
              <infoTable :rowsData="props.plan" />
            </VRow>
          </VCardText>
        </VRow>
      </VCard>
    </VCol>
  </VRow>
</template>
