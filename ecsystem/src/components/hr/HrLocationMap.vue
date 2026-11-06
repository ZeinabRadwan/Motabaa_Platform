<script setup>
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useI18n } from 'vue-i18n'
import { useTheme } from 'vuetify'

/**
 * OpenStreetMap (Leaflet) map for work locations.
 * - `locations`: saved locations drawn with their geofence circle (click emits `select`).
 * - `editable`: picker for one point (v-model:latitude / v-model:longitude); click or drag the pin, the circle
 *   shows `radius` meters.
 * - `markers`: read-only points over the locations, e.g. employees' last verified positions:
 *   { id, latitude, longitude, color (theme color name), title, lines: [] }; click emits `selectMarker`.
 */
const props = defineProps({
  locations: { type: Array, default: () => [] },
  markers: { type: Array, default: () => [] },
  latitude: { type: [Number, String], default: null },
  longitude: { type: [Number, String], default: null },
  radius: { type: [Number, String], default: null },
  editable: { type: Boolean, default: false },
  selectedId: { type: [Number, String], default: null },
  height: { type: String, default: '360px' },
})

const emit = defineEmits(['update:latitude', 'update:longitude', 'select', 'selectMarker'])

const RIYADH = [24.7136, 46.6753]
const SAUDI_ARABIA = [23.8859, 45.0792]
const DEFAULT_RADIUS = 100

const { t } = useI18n()
const theme = useTheme()
const container = ref(null)
const locating = ref(false)

let map = null
let locationsLayer = null
let markersLayer = null
let pickerMarker = null
let pickerCircle = null
let resizeObserver = null
let recenterPicker = false

const color = name => theme.current.value.colors[name]
const toNumber = value => (value === '' || value === null || value === undefined || Number.isNaN(Number(value)) ? null : Number(value))
const round = value => Math.round(value * 1e7) / 1e7

const pickedPoint = computed(() => {
  const lat = toNumber(props.latitude)
  const lng = toNumber(props.longitude)

  return lat === null || lng === null ? null : [lat, lng]
})

const pickedRadius = computed(() => toNumber(props.radius) ?? DEFAULT_RADIUS)

const pointOf = location => {
  const lat = toNumber(location.latitude)
  const lng = toNumber(location.longitude)

  return lat === null || lng === null ? null : [lat, lng]
}

// Built with DOM nodes so user-entered names are never interpreted as HTML.
const popupFor = location => {
  const root = document.createElement('div')
  const title = document.createElement('strong')

  title.textContent = location.name
  root.appendChild(title)

  const lines = [
    location.branch_name ? `${t('hr.fields.branch')}: ${location.branch_name}` : null,
    `${t('hr.fields.radius_meters')}: ${location.radius_meters ?? DEFAULT_RADIUS}`,
    location.employees_count !== undefined ? `${t('hr.organization.assigned_employees')}: ${location.employees_count}` : null,
    location.is_active === false ? t('Inactive') : null,
  ]

  lines.filter(Boolean).forEach(text => {
    const line = document.createElement('div')

    line.textContent = text
    root.appendChild(line)
  })

  return root
}

const toneOf = location => {
  if (location.is_active === false)
    return color('secondary')

  return String(location.id) === String(props.selectedId) ? color('warning') : color('primary')
}

const renderLocations = (fit = false) => {
  if (!map)
    return
  locationsLayer.clearLayers()

  let bounds = null

  props.locations.forEach(location => {
    const point = pointOf(location)
    if (!point)
      return

    const tone = toneOf(location)
    const selected = String(location.id) === String(props.selectedId)
    const radius = location.radius_meters || DEFAULT_RADIUS

    const circle = L.circle(point, { radius, color: tone, weight: selected ? 3 : 2, fillOpacity: 0.12 })
    const dot = L.circleMarker(point, { radius: 6, color: '#fff', weight: 2, fillColor: tone, fillOpacity: 1 })

    dot.bindTooltip(location.name, { direction: 'top', offset: [0, -6] })
    ;[circle, dot].forEach(layer => {
      layer.bindPopup(() => popupFor(location))
      layer.on('click', () => emit('select', location))
      locationsLayer.addLayer(layer)
    })

    const area = L.latLng(point).toBounds(radius * 2)

    bounds = bounds ? bounds.extend(area) : area
  })

  bounds = renderMarkers(bounds)

  if (fit && bounds && !(props.editable && pickedPoint.value))
    map.fitBounds(bounds, { padding: [30, 30], maxZoom: 17 })
}

const markerPopup = marker => {
  const root = document.createElement('div')
  const title = document.createElement('strong')

  title.textContent = marker.title
  root.appendChild(title)
  ;(marker.lines ?? []).filter(Boolean).forEach(text => {
    const line = document.createElement('div')

    line.textContent = text
    root.appendChild(line)
  })

  return root
}

const renderMarkers = bounds => {
  markersLayer.clearLayers()
  props.markers.forEach(marker => {
    const point = pointOf(marker)
    if (!point)
      return
    const dot = L.circleMarker(point, { radius: 8, color: '#fff', weight: 2, fillColor: color(marker.color || 'info'), fillOpacity: 1 })

    dot.bindTooltip(marker.title, { direction: 'top', offset: [0, -8] })
    dot.bindPopup(() => markerPopup(marker))
    dot.on('click', () => emit('selectMarker', marker))
    markersLayer.addLayer(dot)
    bounds = bounds ? bounds.extend(point) : L.latLngBounds(point, point)
  })

  return bounds
}

