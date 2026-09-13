import { servicesApi } from "@/plugins/apis/servicesReqest";
import { termsApi } from "@/plugins/apis/termsRequest";
import { can } from '@layouts/plugins/casl';

import i18n from '@/plugins/i18n/index.js';

const services = servicesApi()
const terms = termsApi()

export const attendanceItems = () => {
  return [
    {
      title: i18n.global.t("attendances.attend"),
      class :"text-success",
      value: 1
    },
    {
      title: i18n.global.t("attendances.absent"), 
      class :"text-error", 
      value: 0
    },
  ]
  
}

export const statusItems = () => {
  return [
      {
        title: i18n.global.t('All'),
        value: 'all',
      },
      {
        title: i18n.global.t('active_status'),
        value: 'active',
      },
      {
        title: i18n.global.t('Inactive'),
        value: 'inactive',
      },
    ]
}

export const reinforcementItems = () => {
  return [
      {
        title: i18n.global.t('verbal'),
        value: 'verbal',
      },
      {
        title: i18n.global.t('material'),
        value: 'material',
      },
      {
        title: i18n.global.t('socialPerson'),
        value: 'social',
      },
    ]
}

export const goalsStandardItems = () => {
  return [
      {
        title: i18n.global.t('in_3_attempts_out_of_5'),
        value: 'in_3_attempts_out_of_5',
      },
      {
        title: i18n.global.t('at_an_80_percent_rate'),
        value: 'at_an_80_percent_rate',
      },
      {
        title: i18n.global.t('correctly'),
        value: 'correctly',
      },
    ]
}

export const goalsGeneralizationItems = () => {
  return [
      {
        title: i18n.global.t('in_two_different_places'),
        value: 'in_two_different_places',
      },
      {
        title: i18n.global.t('with_two_different_people'),
        value: 'with_two_different_people',
      },
    ]
}

export const insuranceItems = () => {
    return [
        {
          title: i18n.global.t('Beneficiary'),
          value: '1',
        },
        {
          title: i18n.global.t('Not beneficiary'),
          value: '0',
        },
      ]
}

export const periodItems = () => {
  return [
      {
        title: i18n.global.t('Morning'),
        value: '1',
      },
      {
        title: i18n.global.t('Evening'),
        value: '0',
      },
    ]
}


export const periodTermItems = (term = null) => {
  const count = Number(term?.periods_count) > 0
    ? Number(term.periods_count)
    : (Array.isArray(term?.evaluation_dates) && term.evaluation_dates.length
      ? term.evaluation_dates.length
      : 4)

  return Array.from({ length: count }, (_, index) => ({
    title: index === count - 1
      ? i18n.global.t('goals.final_period')
      : i18n.global.t('goals.period_n', { n: index + 1 }),
    value: String(index),
  }))
}

export const scaleTypeItems = () => {
  let arr = [
      {
          title: i18n.global.t("educational"),
          value: "educational",
      },
      {
          title: i18n.global.t("Occupational therapy"),
          value: "occupational therapy",
      },
      {
          title: i18n.global.t("physical therapy"),
          value: "physical therapy",
      },
      {
          title: i18n.global.t("Pronouncement"),
          value: "pronouncement",
      },
      {
          title: i18n.global.t("Psychiatric treatment"),
          value: "psychiatric treatment",
      },
      {
          title: i18n.global.t("independent.independent_skills"),
          value: "independent",
      },
  ];
  if(can('admin', 'admin') || can('manager', 'manager')  || can('parent', 'parent') || can('behavior_analyst', 'behavior_analyst') || can('aba_specialist', 'aba_specialist')){
    return arr;
  }
  let filteredArray = arr;
  if(!can('occupational_specialist', 'occupational_specialist')){
    filteredArray = filteredArray.filter(item => item.value !== "occupational therapy");
  }
  if(!can('pronunciation_speech_specialist', 'pronunciation_speech_specialist')){
    filteredArray = filteredArray.filter(item => item.value !== "pronouncement");
  }
  if(!can('physiotherapist_specialist', 'physiotherapist_specialist')){
    filteredArray = filteredArray.filter(item => item.value !== "physical therapy");
  }
  if(!can('teacher', 'teacher')){
    filteredArray = filteredArray.filter(item => item.value !== "educational" && item.value !== "independent");
  }
  if(!can('psychotherapist_specialist', 'psychotherapist_specialist')){
    filteredArray = filteredArray.filter(item => item.value !== "psychiatric treatment");
  }
  return filteredArray;
}

export const scaleTypeFilterItems = () => {
  let arr = scaleTypeItems();
  const filteredArray = arr.filter(item => item.value !== "educational" && item.value !== "independent");
  return filteredArray;
}

export const performanceEvaluationItems = () => {
  return [
      {
        title: i18n.global.t('goals.perfect'),
        value: '0',
      },
      {
        title: i18n.global.t('goals.perfected_with_help'),
        value: '1',
      },
      {
        title: i18n.global.t('goals.needs_training'),
        value: '2',
      },
    ]
}

export const serviceitems = async () => {
    let response = services.fetch();
    return (await response).data.data;
}

export const termItems = async () => {
  let response = terms.items();
  return (await response).data.data;
}

