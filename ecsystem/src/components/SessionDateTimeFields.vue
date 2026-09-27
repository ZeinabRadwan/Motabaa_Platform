<script setup>
import i18n from '@/plugins/i18n'
import { requiredValidator } from '@validators'
import { isParentUser } from '@core/utils/staffSessionVisibility'

const props = defineProps({
  date: {
    type: String,
    default: null,
  },
  time: {
    type: String,
    default: null,
  },
  disableFrom: {
    type: String,
    default: '',
  },
  stacked: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:date', 'update:time'])

const date = computed({
  get: () => props.date,
  set: value => emit('update:date', value),
})

const time = computed({
  get: () => props.time,
  set: value => emit('update:time', value),
})

const colSpan = computed(() => props.stacked ? 12 : 4)

const dateConfig = computed(() => {
  const config = {
    enableTime: false,
    dateFormat: 'Y-m-d',
  }

  if (props.disableFrom)
    config.disable = [{ from: props.disableFrom, to: '9999-12-31' }]

  return config
})

const dayLabel = computed(() => {
  if (!date.value)
    return ''

  const locale = i18n.global.locale.value === 'ar' ? 'ar' : 'en-US'

  return new Date(`${date.value}T00:00:00`).toLocaleDateString(locale, { weekday: 'long' })
})

onMounted(() => {
  const now = new Date()

  if (!date.value) {
    date.value = [
      now.getFullYear(),
      String(now.getMonth() + 1).padStart(2, '0'),
      String(now.getDate()).padStart(2, '0'),
    ].join('-')
  }

  if (!time.value) {
    time.value = [
      String(now.getHours()).padStart(2, '0'),
      String(now.getMinutes()).padStart(2, '0'),
    ].join(':')
  }
})
</script>

<template>
  <VRow v-if="!isParentUser()">
    <VCol
      cols="12"
      :sm="colSpan"
    >
      <AppDateTimePicker
        v-model="date"
        :label="$t('goals.session_date')"
        :rules="[requiredValidator]"
        :config="dateConfig"
      />
    </VCol>

    <VCol
      cols="12"
      :sm="colSpan"
    >
      <AppTextField
        :model-value="dayLabel"
        :label="$t('goals.session_day')"
        readonly
      />
    </VCol>

    <VCol
      cols="12"
      :sm="colSpan"
    >
      <AppDateTimePicker
        v-model="time"
        :label="$t('goals.session_time')"
        :rules="[requiredValidator]"
        :config="{ enableTime: true, minuteIncrement: 1, noCalendar: true, dateFormat: 'H:i' }"
      />
    </VCol>
  </VRow>
</template>
