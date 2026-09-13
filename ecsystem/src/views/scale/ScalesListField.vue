<script setup>
import i18n from '@/plugins/i18n/index.js'
import {assessmentsApi} from "@/plugins/apis/assessmentReqest"
const props = defineProps(['fields', 'steps', 'root', 'loaded']);
const { fields, steps, root, loaded } = toRefs(props);
import { can } from '@layouts/plugins/casl'


const emit = defineEmits();
const isModifyField = ref(false);
const isModifyGoal = ref(false);
const assessmentsReqest = assessmentsApi()

const title = ref('');
const showAddField = ref(true);
const search = ref('');
const selectedStatus = ref('active');

const selectedField = ref('');
const selectedGoal = ref('');

const isDeleteDialogVisible = ref(false);
const deleteDialogData = ref();

const isRestoreDialogVisible = ref(false);
const restoreDialogData = ref();

const statusItems = () => {
  let items = [
    {
      title: 'All',
      value: 'all',
    },
    {
      title: 'Active_user',
      value: 'active',
    },
    {
      title: 'Inactive',
      value: 'inactive',
    },
  ]
  let translatedItems = items.map(item => ({
    ...item,
    title: i18n.global.t(item.title),
  }))

  return translatedItems;
}

watch(search, (newVal, oldVal) => {
    filter()
});

const filter = () => {
    if((search.value != null && search.value != '') || (selectedStatus.value != null && selectedStatus.value != '')){
        let obj = {...fields.value};
        for (const key in obj) {
            if ((search.value != null && search.value != '') && !obj[key].title.includes(search.value)) {
                delete obj[key];
            }
            if ((selectedStatus.value != null && selectedStatus.value != '')) {
                if(selectedStatus.value == 'active' && obj[key] && obj[key].deleted_at) {
                    delete obj[key];
                }
                else if(selectedStatus.value == 'inactive' && obj[key] && !obj[key].deleted_at) {
                    delete obj[key];
                }
            }
        }
        return obj;
    }
    return fields.value
}


const save = () => {
    let id = selectedField.value.id ?? null;
    let data = {
        type: '1',
        title: title.value,
        parent_id: steps.value[Object.keys(steps.value).pop()].id,
    }

    assessmentsReqest.put(data, id).then(response => {
        if(response.data.success == true){
            data['id'] = response.data.data.id
            data['children'] = selectedField.value.children ??  {}
            data['type'] = 'field'
            emit('modify-field', data);
            isModifyField.value = false
            title.value = ''
            search.value = null
            selectedField.value = {}
        }
    });
}

const saveGoal = () => {
    let id = selectedGoal.value.id ?? null;
    let data = {
        type: '2',
        title: title.value,
        parent_id: steps.value[Object.keys(steps.value).pop()].id,
    }
    assessmentsReqest.put(data, id).then(response => {
        if(response.data.success == true){
            data['id'] = response.data.data.id
            data['children'] = selectedField.value.children ??  {}
            data['type'] = 'goal'
            emit('modify-field', data);
            isModifyGoal.value = false
            selectedGoal.value = {}
            search.value = null
            title.value = ''
        }
    });
}

const editField = (data) => {
    isModifyField.value = true
    selectedField.value = data
    title.value = data.title
}

const editGoal = (data) => {
    isModifyGoal.value = true
    selectedGoal.value = data
    title.value = data.title
}


const showAddGoal = () => {
    if(steps.value.length >= 2){
        if(Object.values(fields.value).length == 0){
            return true;
        }else{
            if (fields.value[Object.keys(fields.value)[0]]['type'] == 'goal'){
                showAddField.value = false;
                return true;
            }
        }
    }
    showAddField.value = true;
   return false;
}

const clickedField = (data) => {
    search.value = null
    emit('selected-field', data);
}

const deleteDialog = (data) => {
    isDeleteDialogVisible.value = true;
    deleteDialogData.value = data;
}

const deleteItem = () => {
    emit('modify-field', deleteDialogData.value, 'delete')
    if(Object.values(fields.value).length == 0){
        showAddField.value = true;
    }
    isDeleteDialogVisible.value = false;
}

const restoreDialog = (data) => {
    isRestoreDialogVisible.value = true;
    restoreDialogData.value = data;
}

const restoreItem = () => {
    emit('modify-field', restoreDialogData.value, 'restore')
    if(Object.values(fields.value).length == 0){
        showAddField.value = true;
    }
    isRestoreDialogVisible.value = false;
}
</script>

