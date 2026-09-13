<script setup>
import { watchServerTableFetch } from '@core/utils/tableFetch'
import { useRoute, useRouter } from 'vue-router';
import {roomsApi} from "@/plugins/apis/roomsReqest"
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import i18n from '@/plugins/i18n/index.js';
import { avatarText } from '@core/utils/formatters'
import { can } from '@layouts/plugins/casl'
import {
    statusItems,
    meetingRoomsTypes,
} from '@core/utils/generalItems';

const roomsReqest = roomsApi()
const route = useRoute()
const router = useRouter()
const refVForm = ref()
const snackbarRef = ref(null);
const options = ref({
  page: -1,
  itemsPerPage: 10,
  sortBy: [],
  groupBy: [],
})

const searchQuery = ref('')
const newRoom = ref('')
const newRoomType = ref('')
const rooms = ref([])
const addRoomDialog = ref(false);
const selectedStatus = ref('active')



const fetchAllRooms = () => {
    roomsReqest.fetchAll({
      q: searchQuery.value,
      status: selectedStatus.value,
      options: options.value,
      page: options.value.page,

    }).then(response => {
        rooms.value = response.data.data
    })
}


const addRoom = () =>{
    addRoomDialog.value = true;
    newRoom.value = '';
    newRoomType.value = '';
}

const saveRoom = () =>{
    if(newRoom.value == null || newRoom.value == '' || newRoomType.value == null || newRoomType.value == ''){
        snackbarRef.value.exposevisibleSnackbar(i18n.global.t('validtion.missing_field'), 'error');
        return;
    }
    roomsReqest.put({title: newRoom.value, type: newRoomType.value}, '-1').then(response => {
        if(response.status == 200){
            snackbarRef.value.exposevisibleSnackbar(i18n.global.t('saved_successfully'), 'success');
            addRoomDialog.value = false;   
            fetchAllRooms()
        }
    }).catch((e) => {
        addRoomDialog.value = false;
    })
}


watchServerTableFetch(fetchAllRooms, {
  search: searchQuery,
  filters: () => [selectedStatus.value],
  options,
})

</script>

<template>
  <div>
    <VRow>
      <VCol cols="12">
        <VForm 
              ref="refVForm"
            >
        <VCard class="padding-30p">    
            <VCard class="border-1p">
                <template v-slot:title>
                    <VRow>
                        <span class="v-col v-col-12 justify-lg-space-between d-flex align-center gap-4"> 
                            <div style="inline-size: 15rem;">
                                <h4 class="text-h4">{{ $t('Meeting rooms') }}</h4>
                            </div>
                            <div  class="d-flex gap-4">
                                <AppTextField
                                style="inline-size: 15rem;"
                                    v-model="searchQuery"
                                    :placeholder="$t('Search')"
                                    density="compact"
                                />
                                <AppSelect
                                    v-if="can('admin_meetings','admin_meetings')"
                                    v-model="selectedStatus"
                                    :items="statusItems()"
                                    clearable
                                    clear-icon="tabler-x"
                                >
                                </AppSelect>
                                <VBtn @click="addRoom" v-if="can('edit_meetings','edit_meetings')">
                                    {{ $t('rooms.add_meeting_room') }}
                                </VBtn>
                            </div>
                        </span>
                    </VRow>
                </template>
                <VDivider></VDivider>
                <v-card-text>
                    <VRow>
                        <VCol
                            cols="12"
                            md="4"
                            v-for="room in rooms"
                            :key="room.id"
                        >
                            <VCard class="cursor-pointer" @click="()=> router.push('/meeting-rooms/view/'+room.id)">
                                <VCardText>
                                    <VRow>
                                        <VCol cols="12" class="d-flex align-center">
                                            <VAvatar
                                                rounded
                                                :color="!room.deleted_at ? 'success' : 'error'"
                                                size="40"
                                                variant="tonal"
                                                class="me-3"
                                                icon="tabler-messages"
                                            />
                                            <div class="d-flex flex-column">
                                                <h6 class="text-base" :class="!room.deleted_at ? 'text-success' : 'text-error'">
                                                    {{ room.title }} ({{ room.type_name ? room.type_name : '' }})
                                                </h6>
                                            </div>
                                        </VCol>
                                        <VCol cols="12"><span class="text-body-1 font-weight-bold">{{$t('rooms.create_by')}}</span></VCol>
                                        
                                        <VCol cols="12">
                                            <div class="d-flex align-center">
                                                <VAvatar
                                                    size="40"
                                                    :variant="'tonal'"
                                                    class="me-3"
                                                >
                                                    <VImg
                                                        v-if="room.user.picture"
                                                        :src="room.user.picture.file_url"
                                                    />
                                                    <span v-else>{{ avatarText(room.user.name) }}</span>
                                                </VAvatar>
                                                <div class="d-flex flex-column">
                                                    <h6 class="text-base">
                                                        {{room.user.name}}
                                                    </h6>
                                                </div>
                                            </div>
                                        </VCol>
                                    </VRow>
                                </VCardText>
                            </VCard>
                        </VCol>
                        
                    </VRow>

                </v-card-text>
            </VCard>
        </VCard>
      </VForm>
      </VCol>
    </VRow>
    <VDialog
        v-model="addRoomDialog"
        persistent
        class="v-dialog-sm"
    >
        <VCard :title="$t('rooms.add_meeting_room')">
        <VCardText>
            <VRow>
                <VCol cols="12">
                    <AppTextField
                        v-model="newRoom"
                        :label="$t('rooms.room_name')"
                    />
                </VCol>
                <VCol cols="12">
                    <AppSelect
                        v-model="newRoomType"
                        :label="$t('rooms.room_type')"
                        :items="meetingRoomsTypes()"
                        clearable
                        clear-icon="tabler-x"
                    >
                    </AppSelect>
                </VCol>
            </VRow>
        </VCardText>
        <VCardText class="d-flex justify-end gap-3 flex-wrap">
            <VBtn
            color="secondary"
            variant="tonal"
            @click="addRoomDialog = false"
            >
                {{ $t('Close') }}
            </VBtn>
            <VBtn color="success" @click="saveRoom">
                {{ $t('Save') }}
            </VBtn>
        </VCardText>
        </VCard>
    </VDialog>
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>
<route lang="yaml">
  meta:
    action: access_meetings
    subject: access_meetings
</route>