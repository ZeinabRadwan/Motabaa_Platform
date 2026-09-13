import AppSelect from '@core/components/app-form-elements/AppSelect.vue';
import AppTextField from '@core/components/app-form-elements/AppTextField.vue';
import AppTextarea from '@core/components/app-form-elements/AppTextarea.vue';
import {
  accommodationStatusItems,
  accommodationTypeItems,
  ageDiseaseItems,
  annualIncomeLevelItems,
  babyWeightBirthItems,
  birthTypeItems,
  caseHasProblemsItems,
  chronicDiseasesMotherItems,
  discoversCaseItems,
  durationPregnancyItems,
  emotionalGrowthItem,
  familyIncomeSourcesItems,
  fatherCaresItem,
  giveBirthItems,
  growthHistoryItems,
  kindOfSpeechItems,
  maritalStatusItems,
  mentalCognitiveItems,
  motherCaresItem,
  motherDuringPregnancyItems,
  movementSideItems,
  organicDiseasesItem,
  personalCareItems,
  relationshipFatherMotherItems,
  scientificLevel,
  skillsItems,
  socialAspectItems,
  typeLactationItems,
  understandCaseFatherItems,
  understandCaseItems,
  understandCaseThemItems,
  yesNoItems
} from './selectsItems';


const initFields = [{
    componentType: AppTextarea,
    label: 'caseDetails',
    vModel: 'caseDetails',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'caseBehavior',
    vModel: 'caseBehavior',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'uninterruptedBehaviors',
    vModel: 'uninterruptedBehaviors',
    cols:"12",
    md:"12",
  }];

const enhancement = [
    {
      componentType: AppTextField,
      label: 'favFoodDrink',
      vModel: 'favFoodDrink',
      cols:"12",
      md:"4",
    },
    {
      componentType: AppTextField,
      label: 'favGameActivity',
      vModel: 'favGameActivity',
      cols:"12",
      md:"4",
    },
    {
      componentType: AppTextField,
      label: 'favMoral',
      vModel: 'favMoral',
      cols:"12",
      md:"4",
    },
    {
      componentType: AppTextField,
      label: 'favGift',
      vModel: 'favGift',
      cols:"12",
      md:"4",
    },
    {
      componentType: AppTextarea,
      label: 'strengthPoints',
      vModel: 'strengthPoints',
      cols:"12",
      md:"12",
    },
    {
      componentType: AppTextarea,
      label: 'weakPoints',
      vModel: 'weakPoints',
      cols:"12",
      md:"12",
    },
  ];

const afterBirth = [
  {
    componentType: AppTextField,
    label: 'Jaundice',
    vModel: 'jaundice',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Dehydration',
    vModel: 'dehydration',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Lenght of stay at hospital',
    vModel: 'hospitalLenght',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: '1st seizures',
    vModel: 'firstSeizures',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Last seizer',
    vModel: 'lastSeizer',
    cols:"12",
    md:"4",
  },
];

const investigations = [
  {
    componentType: AppTextField,
    label: 'CT scan result',
    vModel: 'scanResult',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'x-ray',
    vModel: 'xRay',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'EEG result',
    vModel: 'EEGResult',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Others ',
    vModel: 'others',
    cols:"12",
    md:"4",
  },
];

const associatedDisorder = [
  {
    componentType: AppTextField,
    label: 'Vision',
    vModel: 'vision',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Hearing',
    vModel: 'hearing',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Speech',
    vModel: 'speech',
    cols:"12",
    md:"4",
  }
];
// berfore tables
const fieldsUpperSection ={
  initFields: initFields,
  enhancement:enhancement,
  afterBirth:afterBirth,
  investigations: investigations,
  associatedDisorder: associatedDisorder
}
  


const parentInfo = [
  {
    componentType: AppTextField,
    label: 'Name',
    vModel: 'name',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Nationality',
    vModel: 'nationality',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Job',
    vModel: 'job',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Age',
    vModel: 'age',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'Marital Status',
    vModel: 'maritalStatus',
    items: maritalStatusItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Number of marriages',
    vModel: 'marriagesNumber',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Children Number',
    vModel: 'childrenNumber',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'Scientific level',
    vModel: 'scientificLevel',
    items: scientificLevel,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Phone Number',
    vModel: 'phone',
    cols:"12",
    md:"4",
  },
];

