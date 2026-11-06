<script setup>
import { dayAppearance, formatMinutes } from '@/utils/hrShifts'
import { useI18n } from 'vue-i18n'

// One resolved schedule day (ScheduledShiftDay::toArray) as a compact coloured cell with a details tooltip.
const props = defineProps({
  day: { type: Object, required: true },
  compact: { type: Boolean, default: false },
  clickable: { type: Boolean, default: false },
})

const emit = defineEmits(['select'])

const { t } = useI18n()

const look = computed(() => dayAppearance(props.day))
const working = computed(() => props.day.status === 'working')

const cellStyle = computed(() => (look.value.color
  ? { backgroundColor: `${look.value.color}1F`, borderInlineStartColor: look.value.color }
  : {}))

const details = computed(() => {
  const day = props.day
  const lines = []

  if (working.value) {
    lines.push(`${day.shift_name || t('hr.exceptions.types.custom_hours')} · ${day.start_time} - ${day.end_time}${day.is_overnight ? ` (${t('hr.shifts.next_day')})` : ''}`)
    if (day.break_minutes)
      lines.push(t('hr.shifts.break_short', { value: formatMinutes(day.break_minutes, t) }) + (day.break_start ? ` · ${day.break_start} - ${day.break_end}` : ''))
    lines.push(`${t('hr.shifts.expected_hours')}: ${formatMinutes(day.expected_minutes, t)}`)
    lines.push(t('hr.schedule.grace_summary', { late: day.late_grace_minutes, early: day.early_leave_grace_minutes }))
    if (day.rotation_day)
      lines.push(t('hr.shifts.cycle_day', { day: day.rotation_day }))
  }
  else if (look.value.labelKey) {
    lines.push(t(look.value.labelKey))
  }
  if (day.source === 'exception') {
    lines.push(`${t('hr.schedule.exception')}: ${t(`hr.exceptions.types.${day.exception_type}`)}`)
    if (day.exception_reason)
      lines.push(day.exception_reason)
  }

  return lines
})
</script>

<template>
  <div
    class="hr-schedule-cell"
    :class="[`hr-schedule-cell--${day.status}`, { 'hr-schedule-cell--clickable': clickable, 'hr-schedule-cell--compact': compact }]"
    :style="cellStyle"
    @click="clickable && emit('select', day)"
  >
    <template v-if="working">
      <div class="hr-schedule-cell__title">
        <span dir="ltr">{{ day.shift_code || '•' }}</span>
        <VIcon
          v-if="look.icon"
          :icon="look.icon"
          size="14"
        />
      </div>
      <div
        v-if="!compact"
        class="hr-schedule-cell__time"
        dir="ltr"
      >
        {{ day.start_time }}–{{ day.end_time }}<sup v-if="day.is_overnight">+1</sup>
      </div>
    </template>
    <template v-else>
      <VIcon
        v-if="look.icon"
        :icon="look.icon"
        size="14"
      />
      <span
        v-if="!compact && look.labelKey && day.status !== 'unscheduled'"
        class="hr-schedule-cell__label"
      >{{ $t(look.labelKey) }}</span>
    </template>
    <VTooltip
      activator="parent"
      location="top"
      :disabled="!details.length"
    >
      <div
        v-for="(line, index) in details"
        :key="index"
      >
        {{ line }}
      </div>
    </VTooltip>
  </div>
</template>
