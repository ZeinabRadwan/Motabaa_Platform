<script setup>
defineOptions({
  inheritAttrs: false,
})

const props = defineProps({
  hideVerticalScroll: {
    type: Boolean,
    default: false,
  },
  /** Override page count for GeneralPagination (same as legacy :length). */
  paginationLength: {
    type: Number,
    default: null,
  },
  paginationTotalVisible: {
    type: [Number, String],
    default: 5,
  },
  paginationDisabled: {
    type: Boolean,
    default: false,
  },
  hidePagination: {
    type: Boolean,
    default: false,
  },
  /** Total row count for pagination; defaults to items-length on the table. */
  paginationTotal: {
    type: Number,
    default: null,
  },
})

const attrs = useAttrs()
const slots = useSlots()

const TABLE_ATTRS = new Set([
  'pagination-length',
  'paginationLength',
  'pagination-total-visible',
  'paginationTotalVisible',
  'pagination-disabled',
  'paginationDisabled',
  'hide-pagination',
  'hidePagination',
  'pagination-total',
  'paginationTotal',
  'hide-vertical-scroll',
  'hideVerticalScroll',
])

const tableAttrs = computed(() => {
  const result = {}

  for (const [key, value] of Object.entries(attrs)) {
    if (TABLE_ATTRS.has(key))
      continue
    if (key.startsWith('on'))
      result[key] = value
    else if (key !== 'class')
      result[key] = value
  }

  return result
})

const sectionClass = computed(() => attrs.class)

const forwardedSlotNames = computed(() => (
  Object.keys(slots).filter(name => !['header', 'pagination', 'pagination-prepend-controls'].includes(name))
))

const itemsLength = computed(() => (
  props.paginationTotal ?? Number(attrs['items-length'] ?? attrs.itemsLength ?? 0)
))

const itemsPerPage = computed(() => Number(attrs.itemsPerPage ?? attrs['items-per-page'] ?? 10))

const pageModel = computed({
  get: () => Number(attrs.page ?? 1),
  set: value => {
    const handler = attrs['onUpdate:page']
    if (typeof handler === 'function')
      handler(value)
  },
})

const showPagination = computed(() => (
  !props.hidePagination
  && (attrs['items-length'] !== undefined || attrs.itemsLength !== undefined || props.paginationTotal != null)
))

const paginationDisabledState = computed(() => (
  props.paginationDisabled || Boolean(attrs.loading)
))
</script>

<template>
  <div
    class="general-table-section"
    :class="sectionClass"
  >
    <div
      v-if="$slots.header"
      class="general-table-section__header"
    >
      <slot name="header" />
    </div>

    <GeneralTable
      v-bind="tableAttrs"
      external-pagination
      :hide-vertical-scroll="hideVerticalScroll"
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
    </GeneralTable>

    <GeneralPagination
      v-if="showPagination && !$slots.pagination"
      v-model:page="pageModel"
      :items-per-page="itemsPerPage"
      :total="itemsLength"
      :length="paginationLength ?? undefined"
      :disabled="paginationDisabledState"
      :total-visible="paginationTotalVisible"
    >
      <template
        v-if="$slots['pagination-prepend-controls']"
        #prepend-controls
      >
        <slot name="pagination-prepend-controls" />
      </template>
    </GeneralPagination>

    <slot
      v-else-if="$slots.pagination"
      name="pagination"
    />
  </div>
</template>

<style lang="scss">
@import '@/styles/general-table-section.scss';
</style>