const familyInfo = [
  {
    componentType: AppSelect,
    label: 'Is there a link between the father and the mother?',
    vModel: 'linkFatherMother',
    items: yesNoItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'relationshipFatherMother',
    vModel: 'relationshipFatherMother',
    items: relationshipFatherMotherItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'numberLiveWithCase',
    vModel: 'numberLiveWithCase',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'Accommodation type',
    vModel: 'accommodationType',
    items: accommodationTypeItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'Accommodation status',
    vModel: 'accommodationStatus',
    items: accommodationStatusItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Address',
    vModel: 'address',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'Family income sources',
    vModel: 'familyIncomeSources',
    items: familyIncomeSourcesItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'Annual income level',
    vModel: 'annualIncomeLevel',
    items: annualIncomeLevelItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'Number of siblings',
    vModel: 'numberSiblings',
    cols:"12",
    md:"4",
  },
];

const familyDealCase = [
  {
    componentType: AppSelect,
    label: 'motherUnderstands',
    vModel: 'motherUnderstands',
    items: understandCaseItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'motherCares',
    vModel: 'motherCares',
    items: motherCaresItem,
    cols:"12",
    md:"4",
  },
  {
    componentType: '',
    label: '',
    vModel: '',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'fatherUnderstands',
    vModel: 'fatherUnderstands',
    items: understandCaseFatherItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'fatherCares',
    vModel: 'fatherCares',
    items: fatherCaresItem,
    cols:"12",
    md:"4",
  },
  {
    componentType: '',
    label: '',
    vModel: '',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'siblingUnderstandCase',
    vModel: 'siblingUnderstandCase',
    items: understandCaseThemItems,
    cols:"12",
    md:"4",
  },
];

const caseHistory = [
  {
    componentType: AppSelect,
    label: 'discoversCase',
    vModel: 'discoversCase',
    items: discoversCaseItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'ageDisease',
    vModel: 'ageDisease',
    items: ageDiseaseItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextarea,
    label: 'ageAppearance',
    vModel: 'ageAppearance',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'summaryCase',
    vModel: 'summaryCase',
    cols:"12",
    md:"12",
  },

  {
    componentType: AppTextarea,
    label: 'historyCase',
    vModel: 'historyCase',
    cols:"12",
    md:"12",
  },
];

const effectDisorder = [
  {
    componentType: AppSelect,
    label: 'socialAspect',
    vModel: 'socialAspect',
    items: socialAspectItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'movementSide',
    vModel: 'movementSide',
    items: movementSideItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'personalCare',
    vModel: 'personalCare',
    items: personalCareItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'mentalCognitive',
    vModel: 'mentalCognitive',
    items: mentalCognitiveItems,
    cols:"12",
    md:"4",
  },

];

const evolutionaryHistory = [
  {
    componentType: AppTextField,
    label: 'MotherAgeBirth',
    vModel: 'MotherAgeBirth',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'durationPregnancy',
    vModel: 'durationPregnancy',
    items: durationPregnancyItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'motherDuringPregnancy',
    vModel: 'motherDuringPregnancy',
    items: motherDuringPregnancyItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'Birth type',
    vModel: 'birthType',
    items: birthTypeItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'chronicMother',
    vModel: 'chronicMother',
    items: chronicDiseasesMotherItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'medicinesMotherUses',
    vModel: 'medicinesMotherUses',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'giveBirth',
    vModel: 'giveBirth',
    items: giveBirthItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'disorderChildbirth',
    vModel: 'disorderChildbirth',
    items: yesNoItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'babyWeightBirth',
    vModel: 'babyWeightBirth',
    items: babyWeightBirthItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'typeLactation',
    vModel: 'typeLactation',
    items: typeLactationItems,
    cols:"12",
    md:"4",
  },

];

const linguisticHistory = [
  {
    componentType: AppTextField,
    label: 'attentionToSounds',
    vModel: 'attentionToSounds',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'babblingFirstMonths',
    vModel: 'babblingFirstMonths',
    items: yesNoItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'respondCallingName',
    vModel: 'respondCallingName',
    items: yesNoItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'firstWordCase',
    vModel: 'firstWordCase',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'kindOfSpeech',
    vModel: 'kindOfSpeech',
    items: kindOfSpeechItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'caseRepeatWords',
    vModel: 'caseRepeatWords',
    items: yesNoItems,
    cols:"12",
    md:"4",
  },

];

