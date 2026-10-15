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
import PageHeaderActions from '@/components/PageHeaderActions.vue'
import ScalesListBreadcrumb from '@/views/scale/ScalesListBreadcrumb.vue'

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

const filteredRootsList = computed(() => Object.values(filter() || {}))

const categoryTitle = category => {
  const match = items().find(item => item.value === category)

  return match?.title ?? category ?? ''
}

const evaluationMethodTitle = root => {
  const id = Number(root?.evaluation_method_id)
  if (! id)
    return ''

  const item = evaluationMethodItems.value.find(
    m => Number(m.value ?? m.id) === id,
  )

  return item?.title ?? item?.name ?? ''
}

const showListShell = computed(() => ! preview.value && ! isModifyRoot.value)
</script>

<template>
    <div>
        <PageHeaderActions v-if="showListShell && can('edit_scales', 'edit_scales')">
          <VBtn
            class="general-page-header__btn-primary"
            prepend-icon="tabler-plus"
            @click="isModifyRoot = true"
          >
            {{ $t('Add Scale') }}
          </VBtn>
        </PageHeaderActions>

        <section
          v-if="showListShell"
          class="scales-list-shell"
        >
          <div class="scales-list-toolbar">
            <div class="scales-list-toolbar__filters">
              <AppTextField
                v-model="search"
                class="scales-list-toolbar__search"
                :placeholder="$t('Search')"
                density="comfortable"
                prepend-inner-icon="tabler-search"
                hide-details
              />
              <AppSelect
                v-model="selectedStatus"
                class="scales-list-toolbar__status"
                :items="statusItems()"
                density="comfortable"
                hide-details
              />
            </div>
            <div
              v-if="can('edit_scales','edit_scales')"
              class="scales-list-toolbar__actions"
            >
              <VMenu>
                <template #activator="{ props: menuProps }">
                  <VBtn
                    variant="tonal"
                    color="primary"
                    v-bind="menuProps"
                    :loading="previewLoading"
                    prepend-icon="tabler-database"
                  >
                    {{ $t('data') }}
                  </VBtn>
                </template>
                <VList>
                  <VListItem @click="exportAssessments()">
                    <template #prepend>
                      <VIcon icon="tabler-cloud-download" />
                    </template>
                    {{ $t('export_data') }}
                  </VListItem>
                  <VListItem @click="fileUploadfun()">
                    <template #prepend>
                      <VIcon icon="tabler-cloud-upload" />
                    </template>
                    {{ $t('import_data') }}
                  </VListItem>
                </VList>
              </VMenu>
            </div>
          </div>

          <ScalesListBreadcrumb :steps="[]" />

          <div class="scales-list-body">
            <div
              v-if="!loaded"
              class="scales-list-loader"
            >
              <VProgressCircular
                :size="48"
                color="primary"
                indeterminate
              />
            </div>
            <div
              v-else-if="!filteredRootsList.length"
              class="scales-list-empty"
            >
              <span class="scales-list-empty__icon">
                <VIcon
                  icon="tabler-scale-off"
                  size="28"
                />
              </span>
              <p class="scales-list-empty__title">
                {{ $t('scales.empty') }}
              </p>
            </div>
            <div
              v-else
              class="scales-list-grid"
            >
              <article
                v-for="root in filteredRootsList"
                :key="root.id"
                class="scales-list-card"
                :class="{ 'is-inactive': root.deleted_at }"
              >
                <button
                  type="button"
                  class="scales-list-card__main"
                  @click="clickedRoot(root)"
                >
                  <span class="scales-list-card__icon">
                    <VIcon
                      icon="tabler-chart-bar"
                      size="22"
                    />
                  </span>
                  <h3 class="scales-list-card__title">
                    {{ $t(root.title) }}
                  </h3>
                  <div class="scales-list-card__meta">
                    <span
                      v-if="categoryTitle(root.category)"
                      class="scales-list-card__badge"
                    >
                      {{ categoryTitle(root.category) }}
                    </span>
                    <span
                      v-if="evaluationMethodTitle(root)"
                      class="scales-list-card__badge is-muted"
                    >
                      {{ evaluationMethodTitle(root) }}
                    </span>
                    <span
                      v-if="root.deleted_at"
                      class="scales-list-card__badge is-muted"
                    >
                      {{ $t('scales.inactive_badge') }}
                    </span>
                    <span
                      v-if="root.center_id > 0"
                      class="scales-list-card__badge is-muted"
                    >
                      {{ $t('scales.center_scale') }}
                    </span>
                  </div>
                </button>
                <div class="scales-list-card__footer">
                  <VBtn
                    v-if="(root.center_id>0 && can('edit_scales', 'edit_scales')) || (!root.center_id && can('admin', 'admin'))"
                    icon
                    variant="text"
                    size="small"
                    :title="$t('Edit')"
                    @click.stop="edit(root)"
                  >
                    <VIcon
                      icon="tabler-edit"
                      size="20"
                    />
                  </VBtn>
                  <VBtn
                    v-if="!root.deleted_at && ((root.center_id>0 && can('admin_scales', 'admin_scales')) || (!root.center_id && can('admin', 'admin')))"
                    icon
                    variant="text"
                    size="small"
                    color="error"
                    :title="$t('delete')"
                    @click.stop="deleteDialog(root)"
                  >
                    <VIcon
                      icon="tabler-trash"
                      size="20"
                    />
                  </VBtn>
                  <VBtn
                    v-else-if="root.deleted_at && ((root.center_id>0 && can('admin_scales', 'admin_scales')) || (!root.center_id && can('admin', 'admin')))"
                    icon
                    variant="text"
                    size="small"
                    :title="$t('restore')"
                    @click.stop="restoreDialog(root)"
                  >
                    <VIcon
                      icon="tabler-refresh"
                      size="20"
                    />
                  </VBtn>
                  <VBtn
                    variant="tonal"
                    color="primary"
                    size="small"
                    @click="clickedRoot(root)"
                  >
                    {{ $t('scales.open_scale') }}
                  </VBtn>
                </div>
              </article>
            </div>
          </div>
        </section>

        <section
          v-else-if="isModifyRoot"
          class="scales-list-shell"
        >
          <header class="scales-list-form__head">
            <h2 class="scales-list-form__title">
              {{ $t('Add Scale') }}
            </h2>
            <div class="scales-list-form__actions">
              <VBtn
                color="secondary"
                variant="tonal"
                @click="isModifyRoot = false"
              >
                {{ $t('Cancel') }}
              </VBtn>
              <VBtn
                class="general-page-header__btn-primary"
                :disabled="title =='' || selected == '' || selectedEvaluationMethod == ''"
                @click="save"
              >
                {{ $t('Save') }}
              </VBtn>
            </div>
          </header>
          <div class="scales-list-form">
            <VRow>
              <VCol
                cols="12"
                md="4"
              >
                <AppTextField
                  v-model="title"
                  :label="$t('name')"
                  :placeholder="$t('name')"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppSelect
                  v-model="selected"
                  :items="items()"
                  :label="$t('Scale Type')"
                />
              </VCol>
              <VCol
                cols="12"
                md="4"
              >
                <AppSelect
                  v-model="selectedEvaluationMethod"
                  :items="evaluationMethodItems"
                  :label="$t('evaluation_method')"
                />
              </VCol>
            </VRow>
          </div>
        </section>

        <section
          v-else
          class="scales-list-shell"
        >
          <header class="scales-list-form__head">
            <h2 class="scales-list-form__title">
              <span v-if="previewData && previewData.sheets_count==1">{{ $t('assessment_data') }}</span>
              <span v-else>{{ $t('assessments_data') }}</span>
            </h2>
            <div class="scales-list-form__actions">
              <VBtn
                v-if="can('edit_scales','edit_scales') && previewData && previewData.sheets_count>0 && previewData.n_errors==0"
                class="general-page-header__btn-primary"
                :loading="importLoading"
                @click="importAssessments()"
              >
                {{ $t('Save') }}
              </VBtn>
              <VBtn
                color="secondary"
                variant="tonal"
                @click="cancelImport()"
              >
                {{ $t('Cancel') }}
              </VBtn>
            </div>
          </header>
          <div class="scales-list-form">
            <PreviewList :data="previewData" />
          </div>
        </section>

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
