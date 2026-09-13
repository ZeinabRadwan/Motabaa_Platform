<script setup>
import i18n from '@/plugins/i18n/index.js';
import { useUserListStore } from '@/views/apps/user/useUserListStore';
import SnackbarComponent from '@core/components/SnackbarCustom.vue';
import {
confirmedValidator,
passwordValidator,
requiredValidator
} from '@validators';
import { useRoute, useRouter } from 'vue-router';

const userListStore = useUserListStore()
const route = useRoute()
const router = useRouter()
const refVForm = ref()
const isPasswordVisible = ref(false)
const isConfirmPasswordVisible = ref(false)
const newPassword = ref('')
const confirmPassword = ref('')


const snackbarRef = ref(null);



const onSubmit = () => {
  refVForm.value?.validate().then(({ valid: isValid }) => {
    if(isValid){
      
      userListStore.changepassowrdUser({
        password: newPassword.value,
        password_confirmation: confirmPassword.value,
      }, route.params.id).then(response => {
        if(response.data['status']){
           snackbarRef.value.exposevisibleSnackbar(i18n.global.t('The password has been changed.'), 'success');
           setTimeout(() => {router.push(route.query.to ? String(route.query.to) : '/user/list')}, 2000);
        }
      }).catch((e=>{
        const { errors: formErrors } = e.response.data
        errors.value = formErrors
      }))
    }
  })
}
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
        <VCard class="padding-30p">    
        <VCard class="border-1p">
        <template v-slot:title>
          <VRow>
            <span class="v-col v-col-6 d-flex gap-4"> {{ $t('Change Password') }}</span>

            <VCol
              cols="6"
              class="d-flex gap-4 justify-end"
            >
              <VBtn type="submit">
                {{ $t('Save') }}
              </VBtn>
            </VCol>
          </VRow>

        </template>
          <v-card-text>

              <VRow>
                <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="newPassword"
                      autofocus
                      dir="ltr"
                      :label="$t('New Password')"
                      :type="isPasswordVisible ? 'text' : 'password'"
                      :append-inner-icon="isPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                      @click:append-inner="isPasswordVisible = !isPasswordVisible"
                      :rules="[requiredValidator, passwordValidator]"
                    />
                  </VCol>
                  <VCol
                    cols="12"
                    md="4"
                  >
                    <AppTextField
                      v-model="confirmPassword"
                      dir="ltr"
                      :label="$t('Confirm Password')"
                      :type="isConfirmPasswordVisible ? 'text' : 'password'"
                      :append-inner-icon="isConfirmPasswordVisible ? 'tabler-eye-off' : 'tabler-eye'"
                      @click:append-inner="isConfirmPasswordVisible = !isConfirmPasswordVisible"
                      :rules="[requiredValidator, confirmedValidator(newPassword, confirmPassword)]"
                    />
                  </VCol>

              </VRow>
              
          </v-card-text>
        </VCard>
        </VCard>
      </VForm>
      </VCol>
    </VRow>
    <SnackbarComponent ref="snackbarRef" />
  </div>
</template>
<route lang="yaml">
  meta:
    action: admin_users
    subject: admin_users
</route>