const other = [
  {
    componentType: AppTextarea,
    label: "WhatChildDoesWantSomehing",
    vModel: 'WhatChildDoesWantSomehing',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppSelect,
    label: 'sufferAnyProblem',
    vModel: 'sufferAnyProblem',
    items: caseHasProblemsItems,
    binds:{
      chips:true,
      multiple:true,
      'closable-chips': true
    },
    cols:"12",
    md:"6",
  },

];

const growthHistory = [

  {
    componentType: AppSelect,
    label: 'startSitting',
    vModel: 'startSitting',
    items: growthHistoryItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'startStandingUp',
    vModel: 'startStandingUp',
    items: growthHistoryItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'startWalking',
    vModel: 'startWalking',
    items: growthHistoryItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'startTeething',
    vModel: 'startTeething',
    items: growthHistoryItems,
    cols:"12",
    md:"4",
  },

];

const medicalHistory = [

  {
    componentType: AppSelect,
    label: 'organicDiseases',
    vModel: 'organicDiseases',
    items: organicDiseasesItem,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'previouslyHospitalized',
    vModel: 'previouslyHospitalized',
    items: yesNoItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'hadSurgeries',
    vModel: 'hadSurgeries',
    items: yesNoItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'anyInjuriesAccidents',
    vModel: 'anyInjuriesAccidents',
    items: yesNoItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'disabilitiesFamily',
    vModel: 'disabilitiesFamily',
    items: yesNoItems,
    cols:"12",
    md:"4",
  },

  {
    componentType: AppSelect,
    label: 'emotionalGrowth',
    vModel: 'emotionalGrowth',
    items: emotionalGrowthItem,
    cols:"12",
    md:"4",
  },


];
const generalCapabilities = [

  {
    componentType: AppSelect,
    label: 'attentionFocus',
    vModel: 'attentionFocus',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'absorptionUnderstanding',
    vModel: 'absorptionUnderstanding',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'perception',
    vModel: 'perception',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'remembering',
    vModel: 'remembering',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
 

];

const personalSkills = [

  {
    componentType: AppSelect,
    label: 'lotsActivty',
    vModel: 'lotsActivty',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'hurtHimself',
    vModel: 'hurtHimself',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'hurtOthers',
    vModel: 'hurtOthers',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'vandalism',
    vModel: 'vandalism',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'screaming',
    vModel: 'screaming',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'acceptOthersQuickly',
    vModel: 'acceptOthersQuickly',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'fitsAnger',
    vModel: 'fitsAnger',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
];


const selfReliance = [

  {
    componentType: AppSelect,
    label: 'eatingDrinking',
    vModel: 'eatingDrinking',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'askToBathroom',
    vModel: 'askToBathroom',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'changeClothes',
    vModel: 'changeClothes',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'putsShoes',
    vModel: 'putsShoes',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'washHands',
    vModel: 'washHands',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'brushingTeeth',
    vModel: 'brushingTeeth',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
];


const communicationSkills = [

  {
    componentType: AppSelect,
    label: 'verbalCommunication',
    vModel: 'verbalCommunication',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'eyeContact',
    vModel: 'eyeContact',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'social',
    vModel: 'social',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'linguisticDevelopment',
    vModel: 'linguisticDevelopment',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'expressiveAbility',
    vModel: 'expressiveAbility',
    items: skillsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'independenceSkills',
    vModel: 'independenceSkills',
    items: skillsItems,
    cols:"12",
    md:"4",
  },

  {    
    componentType: AppTextarea,
    label: 'centersOrSchoolsAttend',
    vModel: 'centersOrSchoolsAttend',
    cols:"12",
    md:"12",
  },
  {    
    componentType: AppTextarea,
    label: 'IEP',
    vModel: 'IEP',
    cols:"12",
    md:"12",
  },
  {    
    componentType: AppTextarea,
    label: 'behavioralProblemsMotherWants',
    vModel: 'behavioralProblemsMotherWants',
    cols:"12",
    md:"12",
  },
  {    
    componentType: AppTextarea,
    label: 'motherWantsNurture',
    vModel: 'motherWantsNurture',
    cols:"12",
    md:"12",
  },
  {    
    componentType: AppTextarea,
    label: 'previouslyTrained',
    vModel: 'previouslyTrained',
    cols:"12",
    md:"12",
  },
  {    
    componentType: AppTextarea,
    label: 'additionalFamilyNotes',
    vModel: 'additionalFamilyNotes',
    cols:"12",
    md:"12",
  },
];


// after tables
const fieldsBottomSection ={
  fatherInfo: parentInfo,
  motherInfo: parentInfo,
  familyInfo: familyInfo,
  familyDealCase: familyDealCase,
  caseHistory: caseHistory,
  effectDisorder:effectDisorder,
  evolutionaryHistory: evolutionaryHistory,
  linguisticHistory:linguisticHistory,
  other:other,
  growthHistory:growthHistory,
  medicalHistory:medicalHistory,
  generalCapabilities:generalCapabilities,
  personalSkills:personalSkills,
  selfReliance:selfReliance,
  communicationSkills:communicationSkills
}



// gernrate object for (Ref) fun to save data in it
const formData = {};

const mergedObject = { ...fieldsBottomSection, ...fieldsUpperSection };

for (const key in mergedObject) {
  formData[key] = {};

  if (mergedObject[key] && Array.isArray(mergedObject[key])) {
      mergedObject[key].forEach(item => {
          const vModelKey = item.vModel;
          formData[key][vModelKey] = item.hasOwnProperty('items') ? [] : '';
      });
  }
}


export { fieldsBottomSection, fieldsUpperSection, formData };


export const capabilitiesSurrentStatus = {
  remembering: {
        name:'remembering',
        evaluation:'',
        notes:''
  },
  attention: {
        name:'attention',
        evaluation:'',
        notes:''
  },
  perception: {
        name:'perception',
        evaluation:'',
        notes:''
  },
  thinking: {
        name:'thinking',
        evaluation:'',
        notes:''
  },
  imagination: {
        name:'imagination',
        evaluation:'',
        notes:''
  }
};

export const sensoryAbilities = {
  auditory: {
        name:'auditory',
        evaluation:'',
        notes:''
  },
  visual: {
        name:'visual',
        evaluation:'',
        notes:''
  },
  visualMovementAynergy: {
        name:'visualMovementAynergy',
        evaluation:'',
        notes:''
  },
};


export const languageAbilitie = {
  languageAbilitie: {
        name:'languageAbilitie',
        evaluation:'',
        notes:''
  },
  expressingAttitudes: {
        name:'expressingAttitudes',
        evaluation:'',
        notes:''
  },
  sentenceOrganization: {
        name:'sentenceOrganization',
        evaluation:'',
        notes:''
  },
};

export const physicalMotorAbilities = {
  useSmallMuscles: {
        name:'useSmallMuscles',
        evaluation:'',
        notes:''
  },
  useLargeMuscles: {
        name:'useLargeMuscles',
        evaluation:'',
        notes:''
  },
  movmentCoordination: {
        name:'movmentCoordination',
        evaluation:'',
        notes:''
  },
};


export const adaptiveBehaviorSkills = {
  selfCare: {
        name:'selfCare',
        evaluation:'',
        notes:''
  },
  personalAutonomy: {
        name:'personalAutonomy',
        evaluation:'',
        notes:''
  },
  relationshipPeers: {
        name:'relationshipPeers',
        evaluation:'',
        notes:''
  },
  contactOthers: {
        name:'contactOthers',
        evaluation:'',
        notes:''
  },
};

export const dailySkills = {
  personalCleanliness: {
        name:'personalCleanliness',
        evaluation:'',
        notes:''
  },
  eatingAndDrinking: {
        name:'eatingAndDrinking',
        evaluation:'',
        notes:''
  },
  confusion: {
        name:'confusion',
        evaluation:'',
        notes:''
  },
  outsideControl: {
        name:'outsideControl',
        evaluation:'',
        notes:''
  },

};


export const academicSkills = {
  reading: {
        name:'reading',
        evaluation:'',
        notes:''
  },
  writing: {
        name:'writing',
        evaluation:'',
        notes:''
  },
  mathematicsConcepts: {
        name:'mathematicsConcepts',
        evaluation:'',
        notes:''
  },
};

export const generalHealthCondition = {
  healthy: {
        name:'healthy',
        evaluation:'',
        notes:''
  },
  notHealthy: {
        name:'notHealthy',
        evaluation:'',
        notes:''
  },
  notes : {
        name:'notes',
        evaluation:'',
        notes:''
  },
};