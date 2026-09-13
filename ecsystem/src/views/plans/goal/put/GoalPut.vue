<script setup>
import i18n from '@/plugins/i18n/index.js';
import { useRoute, useRouter } from 'vue-router';
import { operationalPlansApi } from "@/plugins/apis/operationalPlansRequest";
import {
requiredValidator
} from '@validators';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import AppDateTimePicker from '@/@core/components/app-form-elements/AppDateTimePicker.vue';
import AppTextField from '@/@core/components/app-form-elements/AppTextField.vue';
import {
  departmentsItems
} from '@core/utils/generalItems';

const planListStore = operationalPlansApi()
const route = useRoute()
const router = useRouter()
const refVForm = ref()
const department = ref('')
const general_goal = ref('')
const activitiesAndPrograms = ref('')
const targetedBy = ref('')
const implementedBy = ref('')
const goalsServices = ref('')
const performanceIndicator = ref('')
const referenceFeed = ref('')
const snackbarRef = ref(null);
const isNew = ref(true);

if(Number(route.params.id)>0) {
  isNew.value = false
  planListStore.fetchGoal(Number(route.params.id)).then(response => {
    let goal = response.data.data
    department.value = goal['department'];
    general_goal.value = goal['general_goal'];
    activitiesAndPrograms.value = goal['activities_and_programs'];
    targetedBy.value = goal['targeted_by'];
    implementedBy.value = goal['implemented_by'];
    goalsServices.value = goal['goals_services'];
    performanceIndicator.value = goal['performance_indicator'];
    referenceFeed.value = goal['reference_feed'];
  })
}

const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){

      const formData = new FormData();
  
      // Append form field data to the FormData object
      formData.append('operational_plan_id', Number(route.params.plan_id));
      formData.append('department', department.value);
      formData.append('general_goal', general_goal.value);
      formData.append('activities_and_programs', activitiesAndPrograms.value);
      formData.append('targeted_by', targetedBy.value);
      formData.append('implemented_by', implementedBy.value);
      formData.append('goals_services', goalsServices.value);
      formData.append('performance_indicator', performanceIndicator.value);
      formData.append('reference_feed', referenceFeed.value);
      
      planListStore.putGoal(Number(route.params.id), formData).then(response => {
        if(response.data['status']){
          snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
          setTimeout(() => {router.push(route.query.to ? String(route.query.to) : `/plans/goals/${Number(route.params.plan_id)}/view`)}, window.timeOutAfterSubmit);
        }

      }).catch((e=>{
        const { errors: formErrors } = e.response.data
        errors.value = formErrors
      }))
    }
  })
}

const goalTypes = () => {
  let items = [
  ]
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))

  return translatedItems;
}
</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <!-- 👉 Multiple Column -->
        <VForm 
          ref="refVForm"
          @submit.prevent="onSubmit"
        >
          <VCard class="padding-30p"> 
            <template v-slot:title>
              <VRow>
                <span class="v-col v-col-6 d-flex gap-4 text-h4"> {{ $t('operational_plan.add_operational_plan') }}</span>

                <VCol
                  cols="6"
                  class="d-flex gap-4 justify-end"
                >
                  <VBtn type="submit">
                    {{ isNew ? $t('Save') : $t('Update') }}
                  </VBtn>
                </VCol>
              </VRow>
            </template>   
            <VCard class="border-1p">
              <VCardText>
                <VRow>
                  <VCol cols="12" md="4">
                    <AppSelect
                      v-model="department"
                      :label="$t('Department')"
                      :items="departmentsItems()"
                      clearable
                      clear-icon="tabler-x"
                      class="pa-1"
                      :rules="[requiredValidator]"
                    >
                    </AppSelect>
                  </VCol>
                  
                  <VCol cols="12" md="4">
                    <AppTextField
                      v-model="targetedBy"
                      :label="$t('operational_plan.targeted_by')"
                    />
                  </VCol>
                  
                  <VCol cols="12" md="4">
                    <AppTextField
                      v-model="implementedBy"
                      :label="$t('operational_plan.implemented_by')"
                    />
                  </VCol>
                  
                  <VCol cols="12">
                    <AppTextarea
                      v-model="general_goal"
                      :label="$t('operational_plan.general_goal')"
                      rows="4"
                      :rules="[requiredValidator]"
                    />
                  </VCol>
                  
                  <VCol cols="12">
                    <AppTextarea
                      v-model="activitiesAndPrograms"
                      :label="$t('operational_plan.activities_and_programs')"
                      rows="4"
                    />
                  </VCol>
                  
                  <VCol cols="12">
                    <AppTextarea
                      v-model="goalsServices"
                      :label="$t('operational_plan.goals_services')"
                      rows="4"
                    />
                  </VCol>
                  
                  <VCol cols="12">
                    <AppTextarea
                      v-model="performanceIndicator"
                      :label="$t('operational_plan.performance_indicator')"
                      rows="4"
                    />
                  </VCol>
                  
                  <VCol cols="12">
                    <AppTextarea
                      v-model="referenceFeed"
                      :label="$t('operational_plan.reference_feed')"
                      rows="4"
                    />
                  </VCol>
                </VRow>
              </VCardText>
            </VCard>
          </VCard>
        </VForm>
      </VCol>
    </VRow>
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>
<route lang="yaml">
  meta:
    action: access_operation-plans
    subject: access_operation-plans
</route>