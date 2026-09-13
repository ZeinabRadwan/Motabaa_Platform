<script setup>
import i18n from '@/plugins/i18n/index.js';
import { useRoute, useRouter } from 'vue-router';
import {fieldsObj, formData} from '@/views/case/fields/casePsychologicalStudy';

const emit = defineEmits();
const route = useRoute()
const router = useRouter()
const refVForm = ref()

const form = ref(formData)

defineExpose({
  form
})

onMounted(() => {
  emit('child-mounted');
});

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
          <div v-for="(fields, key) in fieldsObj" :key="key" >
            <VCardText v-if="key != 'initFields'">
                <h4 class="text-h4"> {{ $t(key) }}</h4>
            </VCardText>
            <VDivider v-if="key != 'initFields'" />
            <VCardText>
              <VRow>
                <VCol 
                  v-for="field in fields" 
                  :key="field.vModel" 
                  :cols="field.cols"
                  :md="field.md"
                >
                <component :is="field.componentType" v-bind="field.hasOwnProperty('binds') ? field.binds : {}" :items="field.hasOwnProperty('items') ? field.items() : []" :label="$t(field.label)" v-model="form[key][field.vModel]" :clearable="field.componentType.name==='AppSelect'" > </component>
                </VCol>
              </VRow>
            </VCardText>
          </div>
        </VForm>
      </VCol>
    </VRow>
  </div>
</template>
