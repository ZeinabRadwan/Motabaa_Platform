<script setup>
const props = defineProps(['roots', 'loaded']);

const { roots, loaded } = toRefs(props);
const emit = defineEmits();
const isModifyRoot = ref(false);
import i18n from '@/plugins/i18n/index.js'
import { can } from '@layouts/plugins/casl'
import {assessmentsApi} from "@/plugins/apis/assessmentReqest"
import {
  scaleTypeItems as items,
} from '@core/utils/generalItems';
import { useRoute, useRouter } from 'vue-router';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import PreviewList from '@/views/preview/List.vue';

const rules = [fileList => !fileList || !fileList.length || fileList[0].size < (100 * 1024 * 1024) || 'validtion.less_than_100MB']

const router = useRouter()
const snackbarRef = ref(null);
const title = ref('');
const selected = ref('');
const selectedEvaluationMethod = ref('');
const search = ref('');
const selectedStatus = ref('active');
const evaluationMethodItems = ref([]);
const assessmentsReqest = assessmentsApi()

const selectedRoot = ref({});
const fileUpload = ref('')
const file = ref('')
const fileName = ref('file')
const errorsMessage = ref({
  file: undefined,
})
const preview = ref(false)
const previewData = ref([])
const previewLoading = ref(false)
const importLoading = ref(false)

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

watch(selectedStatus, query => {
    query && filter()
});

const filter = () => {
    if((search.value != null && search.value != '') || (selectedStatus.value != null && selectedStatus.value != '')){
        let obj = {...roots.value};
        for (const key in obj) {
            if ((search.value != null && search.value != '') && !obj[key].title.includes(search.value)) {
                delete obj[key];
            }
            if ((selectedStatus.value != null && selectedStatus.value != '')) {
                if(selectedStatus.value == 'active' && obj[key].deleted_at) {
                    delete obj[key];
                }
                else if(selectedStatus.value == 'inactive' && !obj[key].deleted_at) {
                    delete obj[key];
                }
            }
        }
        return obj;
    }
    return roots.value
}

assessmentsReqest.fetchEvaluationMethodsl().then(response => {
    evaluationMethodItems.value = response.data.data;
});

const save = () => {
    let id = selectedRoot.value.id ?? null;
    let data = {
        type: '0',
        category: selected.value,
        title: title.value,
        evaluation_method_id: selectedEvaluationMethod.value,
    }
    assessmentsReqest.put(data, id).then(response => {
        if(response.data.success == true){
            data['id'] = response.data.data.id
            data['children'] = selectedRoot.value.children ??  {}
            data['type'] = 'root'
            emit('modify-root', data);
            isModifyRoot.value = false
            title.value = ''
            selected.value = ''
            selectedEvaluationMethod.value = ''
            selectedRoot.value = {}
        }
    });

}

const edit = (data) => {
    isModifyRoot.value = true
    selectedRoot.value = data
    title.value = data.title
    selected.value = data.category
    selectedEvaluationMethod.value = parseInt(data.evaluation_method_id)
}

const clickedRoot = (data) => {
    emit('selected-root', data);
}

// 👉 Export Assessments
const exportAssessments = () => {
    previewLoading.value = true;
    assessmentsReqest.fetchAll({
        export: 'export_assessments'
    }).then(response => {
        previewLoading.value = false;
        window.open(response.data.data.url, '_blank');
    })
    .catch(error => {
    })
}

const fileUploadfun = () => {
    fileUpload.value.click()
}

const onFileSelected = () => {
    previewLoading.value = true;
    if (file.value[0]) {
        fileName.value = file.value[0].name;
        if(file.value[0].size <= (100 * 1024 * 1024)){

        const formData = new FormData();
        formData.append('center_id', Number(localStorage.getItem('center')));
        formData.append('import', 'import_assessments');
        formData.append('action', 'preview');
        formData.append('file', file.value[0]);

        assessmentsReqest.import(formData)
        .then(response => {
            if(response.status == 200){
                previewData.value = response.data.data;
                preview.value = true;
                previewLoading.value = false;
            }
            file.value = null;
        }).catch(error => {
            previewLoading.value = false;
            errorsMessage.value = error.response.data.errors
        })
        }else{
            previewLoading.value = false;
            snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.less_than_100MB'), 'error');
        }
    }else{
        previewLoading.value = false;
        fileName.value = 'file';
    }
    errorsMessage.value.file = { file: undefined,};
}

const importAssessments = () => {
    importLoading.value = true
    assessmentsReqest.import({
        center_id: Number(localStorage.getItem('center')), 
        import: 'import_assessments', 
        action: 'import', 
        data: previewData.value.sheets
    })
    .then(response => {
        if(response.status == 200){
            snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data['message']), 'success');
            preview.value = false;
            importLoading.value = false;
            clickedRoot(1)
        }
        file.value = null;
    }).catch(error => {
        errorsMessage.value = error.response.data.errors
        preview.value = false;
        importLoading.value = false;
        clickedRoot(1)
    })
}

const cancelImport = () => {
  preview.value = false;
  importLoading.value = false;
}

const deleteDialog = (data) => {
    isDeleteDialogVisible.value = true;
    deleteDialogData.value = data;
}

const deleteItem = () => {
    emit('modify-root', deleteDialogData.value, 'delete')
    isDeleteDialogVisible.value = false;
}

const restoreDialog = (data) => {
    isRestoreDialogVisible.value = true;
    restoreDialogData.value = data;
}

const restoreItem = () => {
    emit('modify-root', restoreDialogData.value, 'restore')
    isRestoreDialogVisible.value = false;
}
</script>

