import AppSelect from '@core/components/app-form-elements/AppSelect.vue';
import AppTextField from '@core/components/app-form-elements/AppTextField.vue';
import AppTextarea from '@core/components/app-form-elements/AppTextarea.vue';
import {
  DialogueAndSpeechItems,
  InitiativeAndIndependenceItems,
  OtherAppearancesItems,
  ResidentActivityItems,
  associatedFamilyItems,
  attentionItems,
  awarenessItems,
  behaviorConsistencyItems,
  bodyPositionItems,
  clotheItems,
  completelyFamilyItems,
  conscienceItems,
  disabilityItems,
  externalBehaviourItems,
  eyeContactItems,
  faceFeaturesItems,
  focusItems,
  manifestationsOfExternalBehaviorItems,
  memoryItems,
  moodItems,
  movmentBehaviourItems, otherNotesItems,
  outputFunctionsItems,
  perceptionItems,
  physicalHealthItems,
  relationshipWithTheResidentsAndTheDegreeItems,
  retardationTypeItems,
  wayOfEatingItems
} from './selectsItems';


const initFields = [{
    componentType: AppTextarea,
    label: 'diagnosisChild',
    vModel: 'diagnosisChild',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'description',
    vModel: 'description',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'reasons',
    vModel: 'reasons',
    cols:"12",
    md:"12",
  }];

const healthStatus = [
    {
      componentType: AppTextField,
      label: 'medicalDiagnosis',
      vModel: 'medicalDiagnosis',
      cols:"12",
      md:"4",
    },
    {
      componentType: AppTextField,
      label: "useOfMedications",
      vModel: 'useOfMedications',
      cols:"12",
      md:"4",
    },
    {
      componentType: AppTextField,
      label: "haveDiet",
      vModel: 'haveDiet',
      cols:"12",
      md:"4",
    },
    {
      componentType: AppTextField,
      label: "typeDiet",
      vModel: 'typeDiet',
      cols:"12",
      md:"4",
    },
  ];

const family = [ 
  {
    componentType: AppTextField,
    label: "numberFamilyMembers",
    vModel: 'numberFamilyMembers:',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "generalPersonalityTraits",
    vModel: 'generalPersonalityTraits',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "relationshipWithTheMother",
    vModel: 'relationshipWithTheMother',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "childrenRelationship",
    vModel: 'childrenRelationship',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "relationshipCase",
    vModel: 'relationshipCase',
    cols:"12",
    md:"4",
  },
];

const mother = [
  {
    componentType: AppTextField,
    label: "GeneralPersonalityTraits",
    vModel: 'GeneralPersonalityTraits',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "relationshipWithTheFather",
    vModel: 'relationshipWithTheFather',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'herChildrenRelationship',
    vModel: 'herChildrenRelationship',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'herRelationshipCase',
    vModel: 'herRelationshipCase',
    cols:"12",
    md:"4",
  },
];

const brothersAndSisters = [
  {
    componentType: AppTextField,
    label: 'theirRelationshipToEachOther',
    vModel: 'theirRelationshipToEachOther',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: 'broSisRelationshipCase',
    vModel: 'broSisRelationshipCase',
    cols:"12",
    md:"4",
  }
];

const OtherMembersLiveWithTheFamily = [
  {
    componentType: AppTextField,
    label: "relationshipBetweenCase",
    vModel: 'relationshipBetweenCase',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "broSisRelationshipCase",
    vModel: 'broSisRelationshipCase',
    cols:"12",
    md:"4",
  }
];


const EvolutionaryHistoryOfTheCondition = [
  {
    componentType: AppTextField,
    label: "IllnessesThatOccurredDuringChildhood",
    vModel: 'IllnessesThatOccurredDuringChildhood',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "TheAccidentsThatHappenedToTheCase",
    vModel: 'TheAccidentsThatHappenedToTheCase',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "neurologicalSymptoms",
    vModel: 'neurologicalSymptoms',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "chronicDiseasesTheFamily",
    vModel: 'chronicDiseasesTheFamily',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "mentalIllnessesFamily",
    vModel: 'mentalIllnessesFamily',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "mentalIllnessesFamily2",
    vModel: 'mentalIllnessesFamily2',
    cols:"12",
    md:"4",
  },
];

