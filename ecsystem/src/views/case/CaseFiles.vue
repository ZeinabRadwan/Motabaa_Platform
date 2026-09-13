<script setup>
import { watchServerTableFetch } from '@core/utils/tableFetch'
import { useRoute, useRouter } from 'vue-router';
import i18n from '@/plugins/i18n/index.js'
import {casesApi} from "@/plugins/apis/casesReqest"
import { paginationMeta } from '@/@fake-db/utils'
import { VDataTableServer } from 'vuetify/labs/VDataTable'
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import { can } from '@layouts/plugins/casl'
import {
requiredValidator
} from '@validators';

const route = useRoute()
const router = useRouter()
const refVForm = ref()
const casesListStore = casesApi()
const searchQuery = ref('')
const totalPage = ref(1)
const totalFiles = ref(0)
const files = ref([])
const putFileDialog = ref(false);
const fileName = ref('')
const file = ref('')
const fileUpload = ref('')
const snackbarRef = ref(null);
const fileLoading = ref(false);
const isDeleteDialogVisible = ref(false);
const deleteFileName = ref('')
const deleteFileExtension = ref('')
const uploadPercentage = ref(0)

const options = ref({
  page: 1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
  search: undefined,
})

// Headers
const translatedHeaders = () => {
  const headers = [
    {
      title: 'Name',
      key: 'file_name',
    },
    {
      title: 'File Type',
      key: 'file_extension',
    },
    {
      title: 'Actions',
      key: 'actions',
      sortable: false,
    },
  ]
  let translatedHeaders = headers.map(header => ({
    ...header,
    title: i18n.global.t(header.title),
  }))

  return translatedHeaders;
}


// 👉 Fetching files
const fetchFiles = () => {

  casesListStore.fetchFiles(Number(route.params.id), {
    q: searchQuery.value,
    options: options.value,
    page: options.value.page,
  }).then(response => {
    files.value = response.data.data
    totalPage.value = Math.ceil(response.data.total / response.data.perPage)
    totalFiles.value = response.data.total
    options.value.page = response.data.currentPage
  }).catch(error => {
    console.error(error)
  })
}

const fileDialog = () =>{
  putFileDialog.value = true;
  fileLoading.value = false
  fileName.value = '';
  file.value = null;
}

const putFile = () =>{
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){
      
      if(file.value[0].size <= (100 * 1024 * 1024)){
        
        fileLoading.value = true
        const formData = new FormData();
        formData.append('file_name', fileName.value);
        formData.append('file', file.value[0]);

        casesListStore.addFile(
          Number(route.params.id), 
          formData, 
          (progress) => {
            uploadPercentage.value = progress
          }
        ).then(response => {
          if(response.status == 200){
            if(response.data.status) {
              snackbarRef.value.exposevisibleSnackbar(i18n.global.t(response.data.message), 'success');
              fetchFiles()
            }
            putFileDialog.value = false;
            fileLoading.value = false
          }
        }).catch((e) => {
            putFileDialog.value = false;
            fileLoading.value = false
        })
      }else{
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.less_than_100MB'), 'error');
      }
    }
  })
}

const deleteFileDialog = (name, extension) => {
  isDeleteDialogVisible.value = true;
  deleteFileName.value = name;
  deleteFileExtension.value = extension;
}

const deleteFile = () => {

  const formData = new FormData();
  formData.append('file_name', deleteFileName.value);
  formData.append('file_extension', deleteFileExtension.value);

  casesListStore.deleteFile(Number(route.params.id), formData).then(() =>{
    fetchFiles()
    isDeleteDialogVisible.value = false;
  })
}

const openNewTab = (url) => {
  window.open(url, '_blank');
}

watchServerTableFetch(fetchFiles, {
  search: searchQuery,
  options,
})
</script>

