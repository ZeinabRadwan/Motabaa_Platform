<script setup>
import HrScheduleDayCell from '@/components/hr/HrScheduleDayCell.vue'
import { useHrLookups } from '@/composables/useHrLookups'
import { hrShiftScheduleApi } from '@/plugins/apis/hrRequest'
import { addDays, formatDate, shiftColor, startOfWeek, todayIso, weekdayOf } from '@/utils/hrShifts'
import HrShiftExceptionDialog from '@/views/hr/attendance/HrShiftExceptionDialog.vue'
import { can } from '@layouts/plugins/casl'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()
const { lookups } = useHrLookups()

const canEdit = computed(() => can('edit_hr-attendance-setup', 'edit_hr-attendance-setup'))

const RANGE_DAYS = { week: 7, two_weeks: 14 }

const rangeMode = ref('week')
const from = ref(startOfWeek(todayIso()))
const to = computed(() => addDays(from.value, RANGE_DAYS[rangeMode.value] - 1))
const today = todayIso()

const branchFilter = ref(null)
const departmentFilter = ref(null)
const shiftFilter = ref(null)
const search = ref('')
const page = ref(1)
const perPage = 15

const rows = ref([])
const dates = ref([])
const legend = ref([])
const total = ref(0)
const loading = ref(false)

let requestSeq = 0

const load = () => {
  const seq = ++requestSeq

  loading.value = true
  hrShiftScheduleApi.team({
    from: from.value,
    to: to.value,
    branch_id: branchFilter.value || undefined,
    department_id: departmentFilter.value || undefined,
    shift_id: shiftFilter.value || undefined,
    q: search.value || undefined,
    page: page.value,
    per_page: perPage,
  }).then(response => {
    if (seq !== requestSeq)
      return
    const data = response.data.data

    rows.value = data.rows
    dates.value = data.dates
    legend.value = data.legend
    total.value = data.total
  }).catch(() => {
    if (seq === requestSeq) {
      rows.value = []
      total.value = 0
    }
  }).finally(() => {
    if (seq === requestSeq)
      loading.value = false
  })
}

let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})
watch([branchFilter, departmentFilter, shiftFilter, rangeMode], () => {
  page.value = 1
  load()
})
watch([from, page], load)
onMounted(load)

const shift = direction => {
  from.value = addDays(from.value, direction * RANGE_DAYS[rangeMode.value])
}

const goToday = () => {
  from.value = startOfWeek(todayIso())
}

const rangeLabel = computed(() => `${formatDate(from.value, locale.value, { day: 'numeric', month: 'short' })} – ${formatDate(to.value, locale.value, { day: 'numeric', month: 'short', year: 'numeric' })}`)

const pageCount = computed(() => Math.max(1, Math.ceil(total.value / perPage)))

const weekend = computed(() => new Set([5, 6]))

const shiftItems = computed(() => lookups.value.shifts)

// Exception from a cell
const exceptionPreset = ref(null)

const openException = (row, day) => {
  if (!canEdit.value || day.status === 'not_employed')
    return
  exceptionPreset.value = {
    scope: 'employee',
    employee_id: row.employee.id,
    employee_name: row.employee.name,
    employee_branch_id: row.employee.branch_id,
    date_from: day.date,
    date_to: day.date,
    exception_type: day.status === 'working' ? 'day_off' : 'shift',
    lock_target: true,
  }
}

defineExpose({ refresh: load })
</script>

