<script setup>
import i18n from '@/plugins/i18n/index.js'

defineOptions({
  name: 'AppAutocomplete',
  inheritAttrs: false,
})

const props = defineProps({
  serverSearch: {
    type: Function,
    default: null,
  },
  minSearchChars: {
    type: Number,
    default: 0,
  },
  searchDebounce: {
    type: Number,
    default: 300,
  },

  // Fetch the option list on mount so the dropdown opens with choices.
  preload: {
    type: Boolean,
    default: true,
  },
  preloadLimit: {
    type: Number,
    default: 20,
  },

  // Changing this refetches the preloaded list (for lists that depend on another filter).
  preloadKey: {
    type: [String, Number, Boolean, Array, Object],
    default: null,
  },
})

const emit = defineEmits(['addLebelFun', 'update:search', 'update:modelValue'])
const attrs = useAttrs()

const elementId = computed(() => {
  const _elementIdToken = attrs.id || attrs.label

  return _elementIdToken ? `app-autocomplete-${ _elementIdToken }-${ Math.random().toString(36).slice(2, 7) }` : undefined
})

const label = computed(() => attrs.label)
const labelClasses = computed(() => attrs.labelClasses)
const addLebel = computed(() => attrs.addLebel)
const addLebelFun = computed(() => attrs.addLebelFun)

const parentSearch = computed(() => {
  // A bare `search` attribute becomes "" and would lock VAutocomplete to an empty
  // query. Only follow a real v-model:search from the parent.
  if (typeof attrs['onUpdate:search'] !== 'function')
    return undefined

  const value = attrs.search
  if (value === true || value === false)
    return undefined
  if (typeof value === 'string' || value === null)
    return value

  return undefined
})

const searchInput = ref(typeof parentSearch.value === 'string' ? parentSearch.value : '')
const isFocused = ref(false)
const syncingSearch = ref(false)
const remoteItems = ref([])
const preloadItems = ref([])
const preloadDone = ref(false)

// The preloaded list holds every option, so typing can be filtered locally.
const preloadComplete = ref(false)
const remoteLoading = ref(false)
const remoteError = ref(false)
let debounceTimer = null
let requestSeq = 0
let preloadSeq = 0

const localFilterOnly = computed(() => props.preload && preloadComplete.value)

const itemValueKey = computed(() => attrs['item-value'] || attrs.itemValue || 'id')
const itemTitleKey = computed(() => attrs['item-title'] || attrs.itemTitle || 'title')
const isMultiple = computed(() => {
  const value = attrs.multiple

  return value === true || value === '' || value === 'multiple' || value === 1
})

const selectedIds = computed(() => {
  const key = itemValueKey.value
  if (key === 'name')
    return []

  const val = attrs.modelValue
  if (val == null || val === '')
    return []

  return (Array.isArray(val) ? val : [val]).filter(value => value !== null && value !== undefined && value !== '')
})

const mergeById = items => {
  const key = itemValueKey.value
  const merged = new Map()

  items.forEach(item => {
    if (item && item[key] !== undefined && item[key] !== null)
      merged.set(item[key], item)
  })

  return Array.from(merged.values())
}

const keepSelectedItems = results => {
  const key = itemValueKey.value
  const selected = remoteItems.value.filter(item => selectedIds.value.includes(item?.[key]))

  return mergeById([...selected, ...results])
}

// Preloaded options stay in the list so a server search never hides them.
const displayItems = computed(() => {
  if (!props.preload)
    return remoteItems.value

  return mergeById([...preloadItems.value, ...remoteItems.value])
})

const listedItems = computed(() => {
  if (props.serverSearch)
    return displayItems.value

  return Array.isArray(attrs.items) ? attrs.items : []
})

const resolveTitle = val => {
  if (val == null || val === '')
    return ''

  const valueKey = attrs['item-value'] || attrs.itemValue || (props.serverSearch ? 'id' : 'value')
  const titleKey = itemTitleKey.value
  const found = listedItems.value.find(item => {
    if (item == null)
      return false
    if (typeof item !== 'object')
      return item === val

    return item[valueKey] == val || item.value == val || item.id == val
  })

  if (found == null)
    return ''
  if (typeof found !== 'object')
    return String(found)
  if (typeof titleKey === 'function')
    return String(titleKey(found) ?? '')

  return String(found[titleKey] ?? found.title ?? found.name ?? '')
}

const fetchPreload = async () => {
  if (!props.serverSearch || !props.preload)
    return

  const seq = ++preloadSeq
  remoteLoading.value = true
  remoteError.value = false

  try {
    const response = await props.serverSearch({ q: '', preload: 1, limit: props.preloadLimit })
    if (seq !== preloadSeq)
      return

    const data = response?.data?.data ?? []
    const list = Array.isArray(data) ? data : []

    preloadItems.value = list
    preloadComplete.value = list.length < props.preloadLimit
  } catch {
    if (seq !== preloadSeq)
      return

    remoteError.value = true
    preloadItems.value = []
    preloadComplete.value = false
  } finally {
    if (seq === preloadSeq) {
      preloadDone.value = true
      remoteLoading.value = false
    }
  }
}