<template>
  <section>
    <VRow>
      <VCol cols="12">
        <VCard>
          <VCardText class="d-flex flex-wrap py-4 gap-4">
            <div class="me-3 d-flex gap-3">
              <AppSelect
                :model-value="options.itemsPerPage"
                :items="[
                  { value: 10, title: '10' },
                  { value: 25, title: '25' },
                  { value: 50, title: '50' },
                  { value: 100, title: 'All' },
                ]"
                style="width: 6.25rem;"
                @update:model-value="options.itemsPerPage = parseInt($event, 10)"
              />
            </div>
            <VSpacer />

            <div class="justify-end d-flex align-center flex-wrap gap-4">
              <!-- 👉 Search  -->
              <div style="inline-size: 20rem;">
                <AppTextField
                  v-model="searchQuery"
                  :placeholder="$t('Search')"
                  density="compact"
                />
              </div>
              
              <!-- 👉 Add File button -->
              <VBtn
                v-if="can('edit_cases', 'edit_cases')"
                prepend-icon="tabler-cloud-upload"
                @click="fileDialog()"
              >
                {{ $t('files.Add File') }}
              </VBtn>
            </div>
          </VCardText>

          <VDivider />

          <!-- SECTION datatable -->
          <VDataTableServer
            v-model:items-per-page="options.itemsPerPage"
            v-model:page="options.page"
            :items="files"
            :items-length="totalFiles"
            :headers="translatedHeaders()"
            @update:options="options = $event"
          >
            <!-- Name -->
            <template #item.file_name="{ item }">
              <div style="width: 300px;">
                {{ item.raw.file_name }}
              </div>
            </template>
            <!-- Extension -->
            <template #item.file_extension="{ item }">
              <div style="width: 150px;">
                {{ item.raw.file_extension }}
              </div>
            </template>
            <!-- Actions -->
            <template #item.actions="{ item }">
              <div style="width: 100px;">
                <IconBtn v-if="item.raw.file_url" @click="openNewTab(item.raw.file_url)" color="success">
                  <VIcon 
                    icon="tabler-download"
                    size="22"
                  />
                </IconBtn>

                <IconBtn v-if="can('edit_cases', 'edit_cases')" @click="deleteFileDialog(item.raw.file_name, item.raw.file_extension)">
                  <VIcon icon="tabler-trash" />
                </IconBtn>
              </div>
            </template>

            <!-- pagination -->
            <template #bottom>
              <VDivider />
              <div class="d-flex align-center justify-sm-space-between justify-center flex-wrap gap-3 pa-5 pt-3">
                <p class="text-sm text-disabled mb-0">
                  {{ paginationMeta(options, totalFiles) }}
                </p>

                <VPagination
                  v-model="options.page"
                  :length="Math.ceil(totalFiles / options.itemsPerPage)"
                  :total-visible="$vuetify.display.xs ? 1 : Math.ceil(totalFiles / options.itemsPerPage)"
                >
                  <template #prev="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      Previous
                    </VBtn>
                  </template>

                  <template #next="slotProps">
                    <VBtn
                      variant="tonal"
                      color="default"
                      v-bind="slotProps"
                      :icon="false"
                    >
                      Next
                    </VBtn>
                  </template>
                </VPagination>
              </div>
            </template>
          </VDataTableServer>
        </VCard>
      </VCol>
    </VRow>
    
    <VDialog
      v-model="putFileDialog"
      persistent
      class="v-dialog-sm"
    >
      <VCard :title="$t('files.Add File')">
        <VForm 
            ref="refVForm"
            @submit.prevent="putFile"
          >
          <VCardText>
            <VRow>
              <VCol
                cols="12"
                sm="12"
              >
                <VRow>
                  <VCol cols="12">
                    <AppTextField
                      v-model="fileName"
                      :label="$t('Name')"
                      :rules="[requiredValidator]"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    class="mt-1"
                  >
                  <VFileInput
                    v-model="file"
                    :label="$t('files.Add File')"
                    :loading="fileLoading"
                    :rules="[requiredValidator]"
                  />
                  </VCol>
                </VRow>
              </VCol>
            </VRow>
          </VCardText>
          <VCardText class="d-flex justify-end gap-3 flex-wrap">
            <VBtn
              color="secondary"
              variant="tonal"
              @click="putFileDialog = false"
            >
              {{ $t('Close') }}
            </VBtn>
            <VBtn type="submit" color="success">
              {{ $t('Save') }}<span v-if="fileLoading" > ({{ uploadPercentage }}%)</span>
            </VBtn>
          </VCardText>
        </VForm>
      </VCard>
    </VDialog>

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
          {{ $t('files.Are you sure you want to delete this file?') }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn @click="deleteFile">
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
      v-model="isDeleteDialogVisible"
      persistent
      class="v-dialog-sm"
    >
      <!-- Dialog close btn -->
      <DialogCloseBtn @click="isDeleteDialogVisible = !isDeleteDialogVisible" />
      <!-- Dialog Content -->
      <VCard>
        <VCardText>
          {{ $t('files.Are you sure you want to delete this file?') }}
        </VCardText>

        <VCardText class="d-flex justify-end gap-3 flex-wrap">
          <VBtn @click="deleteFile">
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
    <SnackbarComponent ref="snackbarRef" />
  </section>
</template>

<style lang="scss">
  .text-capitalize {
    text-transform: capitalize;
  }
</style>