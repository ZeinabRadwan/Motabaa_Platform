<script setup>
import { VDataTable, VDataTableServer } from 'vuetify/labs/VDataTable'

defineOptions({
  inheritAttrs: false,
})

const props = defineProps({
  hideVerticalScroll: {
    type: Boolean,
    default: false,
  },
  /** When true, footer pagination is rendered outside the table (GeneralTableSection). */
  externalPagination: {
    type: Boolean,
    default: false,
  },
  /** Use client-side VDataTable instead of VDataTableServer. */
  client: {
    type: Boolean,
    default: false,
  },
})

const tableComponent = computed(() => (props.client ? VDataTable : VDataTableServer))

const attrs = useAttrs()
const slots = useSlots()

const passthroughAttrs = computed(() => {
  const { class: _class, ...rest } = attrs

  return rest
})

const tableClass = computed(() => {
  const external = attrs.class

  return [
    'general-table__datatable',
    'text-no-wrap',
    props.hideVerticalScroll && 'general-table--hide-y-scroll',
    external,
  ].filter(Boolean)
})

const forwardedSlotNames = computed(() => (
  Object.keys(slots).filter(name => name !== 'bottom')
))

const suppressFooter = computed(() => props.externalPagination || !slots.bottom)
</script>

<template>
  <div class="general-table">
    <component
      :is="tableComponent"
      v-bind="passthroughAttrs"
      :class="tableClass"
      hide-default-footer
    >
      <template
        v-for="name in forwardedSlotNames"
        #[name]="slotProps"
      >
        <slot
          :name="name"
          v-bind="slotProps || {}"
        />
      </template>

      <template
        v-if="slots.bottom"
        #bottom
      >
        <slot name="bottom" />
      </template>

      <template
        v-else-if="suppressFooter"
        #bottom
      />
    </component>
  </div>
</template>

<style lang="scss">
@import '@/styles/general-table.scss';
</style>
