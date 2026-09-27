  
<script setup>
    import i18n from '@/plugins/i18n/index.js';
    import { watch } from 'vue';
    import { toHijri, toGregorian } from "hijri-converter";

    const props = defineProps(['modelValue']);
    // const { modelValue } = toRefs(props);
    const label = computed(() => useAttrs().label)
    const labelClasses = computed(() => useAttrs().labelClasses)
    const rules = computed(() => useAttrs().rules)

    const modelValue = computed({
        get: () => props.modelValue,
    });

    const emit = defineEmits();

    const today = new Date()
    const currentHijri = toHijri(today.getFullYear(), today.getMonth() + 1, today.getDate())
    const startYear = 1300
    const currentYear = currentHijri.hy + 2

    const hijri_months ={ 
        ar: [
            {"title": "محرم", "value": '01'},
            {"title": "صفر", "value": '02'},
            {"title": "ربيع الأول", "value": '03'},
            {"title": "ربيع الآخر", "value": '04'},
            {"title": "جمادى الأولى", "value": '05'},
            {"title": "جمادى الآخرة", "value": '06'},
            {"title": "رجب", "value": '07'},
            {"title": "شعبان", "value": '08'},
            {"title": "رمضان", "value": '09'},
            {"title": "شوال", "value": '10'},
            {"title": "ذو القعدة", "value": '11'},
            {"title": "ذو الحجة", "value": '12'}
            ],
        en : [
            {"title": "Muharram", "value": '01'},
            {"title": "Safar", "value": '02'},
            {"title": "Rabi' al-Awwal", "value": '03'},
            {"title": "Rabi' al-Thani", "value": '04'},
            {"title": "Jumada al-Awwal", "value": '05'},
            {"title": "Jumada al-Thani", "value": '06'},
            {"title": "Rajab", "value": '07'},
            {"title": "Sha'ban", "value": '08'},
            {"title": "Ramadan", "value": '09'},
            {"title": "Shawwal", "value": '10'},
            {"title": "Dhu al-Qi'dah", "value": '11'},
            {"title": "Dhu al-Hijjah", "value": '12'}
        ]
    }

    const years = Array.from({ length: currentYear - startYear + 1 }, (_, index) => startYear + index)
    const months = ref(hijri_months[i18n.global.locale.value]);
    const days30 = Array.from({ length: 30 }, (_, index) => (index + 1).toString().padStart(2, '0'));
    const days29 = Array.from({ length: 29 }, (_, index) => (index + 1).toString().padStart(2, '0'));

    const selectedYear = ref(null);
    const selectedMonth = ref(null);
    const selectedDay = ref(null);
    const fullDate = ref(null);

    const updateVModel = () =>{
        if (selectedYear.value != null && selectedMonth.value != null && selectedDay.value != null ){
            fullDate.value = `${selectedYear.value}-${selectedMonth.value}-${selectedDay.value}`

            emit('update:modelValue', convertHijriToGregorian(fullDate.value));
        }
    }
    const returnMonths = () => {
        return hijri_months[i18n.global.locale.value];
    };

    const convertHijriToGregorian = (hijriDate) => {
        const [year, month, day] = hijriDate.split('-').map(Number);
        const gregorianDate = toGregorian(year, month, day);
        const formattedGregorianDate = `${gregorianDate.gy}-${String(gregorianDate.gm).padStart(2, '0')}-${String(gregorianDate.gd).padStart(2, '0')}`;
        return formattedGregorianDate;
    }
    const convertGregorianToHijri = (GregorianTDate) => {
        if(GregorianTDate != null && GregorianTDate != ''){
            const [year, month, day] = GregorianTDate.split('T')[0].split('-').map(Number);
            const hijriDate = toHijri(year, month, day);
            selectedYear.value = hijriDate.hy;
            selectedMonth.value = String(hijriDate.hm).padStart(2, '0');
            selectedDay.value = String(hijriDate.hd).padStart(2, '0');
        }
    }

    watch(selectedDay, query => {
        updateVModel()
    })
    watch(selectedMonth, query => {
        updateVModel()
    })
    watch(selectedYear, query => {
        updateVModel()
    })
    watch(modelValue, query => {
        convertGregorianToHijri(modelValue.value)
    })
    convertGregorianToHijri(modelValue.value)

</script>


<template>
    <div>
        <VLabel
            v-if="label"
            :class="labelClasses"
            class="mb-1 text-body-2 text-high-emphasis"
            :text="$t(label)"
        />
        <div class="">
            <div class="custom-date-picker">
                <AppAutocomplete :rules="rules" v-model="selectedYear" :items="years" :placeholder="$t('year')" class="year-select no-arrow-select"></AppAutocomplete>
                <AppAutocomplete :rules="rules" v-model="selectedMonth" :items="returnMonths()" :placeholder="$t('month')" class="month-select no-arrow-select"></AppAutocomplete>
                <AppAutocomplete :rules="rules" v-model="selectedDay" :items="days30"  :placeholder="$t('day')" class="day-select no-arrow-select"></AppAutocomplete>
                <!-- <AppAutocomplete :rules="rules" v-if="selectedMonth == 2 || selectedMonth == 3 || selectedMonth == 4 || selectedMonth == 6 || selectedMonth == 9 || selectedMonth == 12 || selectedMonth == null"  v-model="selectedDay" :items="days30"  :placeholder="$t('day')" class="day-select no-arrow-select"></AppAutocomplete> -->
                <!-- <AppAutocomplete :rules="rules" v-if="selectedMonth == 1 || selectedMonth == 5 || selectedMonth == 7 || selectedMonth == 8 || selectedMonth == 10 || selectedMonth == 11" v-model="selectedDay" :items="days29" :placeholder="$t('day')" class="day-select no-arrow-select"></AppAutocomplete> -->
            </div>
        </div>

        
    </div>

</template>

  <style>
  .custom-date-picker {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
  }
    .no-arrow-select .v-field__append-inner {
        display: none; /* Hide the arrow icon */
    }
    .no-arrow-select .v-messages{
        display: none; /* Hide the arrow icon */
    }
</style>
  