const psychologicalScales = [
  {
    componentType: AppTextField,
    label: "scale",
    vModel: 'scale1',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "scale",
    vModel: 'scale2',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "scale",
    vModel: 'scale3',
    cols:"12",
    md:"4",
  },
];

const communicationSkills = [
  {
    componentType: AppTextField,
    label: "Missionary",
    vModel: 'Missionary',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "receptivity",
    vModel: 'receptivity',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "pronunciationDefects",
    vModel: 'pronunciationDefects',
    cols:"12",
    md:"4",
  },
];


const generalMentalAbility = [
  {
    componentType: AppTextField,
    label: "TheAbilityToPerceive",
    vModel: 'TheAbilityToPerceive',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "TheAbilityToPayAttention",
    vModel: 'TheAbilityToPayAttention',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "AbsorptionCapacity",
    vModel: 'AbsorptionCapacity',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "TheAbilityToFocus",
    vModel: 'TheAbilityToFocus',
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "theAbilityToFantasize",
    vModel: "theAbilityToFantasize",
    cols:"12",
    md:"4",
  },
  // {
  //   componentType: AppTextField,
  //   label: "القدرة على الاستيعاب>>>>>>>>>>>",
  //   vModel: 'AbsorptionCapacity',
  //   cols:"12",
  //   md:"4",
  // },
];

const generalMoodOfTheSituation = [
  {
    componentType: AppTextField,
    label: "generalMoodOfTheSituation",
    vModel: "generalMoodOfTheSituation",
    cols:"12",
    md:"4",
  },
];

const reinforcers = [
  {
    componentType: AppTextField,
    label: "nutritionalEnhancers",
    vModel: "nutritionalEnhancers",
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "physicalReinforcers",
    vModel: "physicalReinforcers",
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "symbolicReinforcers",
    vModel: "symbolicReinforcers",
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "activityEnhancers",
    vModel: "activityEnhancers",
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextField,
    label: "socialReinforcers",
    vModel: "socialReinforcers",
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextarea,
    label: "caseStudySummaryAndReinforcers",
    vModel: "caseStudySummaryAndReinforcers",
    cols:"12",
    md:"12",
  },
];



const PsychologicalExamination = [
  {
    componentType: AppSelect,
    label: 'clothe',
    vModel: 'clothe',
    items: clotheItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: "physicalHealth",
    vModel: "physicalHealth",
    items: physicalHealthItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: "bodyPosition",
    vModel: "bodyPosition",
    items: bodyPositionItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'faceFeatures',
    vModel: 'faceFeatures',
    items: faceFeaturesItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'eyeContact',
    vModel: 'eyeContact',
    items: eyeContactItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'movmentBehaviour',
    vModel: 'movmentBehaviour',
    items: movmentBehaviourItems,
    cols:"12",
    md:"4",
  },
];



const mentalCapacities = [
  {
    componentType: AppSelect,
    label: 'awareness',
    vModel: 'awareness',
    items: awarenessItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'attention',
    vModel: 'attention',
    items: attentionItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'focus',
    vModel: 'focus',
    items: focusItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'memory',
    vModel: 'memory',
    items: memoryItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'perception',
    vModel: 'perception',
    items: perceptionItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'mood',
    vModel: 'mood',
    items: moodItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'conscience',
    vModel: 'conscience',
    items: conscienceItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextarea,
    label: 'recommendations',
    vModel: 'recommendations',
    cols:"12",
    md:"12",
  },


];