<template>
    <div>
        <VRow v-if="!preview">
            <VCol cols="12">
                <VCard v-if="!isModifyRoot">   
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
                                        <VMenu v-if="can('edit_scales','edit_scales')">
                                            <template #activator="{ props }">
                                            <VBtn color="success" v-bind="props" :loading="previewLoading">
                                                {{ $t('data') }}
                                            </VBtn>
                                            </template>
                                            <VList>
                                            <VListItem @click="exportAssessments()"><VIcon icon="tabler-cloud-download" /> {{ $t('export_data') }}</VListItem>
                                            <VListItem @click="fileUploadfun()"><VIcon icon="tabler-cloud-upload" /> {{ $t('import_data') }}</VListItem>
                                            </VList>
                                        </VMenu>
                                        <VBtn v-if="can('edit_scales', 'edit_scales')" @click="isModifyRoot = true" >
                                            {{ $t('Add Scale') }}
                                        </VBtn>
                                    </div>
                                </VCol>
                            </VRow>
                        </div>
                        <div class="mb-2 pointer-cursor">
                            <VIcon style="color: rgb(var(--v-theme-primary)) !important;" size="17" icon="tabler-home"/>
                        </div>

                        <VRow  v-if="!loaded">
                            <VCol cols="12" class="d-flex justify-center">
                                <VProgressCircular
                                :size="50"
                                color="primary"
                                indeterminate
                                />
                            </VCol>
                        </VRow>
                        <div v-if="loaded">
                            <VAlert
                                v-for="(root, key) in filter()" 
                                :key="key"
                                color="primary"
                                class="mb-2"
                                height="100"
                                rounded="0"
                            >
                                <template v-slot:text>
                                    <div @click="clickedRoot(root)"  class="d-flex align-center pointer-cursor">
                                        <span class="pr-16"></span>
                                        <span class="ma-auto d-flex font-weight-black text-h4" style="color: white;">
                                            <span>{{ $t(root.title) }} </span>
                                        </span>
                                    </div>
                                </template>
                                <template v-slot:append>
                                    <div class="mt-7 d-flex gap-2">
                                        <span v-if="(root.center_id>0 && can('edit_scales', 'edit_scales')) || (!root.center_id && can('admin', 'admin'))" :title="$t('Edit')" class="pointer-cursor" @click="edit(root)">
                                            <VIcon size="20" icon="tabler-edit"/>
                                        </span>
                                        <span v-if="!root.deleted_at && ((root.center_id>0 && can('admin_scales', 'admin_scales')) || (!root.center_id && can('admin', 'admin')))" :title="$t('delete')" class="pointer-cursor" @click="deleteDialog(root)">
                                            <VIcon size="20" icon="tabler-trash"/>
                                        </span>
                                        <span v-else-if="root.deleted_at && ((root.center_id>0 && can('admin_scales', 'admin_scales')) || (!root.center_id && can('admin', 'admin')))" :title="$t('restore')" class="pointer-cursor" @click="restoreDialog(root)">
                                            <VIcon size="20" icon="tabler-refresh"/>
                                        </span>
                                    </div>
                                </template>
                            </VAlert>
                        </div>
                        <!-- Button -->

                    </VCardText>

                </VCard>
                <VCard v-if="isModifyRoot">   
                    <template v-slot:title>
                    <VRow>
                        <h3 class="v-col v-col-6 d-flex gap-4"> {{ $t('Add Scale') }}</h3>

                        <VCol
                        cols="6"
                        class="d-flex gap-4 justify-end"
                        >
                        <VBtn @click="isModifyRoot = false" color="secondary">
                            {{ $t('Cancel') }}
                        </VBtn>
                        <VBtn @click="save" :disabled="title =='' || selected == '' || selectedEvaluationMethod == ''">
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
                                
                                <VCol
                                    md="4"
                                    sm="12"
                                >
                                    <AppSelect
                                        :items="items()"
                                        v-model="selected"
                                        :label="$t('Scale Type')"
                                    />
                                </VCol>
                                <VCol
                                    md="4"
                                    sm="12"
                                >
                                    <AppSelect
                                        :items="evaluationMethodItems"
                                        v-model="selectedEvaluationMethod"
                                        :label="$t('evaluation_method')"
                                    />
                                </VCol>
                            </VRow>
                        </div>
                    </VCardText>
                </VCard>
            </VCol>
        </VRow>
        <VRow v-else>
            <VCol cols="12">
                <VCard class="padding-30p">
                    <template v-slot:title>
                        <VRow>
                            <span v-if="previewData && previewData.sheets_count==1" class="v-col v-col-6 d-flex gap-4 text-h4">{{ $t('assessment_data') }}</span>
                            <span v-else class="v-col v-col-6 d-flex gap-4 text-h4">{{ $t('assessments_data') }}</span>
                            <VCol
                                cols="6"
                                class="d-flex gap-4 justify-end"
                            >
                                <VBtn
                                    v-if="can('edit_scales','edit_scales') && previewData && previewData.sheets_count>0 && previewData.n_errors==0" 
                                    @click="importAssessments()"
                                    color="primary"
                                    :loading="importLoading"
                                >
                                {{ $t('Save') }}
                                </VBtn>
                                <VBtn
                                color="secondary"
                                @click="cancelImport()"
                                >
                                {{ $t('Cancel') }}
                                </VBtn>
                            </VCol>
                        </VRow>
                    </template>
                    <VDivider/>
                    <PreviewList :data="previewData"/>
                </VCard>
            </VCol>
        </VRow>

        <VFileInput v-show="false" v-model="file" ref="fileUpload" @change="onFileSelected" />
    
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
        <SnackbarComponent ref="snackbarRef" />
    </div>
</template>