const fetchRemote = async query => {
  if (!props.serverSearch)
    return

  const q = (query ?? '').toString().trim()
  const ids = selectedIds.value
  const canSearch = q.length >= props.minSearchChars

  // Empty input is handled by preload so we do not wipe the open list.
  if (!canSearch && ids.length === 0) {
    if (props.preload)
      return

    remoteItems.value = []
    remoteLoading.value = false
    remoteError.value = false

    return
  }

  const seq = ++requestSeq
  remoteLoading.value = true
  remoteError.value = false

    const payload = {
      q: canSearch ? q : '',
    }
    if (ids.length)
      payload.ids = ids

    try {
      const response = await props.serverSearch(payload)
    if (seq !== requestSeq)
      return

    const data = response?.data?.data ?? []
    remoteItems.value = keepSelectedItems(Array.isArray(data) ? data : [])
  } catch {
    if (seq !== requestSeq)
      return

    remoteError.value = true
    remoteItems.value = keepSelectedItems([])
  } finally {
    if (seq === requestSeq)
      remoteLoading.value = false
  }
}

const emitParentModel = val => {
  emit('update:modelValue', val)
}

const commitSearch = next => {
  searchInput.value = next
  emit('update:search', searchInput.value)
}

const syncSearchFromSelection = val => {
  if (isMultiple.value)
    return

  const title = resolveTitle(val)
  syncingSearch.value = true
  commitSearch(title)
  nextTick(() => {
    syncingSearch.value = false
  })
}

// Vuetify Autocomplete always sets search to '' on blur (and sometimes
// modelValue to null when the typed text is not an exact item). That must
// not wipe a table/list query or reset pagination when the user hovers,
// clicks a row, or opens a row action.
const onFocusedUpdate = val => {
  isFocused.value = !!val
}

const onSearchUpdate = val => {
  if (syncingSearch.value)
    return

  const next = val ?? ''

  if (next === '' && searchInput.value !== '') {
    nextTick(() => {
      if (!isFocused.value)
        return

      // Vuetify clears search after select / while focused. Keep the selected
      // title visible so the field does not look empty.
      if (!isMultiple.value && attrs.modelValue != null && attrs.modelValue !== '') {
        const title = resolveTitle(attrs.modelValue)
        if (title) {
          syncingSearch.value = true
          commitSearch(title)
          nextTick(() => {
            syncingSearch.value = false
          })
          return
        }
      }

      commitSearch('')
      if (!props.serverSearch || localFilterOnly.value)
        return

      if (props.preload)
        return

      fetchRemote('')
    })

    return
  }

  commitSearch(next)
  if (!props.serverSearch || localFilterOnly.value)
    return

  const typed = searchInput.value.toString().trim()
  if (props.preload && typed.length === 0)
    return

  // Selecting an option sets search to the item title. Do not turn that into a new query.
  if (!isMultiple.value && typed && typed === resolveTitle(attrs.modelValue))
    return

  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchRemote(searchInput.value), props.searchDebounce)
}

const onModelUpdate = val => {
  const empty = val === null || val === undefined || val === '' || (Array.isArray(val) && val.length === 0)
  if (empty && attrs.modelValue !== null && attrs.modelValue !== undefined && attrs.modelValue !== '') {
    // Blur/select artifacts must not clear the applied value.
    // The clear icon goes through onClear.
    return
  }

  emitParentModel(val)
  if (isMultiple.value)
    return

  if (empty)
    syncSearchFromSelection('')
  else
    syncSearchFromSelection(val)
}

const onClear = () => {
  syncingSearch.value = true
  searchInput.value = ''
  emit('update:search', '')
  emitParentModel(null)
  nextTick(() => {
    syncingSearch.value = false
  })
}

const onMenuUpdate = open => {
  if (!open || !props.serverSearch || !props.preload)
    return

  if (!preloadDone.value && !remoteLoading.value)
    fetchPreload()
}

watch(() => attrs.modelValue, (value, previous) => {
  if (!props.serverSearch)
    return

  // The preloaded list already carries the labels, so there is nothing to resolve.
  if (props.preload && (!preloadDone.value || preloadComplete.value))
    return

  const previousIds = (Array.isArray(previous) ? previous : [previous]).filter(Boolean)
  const nextIds = selectedIds.value
  const changed = nextIds.join(',') !== previousIds.join(',')
  if (changed && nextIds.length)
    fetchRemote(searchInput.value)
}, { immediate: true })

onMounted(fetchPreload)

onUnmounted(() => {
  clearTimeout(debounceTimer)
})

