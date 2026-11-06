<script setup>
import HrScheduleDayCell from '@/components/hr/HrScheduleDayCell.vue'
import { hrShiftScheduleApi } from '@/plugins/apis/hrRequest'
import { addDays, addMonths, dayOfMonth, endOfMonth, formatDate, shiftColor, startOfMonth, startOfWeek, todayIso, WEEKDAYS } from '@/utils/hrShifts'
import HrShiftExceptionDialog from '@/views/hr/attendance/HrShiftExceptionDialog.vue'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'

const props = defineProps({
  employee: { type: Object, required: true },
})

const { locale } = useI18n()

const canAddException = computed(() => can('edit_hr-attendance-setup', 'edit_hr-attendance-setup') && props.employee.is_employed)

const today = todayIso()
const month = ref(startOfMonth(today))
const days = ref({})
const legend = ref([])
const loading = ref(false)

// Full weeks (Sunday first) covering the month; the API caps a request at 62 days.
const gridStart = computed(() => startOfWeek(month.value))
const gridEnd = computed(() => addDays(startOfWeek(endOfMonth(month.value)), 6))

const weeks = computed(() => {
  const result = []
  let cursor = gridStart.value

  while (cursor <= gridEnd.value) {
    const week = []
    for (let index = 0; index < 7; index++) {
      week.push(cursor)
      cursor = addDays(cursor, 1)
    }
    result.push(week)
  }

  return result
})

let requestSeq = 0

const load = () => {
  const seq = ++requestSeq

  loading.value = true
  hrShiftScheduleApi.employee(props.employee.id, { from: gridStart.value, to: gridEnd.value }).then(response => {
    if (seq !== requestSeq)
      return
    const data = response.data.data

    days.value = Object.fromEntries(data.days.map(day => [day.date, day]))
    legend.value = data.legend
  }).catch(() => {
    if (seq === requestSeq)
      days.value = {}
  }).finally(() => {
    if (seq === requestSeq)
      loading.value = false
  })
}

watch([month, () => props.employee.id], load, { immediate: true })

const monthLabel = computed(() => formatDate(month.value, locale.value, { month: 'long', year: 'numeric' }))

const exceptionPreset = ref(null)

const openException = day => {
  if (!canAddException.value || day.status === 'not_employed')
    return
  exceptionPreset.value = {
    scope: 'employee',
    employee_id: props.employee.id,
    employee_name: props.employee.name,
    employee_branch_id: props.employee.branch_id,
    date_from: day.date,
    date_to: day.date,
    exception_type: day.status === 'working' ? 'day_off' : 'shift',
    lock_target: true,
  }
}

defineExpose({ refresh: load })
</script>

<template>
  <VCard>
    <VCardItem>
      <VCardTitle>{{ $t('hr.schedule.employee_title') }}</VCardTitle>
      <VCardSubtitle v-if="canAddException">
        {{ $t('hr.schedule.click_hint') }}
      </VCardSubtitle>
      <template #append>
        <div class="d-flex align-center gap-2">
          <VBtn
            icon
            size="small"
            variant="text"
            :aria-label="$t('hr.schedule.previous')"
            @click="month = addMonths(month, -1)"
          >
            <VIcon
              icon="tabler-chevron-left"
              class="flip-in-rtl"
            />
          </VBtn>
          <span class="text-body-1 font-weight-medium">{{ monthLabel }}</span>
          <VBtn
            icon
            size="small"
            variant="text"
            :aria-label="$t('hr.schedule.next')"
            @click="month = addMonths(month, 1)"
          >
            <VIcon
              icon="tabler-chevron-right"
              class="flip-in-rtl"
            />
          </VBtn>
          <VBtn
            size="small"
            variant="tonal"
            @click="month = startOfMonth(today)"
          >
            {{ $t('hr.schedule.today') }}
          </VBtn>
        </div>
      </template>
    </VCardItem>
    <VProgressLinear
      :active="loading"
      indeterminate
      color="primary"
    />
    <VCardText>
      <div class="hr-calendar">
        <div
          v-for="weekday in WEEKDAYS"
          :key="`h${weekday}`"
          class="hr-calendar__head"
        >
          {{ $t(`hr.weekdays_short.${weekday}`) }}
        </div>
        <template
          v-for="week in weeks"
          :key="week[0]"
        >
          <div
            v-for="date in week"
            :key="date"
            class="hr-calendar__day"
            :class="{ 'hr-calendar__day--outside': date.slice(0, 7) !== month.slice(0, 7), 'hr-calendar__day--today': date === today }"
          >
            <div class="hr-calendar__date">
              {{ dayOfMonth(date) }}
            </div>
            <HrScheduleDayCell
              v-if="days[date]"
              :day="days[date]"
              :clickable="canAddException && days[date].status !== 'not_employed'"
              @select="openException"
            />
          </div>
        </template>
      </div>
      <div
        v-if="legend.length"
        class="d-flex flex-wrap gap-3 mt-4"
      >
        <span
          v-for="item in legend"
          :key="item.shift_id"
          class="d-flex align-center gap-1 text-body-2"
        >
          <span
            class="hr-shift-swatch hr-shift-swatch--inline"
            :style="{ backgroundColor: shiftColor(item.color, item.shift_type) }"
          />
          <strong dir="ltr">{{ item.code }}</strong>
          <span class="hr-page__muted">{{ item.name }} (<span dir="ltr">{{ item.time_range }}</span>)</span>
        </span>
      </div>
    </VCardText>

    <HrShiftExceptionDialog
      :model-value="!!exceptionPreset"
      :preset="exceptionPreset ?? {}"
      @update:model-value="exceptionPreset = null"
      @saved="load"
    />
  </VCard>
</template>
