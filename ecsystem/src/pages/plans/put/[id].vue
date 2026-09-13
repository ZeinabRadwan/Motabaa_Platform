<script setup>
import { casesApi } from "@/plugins/apis/casesReqest"
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import i18n from '@/plugins/i18n/index.js';
import { operationalPlansApi } from "@/plugins/apis/operationalPlansRequest";

// import CaseCaseStudy from '@/views/case/CaseCaseStudy.vue';
import {
   information,
} from '@/views/plans/fields/refFiledsTabs';

import { defineAsyncComponent } from 'vue'
const GeneralInfo = defineAsyncComponent(() => import('@/views/plans/GeneralInfo.vue'))

import { useRoute, useRouter } from 'vue-router';

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const planListStore = operationalPlansApi()
const snackbarRef = ref(null);
const plan = ref([])

const GeneralInfoRef = ref(null)
const loaded = ref(false)

const childMounted = () => {
  loaded.value = true
}

const fetchPlan = async () => {
  planListStore.fetchPlan(Number(route.params.id)).then(response => {
   plan.value = response.data.data
   if(plan.value.information == null || plan.value.information == '' || plan.value.information == 'null'){
      GeneralInfoRef.value.form = information;
   }else{
      let data = {};
      Object.keys(information).map(key => (  data[key] = mergeObjects(plan.value.information.form[key], information[key]) ))

      GeneralInfoRef.value.form = data
   }
  }).catch(error => {
    console.error(error)
  })
}

const GeneralInfoRefMounted = () => {
   fetchPlan()
}

const save = async () => {

  const data ={
    information: {
      form : GeneralInfoRef.value?.form ? GeneralInfoRef.value?.form : information, 
    },
  }

  const formData = new FormData();

  for (const key in data) {
    if (data.hasOwnProperty(key)) {
      const value = data[key];
      if (Array.isArray(value) || typeof value === 'object') {
        formData.append(key, JSON.stringify(value));
      } else {
        formData.append(key, value);
      }
    }
  }

  planListStore.putPlan(Number(route.params.id), formData).then(response => {
    if(response.data.status == true){
      snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
      setTimeout(() => {router.push(route.query.to ? String(route.query.to) : `/plans/general_info/${Number(route.params.id)}/view`)}, 2000);
    }
  }).catch((e=>{
    const { errors: formErrors } = e.response.data
  }))
}

const mergeObjects = (oneArr, twoArr) => {
  const one = {...oneArr};
  const two = {...twoArr};

  for (let key in one) {
    if (
          !((Array.isArray(one[key]) && one[key].length === 0) ||
          (typeof one[key] === 'string' && one[key].trim() === ''))
      ) {
          two[key] = one[key];
      }
  }
  return two;
}

watchEffect(fetchPlan)

</script>

<template>
  <div>
      <VRow>
         <VCol cols="12">
            <VForm 
               ref="refVForm"
            >
               <VCard class="padding-30p">    
                  <template v-slot:title>
                     <VRow>
                        <h3 class="v-col v-col-6 d-flex gap-4"> {{ $t('Operational plan') }}</h3>
                        <VCol
                        cols="6"
                        class="d-flex gap-4 justify-end"
                        >
                           <VBtn @click="save">
                              {{ $t('Save') }}
                           </VBtn>
                        </VCol>
                     </VRow>
                  </template>
                  <VCard class="border-1p">
                     <GeneralInfo @child-mounted="GeneralInfoRefMounted" ref="GeneralInfoRef"></GeneralInfo>
                  </VCard>
               </VCard>
            </VForm>
         </VCol>
      </VRow>
      <SnackbarComponent ref="snackbarRef"/>
  </div>
</template>
<route lang="yaml">
  meta:
    action: edit_operation-plans
    subject: edit_operation-plans
</route>