watch(() => props.preloadKey, (next, prev) => {
  if (JSON.stringify(next) === JSON.stringify(prev))
    return

  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    remoteItems.value = []
    searchInput.value = ''
    fetchPreload()
  }, props.searchDebounce)
})

watch(parentSearch, value => {
  if (syncingSearch.value || typeof value !== 'string')
    return
  if (value !== searchInput.value)
    searchInput.value = value
})

watch([() => attrs.modelValue, listedItems], () => {
  if (isMultiple.value || isFocused.value || syncingSearch.value)
    return

  const val = attrs.modelValue
  if (val == null || val === '')
    return

  const title = resolveTitle(val)
  if (title && searchInput.value !== title)
    searchInput.value = title
}, { immediate: true })

const matchesDropdownQuery = item => {
  const q = (searchInput.value ?? '').toString().trim()
  if (!q)
    return true

  // The input shows the selected title while focused; that is not a search query.
  if (!isMultiple.value && attrs.modelValue != null && attrs.modelValue !== '' && q === resolveTitle(attrs.modelValue))
    return true

  if (item == null)
    return false
  if (typeof item !== 'object')
    return String(item).toLocaleLowerCase().includes(q.toLocaleLowerCase())

  const titleKey = itemTitleKey.value
  const title = typeof titleKey === 'function'
    ? titleKey(item)
    : (item[titleKey] ?? item.title ?? item.name ?? '')

  return String(title).toLocaleLowerCase().includes(q.toLocaleLowerCase())
}

const filterDropdownItems = items => {
  const list = Array.isArray(items) ? items : []
  const q = (searchInput.value ?? '').toString().trim()
  if (!q)
    return list
  if (!isMultiple.value && attrs.modelValue != null && attrs.modelValue !== '' && q === resolveTitle(attrs.modelValue))
    return list

  return list.filter(matchesDropdownQuery)
}

const remoteNoDataText = computed(() => {
  if (remoteError.value)
    return i18n.global.t('autocomplete.load_error')

  if (localFilterOnly.value)
    return i18n.global.t('autocomplete.no_results')

  const typed = (searchInput.value ?? '').toString().trim()
  if (props.minSearchChars > 0 && typed.length < props.minSearchChars)
    return i18n.global.t('autocomplete.type_to_search')

  return i18n.global.t('autocomplete.no_results')
})

const autocompleteBind = computed(() => {
  const {
    class: _class,
    label: _label,
    id: _id,
    search: _search,
    noDataText,
    addLebel: _addLebel,
    addLebelFun: _addLebelFun,
    labelClasses: _labelClasses,
    items,
    loading,
    'onUpdate:modelValue': _onUpdateModelValue,
    ...rest
  } = attrs

  const bind = {
    ...rest,
    class: null,
    label: undefined,
    id: elementId.value,
    variant: 'outlined',
    menuProps: {
      contentClass: [
        'app-inner-list',
        'app-autocomplete__content',
        'v-autocomplete__content',
      ],
    },
  }

  // Always drive VAutocomplete search from our copy so a selected title stays
  // visible while focused. Never pass a bare `search` attribute through.
  bind.search = searchInput.value
  // Vuetify would otherwise re-filter by the displayed selection (or a raw
  // unmatched id), hiding valid options. Item filtering is handled above.
  bind.customFilter = () => true

  if (props.serverSearch) {
    bind.items = filterDropdownItems(displayItems.value)
    bind.loading = remoteLoading.value || Boolean(loading)
    bind.noDataText = remoteNoDataText.value
  } else {
    bind.items = filterDropdownItems(items)
    bind.loading = loading

    if (typeof noDataText === 'string')
      bind.noDataText = noDataText
  }

  return bind
})
</script>

<template>
  <div
    class="app-autocomplete flex-grow-1"
    :class="$attrs.class"
  >
    <div v-if="addLebel" class="d-flex flex-wrap gap-4 justify-lg-space-between">
      <VLabel
        v-if="label"
        :for="elementId"
        :class="labelClasses"
        class="mb-1 text-body-2 text-high-emphasis"
        :text="label"
      />
      <span class="font-weight-black  pointer-cursor" @click="emit('addLebelFun')" style="color: rgba(var(--v-theme-primary))">{{addLebel}}</span>
    </div>
    <div v-else>
      <VLabel
        v-if="label"
        :for="elementId"
        :class="labelClasses"
        class="mb-1 text-body-2 text-high-emphasis"
        :text="label"
      />
    </div>
    <VAutocomplete
      v-bind="autocompleteBind"
      @update:search="onSearchUpdate"
      @update:focused="onFocusedUpdate"
      @update:model-value="onModelUpdate"
      @update:menu="onMenuUpdate"
      @click:clear="onClear"
    >
      <template
        v-for="(_, name) in $slots"
        #[name]="slotProps"
      >
        <slot
          :name="name"
          v-bind="slotProps || {}"
        />
      </template>
    </VAutocomplete>
  </div>
</template>
