<script setup>
import i18n from '@/plugins/i18n/index.js';
import { useRoute, useRouter } from 'vue-router';
import { fieldsObj, formData } from '@/views/plans/fields/planDataFields';

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
        <VForm 
          ref="refVForm"
          @submit.prevent="onSubmit"
        >
          <div v-for="(fields, key) in fieldsObj" :key="key">
            <VCardText v-if="key != 'general_info'">
                <h4 class="text-h4"> {{ $t('operational_plan.'+key) }}</h4>
            </VCardText>
            <VDivider v-if="key != 'general_info'"/>
            <VCardText>
              <VRow>
                <VCol
                  v-for="field in fields"
                  :key="field.vModel"
                  :cols="field.cols"
                  :md="field.md"
                >
                  <component 
                    :is="field.componentType" 
                    v-bind="field.hasOwnProperty('binds') ? field.binds : {}" 
                    :items="field.hasOwnProperty('items') ? field.items() : []" 
                    :label="$t(field.label)" 
                    v-model="form[key][field.vModel]" 
                    :type="field.type ? $t(field.type) : ''"
                  >
                  </component>
                </VCol>
              </VRow>
            </VCardText>
          </div>

      </VForm>
      </VCol>
    </VRow>


  </div>
</template>
