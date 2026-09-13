<script setup>
  // import Terms from "@/pages/terms/components/terms";

  import {termsServices} from "../terms/api/terms"
  import {onMounted , ref} from "vue"
  const terms = new termsServices()
  onMounted( ()=>{
    terms.getTerms({limit:10})
  });




</script>

<template>
<!--    <terms></terms>-->
  <h2>test</h2>
</template>

<route lang="yaml">
  meta:
    action: access_parents
    subject: access_parents
</route>



