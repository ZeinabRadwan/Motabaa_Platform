<script setup>
import i18n from '@/plugins/i18n/index.js'
import {assessmentsApi} from "@/plugins/apis/assessmentReqest"
const props = defineProps(['fields', 'steps', 'root', 'loaded']);
const { fields, steps, root, loaded } = toRefs(props);
import { can } from '@layouts/plugins/casl'
import ScalesListBreadcrumb from '@/views/scale/ScalesListBreadcrumb.vue'


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

const filteredFieldsList = computed(() => Object.values(filter() || {}))

const isGoalLevel = computed(() => {
  const keys = Object.keys(fields.value || {})

  return keys.length > 0 && fields.value[keys[0]]?.type === 'goal'
})

const onBreadcrumbHome = () => emit('move-step', 'null')

const onBreadcrumbStep = step => emit('move-step', step)
</script>

<template>
  <div>
    <section
      v-if="!isModifyField && !isModifyGoal"
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
          v-if="can('edit_scales', 'edit_scales')"
          class="scales-list-toolbar__actions"
        >
          <VBtn
            v-if="showAddGoal()"
            color="success"
            variant="tonal"
            prepend-icon="tabler-target"
            @click="isModifyGoal = true"
          >
            {{ $t('Add Goal') }}
          </VBtn>
          <VBtn
            v-if="showAddField"
            class="general-page-header__btn-primary"
            prepend-icon="tabler-plus"
            @click="isModifyField = true"
          >
            {{ $t('Add Field') }}
          </VBtn>
        </div>
      </div>

      <ScalesListBreadcrumb
        :steps="steps"
        @home="onBreadcrumbHome"
        @step="onBreadcrumbStep"
      />

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
          v-else-if="!filteredFieldsList.length"
          class="scales-list-empty"
        >
          <span class="scales-list-empty__icon">
            <VIcon
              icon="tabler-folder-open"
              size="28"
            />
          </span>
          <p class="scales-list-empty__title">
            {{ $t('scales.empty') }}
          </p>
        </div>
        <div
          v-else-if="isGoalLevel"
          class="scales-list-goals"
        >
          <article
            v-for="field in filteredFieldsList"
            :key="field.id"
            class="scales-list-goal"
            :class="{ 'is-inactive': field.deleted_at }"
          >
            <p class="scales-list-goal__text">
              {{ field.title }}
            </p>
            <div class="scales-list-goal__actions">
              <VBtn
                v-if="(field.center_id>0 && can('edit_scales', 'edit_scales')) || (!field.center_id && can('admin', 'admin'))"
                icon
                variant="text"
                size="small"
                :title="$t('Edit')"
                @click="editGoal(field)"
              >
                <VIcon
                  icon="tabler-edit"
                  size="20"
                />
              </VBtn>
              <VBtn
                v-if="!field.deleted_at && ((field.center_id>0 && can('admin_scales', 'admin_scales')) || (!field.center_id && can('admin', 'admin')))"
                icon
                variant="text"
                size="small"
                color="error"
                :title="$t('delete')"
                @click="deleteDialog(field)"
              >
                <VIcon
                  icon="tabler-trash"
                  size="20"
                />
              </VBtn>
              <VBtn
                v-else-if="field.deleted_at && ((field.center_id>0 && can('admin_scales', 'admin_scales')) || (!field.center_id && can('admin', 'admin')))"
                icon
                variant="text"
                size="small"
                :title="$t('restore')"
                @click="restoreDialog(field)"
              >
                <VIcon
                  icon="tabler-refresh"
                  size="20"
                />
              </VBtn>
            </div>
          </article>
        </div>
        <div
          v-else
          class="scales-list-grid"
        >
          <article
            v-for="field in filteredFieldsList"
            :key="field.id"
            class="scales-list-card"
            :class="{ 'is-inactive': field.deleted_at }"
          >
            <button
              type="button"
              class="scales-list-card__main"
              @click="clickedField(field)"
            >
              <span class="scales-list-card__icon">
                <VIcon
                  icon="tabler-list-details"
                  size="22"
                />
              </span>
              <h3 class="scales-list-card__title">
                {{ $t(field.title) }}
              </h3>
              <div
                v-if="field.deleted_at"
                class="scales-list-card__meta"
              >
                <span class="scales-list-card__badge is-muted">
                  {{ $t('scales.inactive_badge') }}
                </span>
              </div>
            </button>
            <div class="scales-list-card__footer">
              <VBtn
                v-if="(field.center_id>0 && can('edit_scales', 'edit_scales')) || (!field.center_id && can('admin', 'admin'))"
                icon
                variant="text"
                size="small"
                :title="$t('Edit')"
                @click.stop="editField(field)"
              >
                <VIcon
                  icon="tabler-edit"
                  size="20"
                />
              </VBtn>
              <VBtn
                v-if="!field.deleted_at && ((field.center_id>0 && can('admin_scales', 'admin_scales')) || (!field.center_id && can('admin', 'admin')))"
                icon
                variant="text"
                size="small"
                color="error"
                :title="$t('delete')"
                @click.stop="deleteDialog(field)"
              >
                <VIcon
                  icon="tabler-trash"
                  size="20"
                />
              </VBtn>
              <VBtn
                v-else-if="field.deleted_at && ((field.center_id>0 && can('admin_scales', 'admin_scales')) || (!field.center_id && can('admin', 'admin')))"
                icon
                variant="text"
                size="small"
                :title="$t('restore')"
                @click.stop="restoreDialog(field)"
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
                @click="clickedField(field)"
              >
                {{ $t('scales.open_scale') }}
              </VBtn>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section
      v-if="isModifyField"
      class="scales-list-shell"
    >
      <header class="scales-list-form__head">
        <h2 class="scales-list-form__title">
          {{ $t('Add Field') }}
        </h2>
        <div class="scales-list-form__actions">
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isModifyField = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            class="general-page-header__btn-primary"
            :disabled="title ==''"
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
            md="6"
          >
            <AppTextField
              v-model="title"
              :label="$t('name')"
              :placeholder="$t('name')"
            />
          </VCol>
        </VRow>
      </div>
    </section>

    <section
      v-if="isModifyGoal"
      class="scales-list-shell"
    >
      <header class="scales-list-form__head">
        <h2 class="scales-list-form__title">
          {{ $t('Add Goal') }}
        </h2>
        <div class="scales-list-form__actions">
          <VBtn
            color="secondary"
            variant="tonal"
            @click="isModifyGoal = false"
          >
            {{ $t('Cancel') }}
          </VBtn>
          <VBtn
            class="general-page-header__btn-primary"
            :disabled="title ==''"
            @click="saveGoal"
          >
            {{ $t('Save') }}
          </VBtn>
        </div>
      </header>
      <div class="scales-list-form">
        <VRow>
          <VCol cols="12">
            <AppTextarea
              v-model="title"
              :label="$t('Goal')"
              :placeholder="$t('Goal')"
            />
          </VCol>
        </VRow>
      </div>
    </section>
    
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