<template>
  <div>

    <VCard v-if="!isModifyField && !isModifyGoal">   
        <template v-slot:title>
        <VRow>
            <span class="v-col v-col-6 d-flex gap-4"> {{ $t('Scales') }}</span>
        </VRow>
        </template>
        <VCardText>
            
            <div class="pb-9">
                <VRow>
                    <VCol cols="12" sm="3">
                        <!-- 👉 Search  -->
                        <AppTextField
                            v-model="search"
                            :placeholder="$t('Search')"
                            density="compact"
                        />
                    </VCol>
                    
                    <VCol cols="12" sm="3">
                        <AppSelect
                            v-model="selectedStatus"
                            :items="statusItems()"
                            class="pa-1"
                        >
                        </AppSelect>
                    </VCol>
                        
                    <VCol cols="12" sm="6" class="d-flex align-left justify-end">
                        <div class="d-flex gap-4">
                            <VBtn @click="isModifyGoal = true" v-if="showAddGoal() && can('edit_scales', 'edit_scales')" color="success">
                                {{ $t('Add Goal') }}
                            </VBtn>
                            <VBtn @click="isModifyField = true" v-if="showAddField && can('edit_scales', 'edit_scales')"  >
                                {{ $t('Add Field') }}
                            </VBtn>
                        </div>
                    </VCol>
                </VRow>
            </div>
            <div class="">
                <span class="pointer-cursor" @click="() => emit('move-step', 'null')">
                    <VIcon style="color: rgb(var(--v-theme-primary)) !important;" size="17" icon="tabler-home"/>
                </span>
                <span  v-for="step in steps" :key="step.id" class="pointer-cursor ml-1 mr-1" @click="() => emit('move-step', step)">
                    <span class="d-inline-block"> <VIcon class="mt-1" size="16" icon="tabler-math-lower"/>  {{ step.title }}</span>
                </span>
            </div>
        </VCardText>
            <VCardText v-if="!loaded">
                <VRow>
                    <VCol cols="12" class="d-flex justify-center">
                        <VProgressCircular
                        :size="50"
                        color="primary"
                        indeterminate
                        />
                    </VCol>
                </VRow>
            </VCardText>
            <div v-if="loaded && fields && fields[Object.keys(fields)[0]]?.type == 'goal'" >
                <VDivider />
                <div v-for="(field, key) in filter()" :key="key">
                    <VCardText >
                        <VRow>
                            <VCol cols="12" lg="11" md="11" sm="12">
                                <p class="font-weight-medium text-subtitle-2">
                                    {{ field.title }}
                                </p>
                            </VCol>
                            <VCol cols="12" lg="1" md="1" sm="12">
                                <div class="mb-3 d-flex gap-2">
                                    <span v-if="(field.center_id>0 && can('edit_scales', 'edit_scales')) || (!field.center_id && can('admin', 'admin'))" :title="$t('Edit')" style="color: rgb(var(--v-theme-primary)) !important;" class="pointer-cursor" @click="editGoal(field)">
                                        <VIcon size="20" icon="tabler-edit"/>
                                    </span>
                                    <span v-if="!field.deleted_at && ((field.center_id>0 && can('admin_scales', 'admin_scales')) || (!field.center_id && can('admin', 'admin')))" :title="$t('delete')" style="color: rgb(var(--v-theme-primary)) !important;" class="pointer-cursor" @click="deleteDialog(field)">
                                        <VIcon size="20" icon="tabler-trash"/>
                                    </span>
                                    <span v-else-if="field.deleted_at && ((field.center_id>0 && can('admin_scales', 'admin_scales')) || (!field.center_id && can('admin', 'admin')))" :title="$t('restore')" style="color: rgb(var(--v-theme-primary)) !important;" class="pointer-cursor" @click="restoreDialog(field)">
                                        <VIcon size="20" icon="tabler-refresh"/>
                                    </span>
                                </div>
                            </VCol>
                        </VRow>
                    </VCardText>
                    <VDivider />
                </div>
            </div>
            <VCardText v-else-if="loaded">
                <div v-for="(field, key) in filter()" :key="key">
                    <VAlert
                        :key="key"
                        color="primary"
                        class="mb-2"
                        height="100"
                        rounded="0"
                    >
                        <template v-slot:text>
                            <div @click="clickedField(field)"  class="d-flex align-center pointer-cursor">
                                <span class="pr-16"></span>
                                <div class="ma-auto d-flex font-weight-black text-h4" style="color: white;">
                                    <span>{{ $t(field.title) }} </span>
                                </div>
                            </div>
                        </template>
                        <template v-slot:append>
                            <div class="mt-7 d-flex gap-2">
                                <span v-if="(field.center_id>0 && can('edit_scales', 'edit_scales')) || (!field.center_id && can('admin', 'admin'))" :title="$t('Edit')" class="pointer-cursor" @click="editField(field)">
                                    <VIcon size="20" icon="tabler-edit"/>
                                </span>
                                <span v-if="!field.deleted_at && ((field.center_id>0 && can('admin_scales', 'admin_scales')) || (!field.center_id && can('admin', 'admin')))" :title="$t('delete')" class="pointer-cursor" @click="deleteDialog(field)">
                                    <VIcon size="20" icon="tabler-trash"/>
                                </span>
                                <span v-else-if="field.deleted_at && ((field.center_id>0 && can('admin_scales', 'admin_scales')) || (!field.center_id && can('admin', 'admin')))" :title="$t('restore')" class="pointer-cursor" @click="restoreDialog(field)">
                                    <VIcon size="20" icon="tabler-refresh"/>
                                </span>
                            </div>
                        </template>
                    </VAlert>
                </div>
            </VCardText>

    </VCard>
    <VCard v-if="isModifyField">   
        <template v-slot:title>
        <VRow>
            <h3 class="v-col v-col-6 d-flex gap-4"> {{ $t('Add Field') }}</h3>

            <VCol
              cols="6"
              class="d-flex gap-4 justify-end"
            >
              <VBtn @click="isModifyField = false" color="secondary">
                {{ $t('Cancel') }}
              </VBtn>
              <VBtn @click="save" :disabled="title ==''">
                {{ $t('Save') }}
              </VBtn>
            </VCol>
        </VRow>
        </template>
        <VCardText>
        
            <div class="justify-start d-flex align-center flex-wrap gap-4 pb-9">
                <VRow>
                    <VCol
                        md="4"
                        sm="12"
                    >
                    <AppTextField
                        v-model="title"
                        :label="$t('name')"
                        :placeholder="$t('name')"
                        density="compact"
                    />
                    </VCol>
                </VRow>
            </div>
        </VCardText>
    </VCard>

    <VCard v-if="isModifyGoal">   
        <template v-slot:title>
        <VRow>
            <h3 class="v-col v-col-6 d-flex gap-4"> {{ $t('Add Goal') }}</h3>

            <VCol
              cols="6"
              class="d-flex gap-4 justify-end"
            >
              <VBtn @click="isModifyGoal = false" color="secondary">
                {{ $t('Cancel') }}
              </VBtn>
              <VBtn @click="saveGoal" :disabled="title ==''">
                {{ $t('Save') }}
              </VBtn>

              
              
            </VCol>
        </VRow>
        </template>
        <VCardText>
        
            <div class="justify-start d-flex align-center flex-wrap gap-4 pb-9">
                <VRow>
                    <VCol
                        md="8"
                        sm="12"
                    >
                    <AppTextarea
                        v-model="title"
                        :label="$t('Goal')"
                        :placeholder="$t('Goal')"
                        density="compact"
                    />
                    </VCol>
                </VRow>
            </div>
        </VCardText>
    </VCard>
    
    <VDialog
        v-model="isDeleteDialogVisible"
        persistent
        class="v-dialog-sm"
    >
        <!-- Dialog close btn -->
        <DialogCloseBtn @click="isDeleteDialogVisible = !isDeleteDialogVisible" />

        <!-- Dialog Content -->
        <VCard>
        <VCardText>
            {{ $t('Are you sure you want to delete this assessment?') }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
            <VBtn @click="deleteItem">
            {{ $t('delete') }}
            </VBtn>
            <VBtn
            color="secondary"
            variant="tonal"
            @click="isDeleteDialogVisible = false"
            >
            {{ $t('Cancel') }}
            </VBtn>
        </VCardText>
        </VCard>
    </VDialog>
    
    <VDialog
        v-model="isRestoreDialogVisible"
        persistent
        class="v-dialog-sm"
    >
        <!-- Dialog close btn -->
        <DialogCloseBtn @click="isRestoreDialogVisible = !isRestoreDialogVisible" />

        <!-- Dialog Content -->
        <VCard>
        <VCardText>
            {{ $t('Are you sure you want to restore this assessment?') }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
            <VBtn @click="restoreItem">
            {{ $t('restore') }}
            </VBtn>
            <VBtn
            color="secondary"
            variant="tonal"
            @click="isRestoreDialogVisible = false"
            >
            {{ $t('Cancel') }}
            </VBtn>
        </VCardText>
        </VCard>
    </VDialog>
  </div>
</template>