export const periodItemsSearch = (key) => {
    return periodItems().filter(item => item.value === key);
}
export const performanceEvaluationItemsSearch = (key) => {
  return performanceEvaluationItems().filter(item => item.value === key);
}

export const departmentsItems = () => {
  return [
    {
        title: i18n.global.t("department.general"),
        value: "general",
    },
    {
        title: i18n.global.t("department.occupational_therapy"),
        value: "occupational_therapy",
    },
    {
        title: i18n.global.t("department.physical_therapy"),
        value: "physical_therapy",
    },
    {
        title: i18n.global.t("department.pronouncement"),
        value: "pronouncement",
    },
    {
        title: i18n.global.t("department.psychiatric_treatment"),
        value: "psychiatric_treatment",
    },
    {
        title: i18n.global.t("department.social"),
        value: "social",
    },
    {
        title: i18n.global.t("department.nursing"),
        value: "nursing",
    },
  ];
}

export const meetingRoomsTypes = () => {
    return [
        {
          title: i18n.global.t('general'),
          value: '1',
        },
        {
          title: i18n.global.t('administrative'),
          value: '2',
        },
      ]
}

export const independentAssistanceTypeItems = () => {
    return [
        {
          title: i18n.global.t('independent.total_physical_assistance'),
          value: '1',
        },
        {
          title: i18n.global.t('independent.partial_assistance'),
          value: '2',
        },
        {
          title: i18n.global.t('independent.verbal_assistance'),
          value: '3',
        },
        {
          title: i18n.global.t('independent.gestures'),
          value: '4',
        },
      ]
}

export const genderTypes = () => {
  return [
      {
        title: i18n.global.t('male'),
        value: '1',
      },
      {
        title: i18n.global.t('female'),
        value: '2',
      }
    ]
}

export const departmentItems = () => {
  return [
    'general',
    'occupational_therapy',
    'physical_therapy',
    'pronouncement',
    'psychiatric_treatment',
    'social',
    'nursing',
    'administration',
    'education',
  ].map(value => ({
    title: i18n.global.t(`department.${value}`),
    value,
  }))
}

export const workShiftItems = () => {
  return [
    { title: i18n.global.t('employee_affairs.work_shift_morning'), value: 'morning' },
    { title: i18n.global.t('employee_affairs.work_shift_evening'), value: 'evening' },
  ]
}

export const workShiftValues = shift => {
  if (shift === 'both')
    return ['morning', 'evening']
  if (shift === 'morning' || shift === 'evening')
    return [shift]

  return Array.isArray(shift) ? shift.filter(value => value === 'morning' || value === 'evening') : []
}

export const serializeWorkShift = values => {
  const set = new Set((values || []).filter(Boolean))
  const morning = set.has('morning')
  const evening = set.has('evening')
  if (morning && evening)
    return 'both'
  if (morning)
    return 'morning'
  if (evening)
    return 'evening'

  return ''
}

export const contractTypeItems = () => {
  return [
    { title: i18n.global.t('employee_affairs.contract_types.permanent'), value: 'permanent' },
    { title: i18n.global.t('employee_affairs.contract_types.temporary'), value: 'temporary' },
    { title: i18n.global.t('employee_affairs.contract_types.part_time'), value: 'part_time' },
  ]
}

export const leaveTypeItems = () => {
  return [
    { title: i18n.global.t('employee_leaves.types.annual'), value: 'annual' },
    { title: i18n.global.t('employee_leaves.types.sick'), value: 'sick' },
    { title: i18n.global.t('employee_leaves.types.unpaid'), value: 'unpaid' },
    { title: i18n.global.t('employee_leaves.types.emergency'), value: 'emergency' },
    { title: i18n.global.t('employee_leaves.types.other'), value: 'other' },
  ]
}

export const leaveStatusItems = () => {
  return [
    { title: i18n.global.t('employee_leaves.statuses.pending'), value: 'pending' },
    { title: i18n.global.t('employee_leaves.statuses.approved'), value: 'approved' },
    { title: i18n.global.t('employee_leaves.statuses.rejected'), value: 'rejected' },
  ]
}

export const hrAlertItems = () => {
  return [
    { title: i18n.global.t('employee_affairs.contracts_ending'), value: 'contracts_ending' },
    { title: i18n.global.t('employee_affairs.ids_expiring'), value: 'ids_expiring' },
    { title: i18n.global.t('employee_affairs.documents_expiring'), value: 'documents_expiring' },
    { title: i18n.global.t('employee_affairs.expiring_ids_or_docs'), value: 'expiring_ids_or_docs' },
  ]
}

export const documentTypeItems = () => {
  return [
    { title: i18n.global.t('employee_documents.types.id'), value: 'id' },
    { title: i18n.global.t('employee_documents.types.passport'), value: 'passport' },
    { title: i18n.global.t('employee_documents.types.contract'), value: 'contract' },
    { title: i18n.global.t('employee_documents.types.medical'), value: 'medical' },
    { title: i18n.global.t('employee_documents.types.other'), value: 'other' },
  ]
}

export const paymentMethods = () => {
  return [
      {
        title: i18n.global.t('payments.cash'),
        value: '1',
      },
      {
        title: i18n.global.t('payments.cheque'),
        value: '2',
      },
      {
        title: i18n.global.t('payments.transfer'),
        value: '3',
      }
    ]
}