const miscellaneousQuestions = [
  {
    componentType: AppSelect,
    label: 'retardationType',
    vModel: 'retardationType',
    items: retardationTypeItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'disability',
    vModel: 'disability',
    items: disabilityItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'InitiativeAndIndependence',
    vModel: 'InitiativeAndIndependence',
    items: InitiativeAndIndependenceItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'DialogueAndSpeech',
    vModel: 'DialogueAndSpeech',
    items: DialogueAndSpeechItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'otherNotes',
    vModel: 'otherNotes',
    items: otherNotesItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'relationshipWithTheResidentsAndTheDegree',
    vModel: 'relationshipWithTheResidentsAndTheDegree',
    items: relationshipWithTheResidentsAndTheDegreeItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'behaviorConsistency',
    vModel: 'behaviorConsistency',
    items: behaviorConsistencyItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'wayOfEating',
    vModel: 'wayOfEating',
    items: wayOfEatingItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'outputFunctions',
    vModel: 'outputFunctions',
    items: outputFunctionsItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'externalBehaviour',
    vModel: 'externalBehaviour',
    items: externalBehaviourItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'manifestationsOfExternalBehavior',
    vModel: 'manifestationsOfExternalBehavior',
    items: manifestationsOfExternalBehaviorItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'ResidentActivity',
    vModel: 'ResidentActivity',
    items: ResidentActivityItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'associatedFamily',
    vModel: 'associatedFamily',
    items: associatedFamilyItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'completelyFamily',
    vModel: 'completelyFamily',
    items: completelyFamilyItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppSelect,
    label: 'OtherAppearances',
    vModel: 'OtherAppearances',
    items: OtherAppearancesItems,
    cols:"12",
    md:"4",
  },
  {
    componentType: AppTextarea,
    label: 'reinforcers',
    vModel: 'reinforcers',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'exterior',
    vModel: 'exterior',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'CognitiveAndMentalAbilities',
    vModel: 'CognitiveAndMentalAbilities',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'SocialInteractionAndActivities',
    vModel: 'SocialInteractionAndActivities',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'eyeContact',
    vModel: 'eyeContact',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'auditoryCommunication',
    vModel: 'auditoryCommunication',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'verbalCommunication',
    vModel: 'verbalCommunication',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'exterior',
    vModel: 'exterior',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'movmentAbilities',
    vModel: 'movmentAbilities',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'TheIndependentSide',
    vModel: 'TheIndependentSide',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'mood',
    vModel: 'mood',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'behavioralAspectStereotypical',
    vModel: 'behavioralAspectStereotypical',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'behavioralAspectUndesirable',
    vModel: 'behavioralAspectUndesirable',
    cols:"12",
    md:"12",
  },
  {
    componentType: AppTextarea,
    label: 'recommendations',
    vModel: 'recommendations',
    cols:"12",
    md:"12",
  },
];



const fieldsObj ={
  initFields: initFields,
  healthStatus:healthStatus,
  family:family,
  mother: mother,
  brothersAndSisters: brothersAndSisters,
  OtherMembersLiveWithTheFamily:OtherMembersLiveWithTheFamily,
  EvolutionaryHistoryOfTheCondition:EvolutionaryHistoryOfTheCondition,
  psychologicalScales:psychologicalScales,
  communicationSkills:communicationSkills,
  generalMentalAbility:generalMentalAbility,
  generalMoodOfTheSituation:generalMoodOfTheSituation,
  reinforcers:reinforcers,
  PsychologicalExamination:PsychologicalExamination,
  mentalCapacities:mentalCapacities,
  miscellaneousQuestions:miscellaneousQuestions,
}
  



// gernrate object for (Ref) fun to save data in it
const formData = {};

const mergedObject = fieldsObj;

for (const key in mergedObject) {
  formData[key] = {};

  if (mergedObject[key] && Array.isArray(mergedObject[key])) {
      mergedObject[key].forEach(item => {
          const vModelKey = item.vModel;
          formData[key][vModelKey] = item.hasOwnProperty('items') ? [] : '';
      });
  }
}


export { fieldsObj, formData };


