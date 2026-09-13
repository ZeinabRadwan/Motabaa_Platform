<script setup>
import add_case from '@images/svg/add_case.svg'
import show_cases from '@images/svg/show_cases.svg'
const route = useRoute()
const router = useRouter()

const simpleStatisticsDemoCards = [
  {
    icon: 'tabler-currency-dollar',
    color: 'error',
    title: 'Total Profit',
    subTitle: 'Last week',
    stat: '1.28k',
    change: '-12.2%',
  },
  {
    icon: 'tabler-chart-bar',
    color: 'info',
    title: 'Total Sales',
    subTitle: 'Last week',
    stat: '$4,673',
    change: '+25.2%',
  },
]
</script>

<template>
  <VRow>
    <VCol       
        cols="12"
        md="12">
          <VRow>
            <VCol md="4">
              <!-- <VCard class="d-flex " height="250">
                <v-card-text class="d-flex justify-center align-center">
                  <div class="align-center text-center">
                    
                  </div>
                </v-card-text>
              </VCard> -->
            </VCol>
          </VRow>
      </VCol>
  </VRow>
</template>
<route lang="yaml">
  meta:
    action: default
    subject: default
</route>