<template>
  <div>
    <VCardText class="pb-2">
      <VRow class="align-center">
        <VCol
          cols="12"
          md="5"
          class="d-flex align-center flex-wrap gap-2"
        >
          <VBtnGroup
            density="comfortable"
            variant="outlined"
            divided
          >
            <VBtn
              icon
              :aria-label="$t('hr.schedule.previous')"
              @click="shift(-1)"
            >
              <VIcon
                icon="tabler-chevron-left"
                class="flip-in-rtl"
              />
            </VBtn>
            <VBtn @click="goToday">
              {{ $t('hr.schedule.today') }}
            </VBtn>
            <VBtn
              icon
              :aria-label="$t('hr.schedule.next')"
              @click="shift(1)"
            >
              <VIcon
                icon="tabler-chevron-right"
                class="flip-in-rtl"
              />
            </VBtn>
          </VBtnGroup>
          <span class="text-h6">{{ rangeLabel }}</span>
        </VCol>
        <VCol
          cols="12"
          md="7"
          class="d-flex justify-md-end"
        >
          <VBtnToggle
            v-model="rangeMode"
            mandatory
            divided
            density="comfortable"
            variant="outlined"
            color="primary"
          >
            <VBtn value="week">
              {{ $t('hr.schedule.week') }}
            </VBtn>
            <VBtn value="two_weeks">
              {{ $t('hr.schedule.two_weeks') }}
            </VBtn>
          </VBtnToggle>
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <AppTextField
            v-model="search"
            :placeholder="$t('Search')"
            prepend-inner-icon="tabler-search"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <AppSelect
            v-model="branchFilter"
            :items="lookups.branches"
            :placeholder="$t('hr.fields.branch')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <AppSelect
            v-model="departmentFilter"
            :items="lookups.departments"
            :placeholder="$t('hr.fields.department')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
        <VCol
          cols="12"
          sm="6"
          md="3"
        >
          <AppSelect
            v-model="shiftFilter"
            :items="shiftItems"
            :placeholder="$t('hr.fields.shift')"
            clearable
            clear-icon="tabler-x"
          />
        </VCol>
      </VRow>
    </VCardText>

    <div
      v-if="legend.length"
      class="d-flex flex-wrap align-center gap-3 px-6 pb-3"
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
      <span class="d-flex align-center gap-1 hr-page__muted">
        <VIcon
          icon="tabler-moon"
          size="16"
        />{{ $t('hr.schedule.status.rest') }}
      </span>
      <span class="d-flex align-center gap-1 hr-page__muted">
        <VIcon
          icon="tabler-calendar-event"
          size="16"
        />{{ $t('hr.schedule.exception') }}
      </span>
    </div>

    <VProgressLinear
      :active="loading"
      indeterminate
      color="primary"
    />
    <div class="hr-schedule">
      <table class="hr-schedule__table">
        <thead>
          <tr>
            <th class="hr-schedule__employee">
              {{ $t('hr.fields.employee') }}
            </th>
            <th
              v-for="date in dates"
              :key="date.date"
              :class="{ 'hr-schedule__day--today': date.date === today, 'hr-schedule__day--weekend': weekend.has(date.weekday) }"
            >
              <div class="hr-page__muted">
                {{ $t(`hr.weekdays_short.${weekdayOf(date.date)}`) }}
              </div>
              <div class="font-weight-medium">
                {{ formatDate(date.date, locale, { day: 'numeric', month: 'short' }) }}
              </div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.employee.id"
          >
            <td class="hr-schedule__employee">
              <RouterLink
                :to="{ name: 'hr-employees-view-id', params: { id: row.employee.id }, query: { tab: 'attendance' } }"
                class="font-weight-medium"
              >
                {{ row.employee.name }}
              </RouterLink>
              <div class="hr-page__muted">
                <span dir="ltr">{{ row.employee.employee_number }}</span>
                <span v-if="row.employee.branch_name"> · {{ row.employee.branch_name }}</span>
              </div>
            </td>
            <td
              v-for="day in row.days"
              :key="day.date"
              :class="{ 'hr-schedule__day--today': day.date === today }"
            >
              <HrScheduleDayCell
                :day="day"
                :compact="rangeMode === 'two_weeks'"
                :clickable="canEdit && day.status !== 'not_employed'"
                @select="openException(row, day)"
              />
            </td>
          </tr>
          <tr v-if="!rows.length && !loading">
            <td
              :colspan="dates.length + 1"
              class="text-center hr-page__muted pa-6"
            >
              {{ $t('hr.schedule.empty') }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="d-flex align-center justify-space-between flex-wrap gap-2 pa-4">
      <span class="hr-page__muted">
        {{ canEdit ? $t('hr.schedule.click_hint') : '' }}
      </span>
      <VPagination
        v-if="pageCount > 1"
        v-model="page"
        :length="pageCount"
        :total-visible="5"
        density="comfortable"
      />
    </div>

    <HrShiftExceptionDialog
      :model-value="!!exceptionPreset"
      :preset="exceptionPreset ?? {}"
      @update:model-value="exceptionPreset = null"
      @saved="load"
    />
  </div>
</template>
