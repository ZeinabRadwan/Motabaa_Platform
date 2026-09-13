<script setup>
import { useRoute, useRouter } from 'vue-router';
import ScalesListRoots from '@/views/scale/ScalesListRoots.vue';
import ScalesListField from '@/views/scale/ScalesListField.vue';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import i18n from '@/plugins/i18n/index.js'
import {assessmentsApi} from "@/plugins/apis/assessmentReqest"

const snackbarRef = ref(null);

const route = useRoute()
const router = useRouter()
const assessmentsReqest = assessmentsApi()
const refVForm = ref()

//Math.floor(Math.random() * 100000)

const scales = ref({})
const selectedScales = ref([])
const parentId = ref([])
const rootId = ref(null);
const fields = ref([])
const steps = ref([])
//loading Rotate
const loaded = ref(false)

const fetchAssessments = () => {
  loaded.value = false;
  assessmentsReqest.fetchAll({
    parent_id: parentId.value,
    status: 'all'
  }).then(response => {
    scales.value = indexingObj(response.data.data);
    loaded.value = true;
  });
}

fetchAssessments()

const modifyRoot = (obj, action = null) => {
  if(action == 'delete'){
    assessmentsReqest.delete(obj.id).then(response => {
      parentId.value = null
      rootId.value = null
      if(response.status == 200){
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('deleted_successfully'), 'success');
        fetchAssessments()
      }
    }).catch(e =>{
      if(e.response.status == 406){
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('cantdeletescale'), 'error');
      }
    })
  }
  else if(action == 'restore'){
    assessmentsReqest.restore(obj.id).then(response => {
      parentId.value = null
      rootId.value = null
      if(response.status == 200){
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('restored_successfully'), 'success');
        fetchAssessments()
      }
    }).catch(e =>{
      if(e.response.status == 406){
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('cantrestorescale'), 'error');
      }
    })
  }
  else{
    parentId.value = null
    rootId.value = null
    fetchAssessments()  
  }
}

const selectedRoot = (data) => {
  parentId.value = data.id
  rootId.value = data.id
  selectedScales.value.push(data)
  fetchAssessments()
}

const selectedField = (data) => {
  selectedScales.value.push(data)
  parentId.value = data.id
  fetchAssessments()
}

const moveToStep = (data) => {
  if(data == 'null'){
    selectedScales.value = []
    parentId.value = null
    rootId.value = null
  }else{
    parentId.value = data.id
  }
  fetchAssessments()
  selectedScales.value = selectedScales.value.filter(item => item.id <= data.id);}

const indexingObj = (array) => {
  const transformedObject = {};
  for (const item of array) {
        const transformedItem = { ...item };
        transformedItem.children = {};
        transformedObject[item.id] = transformedItem;
        if (item.children && item.children.length > 0) {
            transformedItem.children = indexingObj(item.children);
        }
    }
    return transformedObject;
}

const addToParentById =  (data, action = null) => {
  if(action == 'delete'){
    assessmentsReqest.delete(data.id).then(response => {
      if(response.status == 200){
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('deleted_successfully'), 'success');
        fetchAssessments()
      }
    }).catch(e =>{
      if(e.response.status == 406){
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('cantdeletescale'), 'error');
      }
    })
  }
  else if(action == 'restore'){
    assessmentsReqest.restore(data.id).then(response => {
      if(response.status == 200){
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('restored_successfully'), 'success');
        fetchAssessments()
      }
    }).catch(e =>{
      if(e.response.status == 406){
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('cantrestorescale'), 'error');
      }
    })
  }
  else{
    fetchAssessments()
  }
}

</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <VForm 
          ref="refVForm"
        >
          <ScalesListRoots v-if="rootId == null" :loaded="loaded" @modify-root="modifyRoot" @move-step="moveToStep" @selected-root="selectedRoot" :roots="scales"></ScalesListRoots> 
          <ScalesListField v-if="rootId != null" :loaded="loaded" @modify-field="addToParentById" :root="scales[rootId]" @move-step="moveToStep" @selected-field="selectedField" :steps="selectedScales" :fields="scales"></ScalesListField> 
        </VForm>
      </VCol>
    </VRow>
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>
<route lang="yaml">
  meta:
    action: access_scales
    subject: access_scales
</route>