const focusSelected = () => {
  const location = props.locations.find(item => String(item.id) === String(props.selectedId))
  const point = location ? pointOf(location) : null
  if (map && point)
    map.flyToBounds(L.latLng(point).toBounds((location.radius_meters || DEFAULT_RADIUS) * 3), { maxZoom: 17, duration: 0.6 })
}

const pinIcon = () => L.divIcon({
  className: 'hr-location-map__pin',
  html: `<span style="background:${color('primary')}"></span>`,
  iconSize: [28, 28],
  iconAnchor: [14, 28],
})

const setPoint = latlng => {
  emit('update:latitude', round(latlng.lat))
  emit('update:longitude', round(latlng.lng))
}

const renderPicker = () => {
  if (!map || !props.editable)
    return

  const point = pickedPoint.value
  if (!point) {
    pickerMarker?.remove()
    pickerCircle?.remove()
    pickerMarker = null
    pickerCircle = null

    return
  }

  if (!pickerMarker) {
    pickerCircle = L.circle(point, { radius: pickedRadius.value, color: color('primary'), weight: 2, dashArray: '6 4', fillOpacity: 0.15 }).addTo(map)
    pickerMarker = L.marker(point, { draggable: true, icon: pinIcon(), zIndexOffset: 1000, keyboard: false }).addTo(map)
    pickerMarker.on('drag', event => pickerCircle?.setLatLng(event.target.getLatLng()))
    pickerMarker.on('dragend', event => setPoint(event.target.getLatLng()))
  }
  else {
    pickerMarker.setLatLng(point)
    pickerCircle.setLatLng(point)
  }
  pickerCircle.setRadius(pickedRadius.value)

  if (recenterPicker) {
    recenterPicker = false
    map.fitBounds(pickerCircle.getBounds(), { padding: [40, 40], maxZoom: 17 })
  }
  else if (!map.getBounds().contains(point)) {
    map.panTo(point)
  }
}

const useMyLocation = () => {
  if (!navigator.geolocation) {
    window.Snackbar?.value?.exposevisibleSnackbar?.(t('hr.map.location_unavailable'), 'error')

    return
  }
  locating.value = true
  navigator.geolocation.getCurrentPosition(position => {
    locating.value = false
    recenterPicker = true
    setPoint({ lat: position.coords.latitude, lng: position.coords.longitude })
  }, () => {
    locating.value = false
    window.Snackbar?.value?.exposevisibleSnackbar?.(t('hr.map.location_unavailable'), 'error')
  }, { enableHighAccuracy: true, timeout: 10000 })
}

onMounted(() => {
  map = L.map(container.value, { scrollWheelZoom: true, attributionControl: true })
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
  }).addTo(map)
  locationsLayer = L.layerGroup().addTo(map)
  markersLayer = L.layerGroup().addTo(map)

  if (props.editable && pickedPoint.value)
    map.setView(pickedPoint.value, 16)
  else
    map.setView(props.editable ? RIYADH : SAUDI_ARABIA, props.editable ? 11 : 5)

  map.on('click', event => {
    if (props.editable)
      setPoint(event.latlng)
  })

  recenterPicker = true
  renderLocations(true)
  renderPicker()

  // Maps inside dialogs and tabs get their final size after mounting.
  resizeObserver = new ResizeObserver(() => map?.invalidateSize())
  resizeObserver.observe(container.value)
})

onBeforeUnmount(() => {
  resizeObserver?.disconnect()
  map?.remove()
  map = null
})

watch(() => props.locations, () => renderLocations(true), { deep: true })

// Refits only when the set of markers changes, not when a periodic refresh moves them.
watch(() => props.markers, (markers, previous) => {
  const ids = list => (list ?? []).map(marker => marker.id).join(',')

  renderLocations(ids(markers) !== ids(previous))
}, { deep: true })
watch(() => props.selectedId, () => {
  renderLocations(false)
  focusSelected()
})
watch([pickedPoint, pickedRadius], renderPicker)
watch(() => theme.current.value.dark, () => renderLocations(false))
</script>

<template>
  <!-- LTR keeps the overlay buttons clear of Leaflet's zoom control (top-left) in Arabic. -->
  <div
    class="hr-location-map"
    :style="{ height }"
    dir="ltr"
  >
    <div
      ref="container"
      class="hr-location-map__canvas"
    />
    <div
      v-if="editable"
      class="hr-location-map__toolbar"
    >
      <VBtn
        size="small"
        color="surface"
        prepend-icon="tabler-current-location"
        :loading="locating"
        @click="useMyLocation"
      >
        {{ $t('hr.map.use_my_location') }}
      </VBtn>
    </div>
    <div
      v-if="editable && !pickedPoint"
      class="hr-location-map__hint"
      dir="auto"
    >
      {{ $t('hr.map.click_to_pick') }}
    </div>
  </div>
</template>

<style lang="scss">
.hr-location-map {
  position: relative;
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;

  &__canvas {
    block-size: 100%;
    inline-size: 100%;
  }

  &__toolbar {
    position: absolute;
    z-index: 1000;
    inset-block-start: 10px;
    inset-inline-end: 10px;
  }

  &__hint {
    position: absolute;
    z-index: 1000;
    padding: 4px 12px;
    border-radius: 4px;
    background: rgba(var(--v-theme-surface), 0.92);
    font-size: 0.8125rem;
    inline-size: fit-content;
    inset-block-end: 24px;
    inset-inline: 0;
    margin-inline: auto;
    pointer-events: none;
  }

  &__pin span {
    display: block;
    border: 3px solid #fff;
    border-radius: 50% 50% 50% 0;
    block-size: 28px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 35%);
    inline-size: 28px;
    transform: rotate(-45deg);
  }
}
</style>
