import i18n from '@/plugins/i18n/index.js';



const yesNoItems = () => { return [
    {
      title: i18n.global.t('Yes'),
      value: 'yes',
    },
    {
        title: i18n.global.t('No'),
        value: 'no',
      },
  ]}



const chronicDiseasesMotherItems = () => { return [
    {
        title: i18n.global.t('diabetes'),
        value:'diabetes',
    },
    {
        title: i18n.global.t('pressureDisease'),
        value:'pressureDisease',
    },
    {
        title: i18n.global.t('heartDisease'),
        value:'heartDisease',
    },
    {
        title: i18n.global.t('other'),
        value:'other',
    },
    ]}


const birthTypeItems = () => { return [
    {
        title: i18n.global.t("natural"),
        value:'natural',
    },
    {
        title: i18n.global.t("cesarean"),
        value:'cesarean',
    },
]}

const typeLactationItems = () => { return [
    {
        title: i18n.global.t("natural"),
        value:'natural',
    },
    {
        title: i18n.global.t("industrial"),
        value:'industrial',
    },
    {
        title: i18n.global.t("naturalAndIndustrial"),
        value:'naturalAndIndustrial',
    },
    ]}

const linguisticFirstMonthItems = () => { return [
    {
        title: i18n.global.t("natural"),
        value:'natural',
    },
    {
        title: i18n.global.t("industrial"),
        value:'industrial',
    },

    ]}
    
const scientificLevel = () => { return [
    {
        title: i18n.global.t("highQualified"),
        value:'highQualified',
    },
    {
        title: i18n.global.t("highSchool"),
        value:'highSchool',
    },
    {
        title: i18n.global.t("qualifiedAverage"),
        value:'qualifiedAverage',
    },
    {
        title: i18n.global.t("illiterate"),
        value:'illiterate',
    },
]}

const kindOfSpeechItems = () => { return [
    {
        title: i18n.global.t('understand'),
        value:'understand',
    },
    {
        title: i18n.global.t('notUnderstand'),
        value:'notUnderstand',
    },
    {
        title: i18n.global.t('clear'),
        value:'clear',
    },
    {
        title: i18n.global.t('notClear'),
        value:'notClear',
    },
    {
        title: i18n.global.t('disconnected'),
        value:'disconnected',
    },
    {
        title: i18n.global.t('advanced'),
        value:'advanced',
    },
]}

const caseHasProblemsItems = () => { return [
    {
        title:i18n.global.t("sleepDisorders"),
        value:'sleepDisorders',
    },
    {
        title:i18n.global.t("excretionDisorders"),
        value:'excretionDisorders',
    },
    {
        title:i18n.global.t("bedwetting"),
        value:'bedwetting',
    },
    {
        title:i18n.global.t("feedingDisorders"),
        value:'feedingDisorders',
    },
    {
        title:i18n.global.t("fingerSucking"),
        value:'fingerSucking',
    },
    {
        title:i18n.global.t("fingerBiting"),
        value:'fingerBiting',
    },
    {
        title:i18n.global.t("moodswings"),
        value:'moodswings',
    },
    {
        title:i18n.global.t("nervousness"),
        value:'nervousness',
    },
    {
        title:i18n.global.t("aggression"),
        value:'aggression',
    },    
    {
        title:i18n.global.t("fearOfOthers"),
        value:'fearOfOthers',
    },    
    {
        title:i18n.global.t("stubbornness"),
        value:'stubbornness',
    },
    {
        title:i18n.global.t("shy"),
        value:'shy',
    },    
    {
        title:i18n.global.t("selfHarm"),
        value:'selfHarm',
    },    
    {
        title:i18n.global.t("temperTantrums"),
        value:'temperTantrums',
    },    
    {
        title:i18n.global.t("convulsiveSeizures"),
        value:'convulsiveSeizures',
    },
    {
        title:i18n.global.t("hyperactivity"),
        value:'hyperactivity',
    },      
    {
        title:i18n.global.t("lethargyAndLaziness"),
        value:'lethargyAndLaziness',
    },
    {
        title:i18n.global.t("introversion"),
        value:'introversion',
    },
    {
        title:i18n.global.t("jealousyOfTheBrothers"),
        value:'jealousyOfTheBrothers',
    },  
    {
        title:i18n.global.t("escapeFromTheHouse"),
        value:'escapeFromTheHouse',
    },
    {
        title:i18n.global.t("ruinThings"),
        value:'ruinThings',
    },
]}




const growthHistoryItems = () => { return [
    {
        title:i18n.global.t('aYearAgo'),
        value:'aYearAgo',
    },
    {
        title:i18n.global.t('year'),
        value:'year',
    },
    {
        title:i18n.global.t('afterOneYear'),
        value:'afterOneYear',
    },
]}


const maritalStatusItems = () => { return [
    {
        title:i18n.global.t("married"),
        value:"married",
    },
    {
        title:i18n.global.t("divorced"),
        value:"divorced",
    },
    {
        title:i18n.global.t("widower"),
        value:"widower",
    },
]}


const understandCaseItems = () => { return [
    {

        title: i18n.global.t("awareAndUnderstandingOfTheSituation"),
        value:"awareAndUnderstandingOfTheSituation",
    },
    {

        title: i18n.global.t("insufficientInformationAboutTheCondition"),
        value:"insufficientInformationAboutTheCondition",
    },
    {

        title: i18n.global.t("uninterestedAndUnawareOfTheSituation"),
        value:"uninterestedAndUnawareOfTheSituation",
    },
]}

const understandCaseFatherItems = () => { return [
    {

        title: i18n.global.t("awareAndUnderstandingOfTheSituationFather"),
        value:"awareAndUnderstandingOfTheSituationFather",
    },
    {

        title: i18n.global.t("insufficientInformationAboutTheConditionFather"),
        value:"insufficientInformationAboutTheConditionFather",
    },
    {

        title: i18n.global.t("uninterestedAndUnawareOfTheSituationFather"),
        value:"uninterestedAndUnawareOfTheSituationFather",
    },
]}


const understandCaseThemItems = () => { return [
    {

        title: i18n.global.t("awareAndUnderstandingOfTheSituationThem"),
        value:"awareAndUnderstandingOfTheSituationThem",
    },
    {

        title: i18n.global.t("insufficientInformationAboutTheConditionThem"),
        value:"insufficientInformationAboutTheConditionThem",
    },
    {

        title: i18n.global.t("uninterestedAndUnawareOfTheSituationThem"),
        value:"uninterestedAndUnawareOfTheSituationThem",
    },
]}




const relationshipFatherMotherItems = () => { return [
    {
        title: i18n.global.t("goodRelation"),
        value:"goodRelation",
    },
    {
        title: i18n.global.t("someIrregularities"),
        value:"someIrregularities",
    },
    {
        title: i18n.global.t("relationshipDisturbances"),
        value:"relationshipDisturbances",
    },
    {
        title: i18n.global.t("separateCouple"),
        value:"separateCouple",
    },
]}

const accommodationTypeItems = () => { return [
    {
        title: i18n.global.t("villa"),
        value:"villa",
    },
    {
        title: i18n.global.t("apartment"),
        value:"apartment",
    },

]}
const accommodationStatusItems = () => { return [
    {
        title: i18n.global.t("own"),
        value:"own",
    },
    {
        title: i18n.global.t("rent"),
        value:"rent",
    },

]}
const familyIncomeSourcesItems = () => { return [
    {
        title: i18n.global.t("job"),
        value:"job",
    },
    {
        title: i18n.global.t("realEstate"),
        value:"realEstate",
    },
    {
        title: i18n.global.t("freelace"),
        value:"freelace",
    },

]}
const annualIncomeLevelItems = () => { return [
    {
        title: i18n.global.t("excellent"),
        value:"excellent",
    },
    {
        title: i18n.global.t("average"),
        value:"average",
    },
    {
        title: i18n.global.t("good"),
        value:"good",
    },
    {
        title: i18n.global.t("low"),
        value:"low",
    },

]}


const skillsItems = () => { return [
    {
        title: i18n.global.t("excellent"),
        value:"excellent",
    },
    {
        title: i18n.global.t("average"),
        value:"average",
    },
    {
        title: i18n.global.t("good"),
        value:"good",
    },
    {
        title: i18n.global.t("low"),
        value:"low",
    },

]}

const discoversCaseItems = () => { return [
    {
        title: i18n.global.t("hospital"),
        value:"hospital",
    },
    {
        title: i18n.global.t("mother"),
        value:"mother",
    },
    {
        title: i18n.global.t("center"),
        value:"center",
    },
    {
        title: i18n.global.t("others"),
        value:"others",
    },

]}

const ageDiseaseItems = () => { return [
    {
        title:i18n.global.t("beforeTheFirstYear"),
        value: "beforeTheFirstYear"
    },
    {
        title:i18n.global.t("afterTheFirstYear"),
        value: "afterTheFirstYear"
    }
]}

const socialAspectItems = () => { return [
    {
        title: i18n.global.t("effectivelyConnected"),
        value: "effectivelyConnected"
    },
    {
        title: i18n.global.t("sometimesConnected"),
        value: "sometimesConnected"
    },
    {
        title: i18n.global.t("notConnected"),
        value: "notConnected"
    }
]}



const movementSideItems = () => { return [
    {
        title: i18n.global.t("normalMovementPerformance"),
        value: "normalMovementPerformance"
    },
    {
        title: i18n.global.t("lowMovementPerformance"),
        value: "lowMovementPerformance"
    },
    {
        title: i18n.global.t("troubledAndUnbalancedMovementPerformance"),
        value: "troubledAndUnbalancedMovementPerformance"
    }
]}



const personalCareItems = () => { return [
    {
        title: i18n.global.t("takesCareOfHimselfAlways"),
        value: "takesCareOfHimselfAlways"
    },
    {
        title: i18n.global.t("takesCareOfHimselfSometimes"),
        value: "takesCareOfHimselfSometimes"
    },
    {
        title: i18n.global.t("othersHelpHimAnyway"),
        value: "othersHelpHimAnyway"
    }
]}


const mentalCognitiveItems = () => { return [
    {
        title:i18n.global.t("easilyUnderstandsAndActsConsciously"),
        value: "easilyUnderstandsAndActsConsciously"
    },
    {
        title:i18n.global.t("understandsSlowActingSometimes"),
        value: "understandsSlowActingSometimes"
    },
    {
        title:i18n.global.t("notUnderstandOrActConsciously"),
        value: "notUnderstandOrActConsciously"
    }
]}


const durationPregnancyItems = () => { return [
    {
        title:i18n.global.t("normal9Months"),
        value: "normal9Months"
    },
    {
        title:i18n.global.t("before9Months"),
        value: "before9Months"
    }
]}


const motherDuringPregnancyItems = () => { return [
    {
        title: i18n.global.t("normal"),
        value: "normal"
    },
    {
        title: i18n.global.t("pregnancyProblems"),
        value: "pregnancyProblems"
    },
    {
        title: i18n.global.t("otherDiseases"),
        value: "otherDiseases"
    }
]}




const giveBirthItems = () => { return [
    {
        title: i18n.global.t("home"),
        value: "home"
    },
    {
        title: i18n.global.t("hospital"),
        value: "hospital"
    },
    {
        title: i18n.global.t("other"),
        value: "other"
    }
]}



const babyWeightBirthItems = () => { return [
    {
        title: i18n.global.t("baby2_3kilos"),
        value: "baby2_3kilos"
    },
    {
        title: i18n.global.t("baby4_3kilos"),
        value: "baby4_3kilos"
    },
    {
        title: i18n.global.t("baby5_4kilos"),
        value: "baby5_4kilos"
    },
    {
        title: i18n.global.t("others"),
        value: "others"
    }
]}



const organicDiseasesItem = () => { return [
    {
        title: i18n.global.t("chronicDiseases"),
        value: "chronicDiseases"
    },
    {
        title: i18n.global.t("nonChronicDiseases"),
        value: "nonChronicDiseases"
    },
    {
        title: i18n.global.t("good"),
        value: "good"
    },
    {
        title: i18n.global.t("low"),
        value: "low"
    }
]}





const emotionalGrowthItem = () => { return [
    {
        title: i18n.global.t("excessive"),
        value: "excessive"
    },
    {
        title: i18n.global.t("escape"),
        value: "escape"
    },
    {
        title: i18n.global.t("frustration"),
        value: "frustration"
    },
    {
        title: i18n.global.t("fears"),
        value: "fears"
    },
    {
        title: i18n.global.t("anxiety"),
        value: "anxiety"
    },
    {
        title: i18n.global.t("tension"),
        value: "tension"
    },
    {
        title: i18n.global.t("sabotage"),
        value: "sabotage"
    },
    {
        title: i18n.global.t("stubborn"),
        value: "stubborn"
    },
    {
        title: i18n.global.t("aggressive"),
        value: "aggressive"
    },
    {
        title: i18n.global.t("lethargy"),
        value: "lethargy"
    },
]}


const fatherCaresItem = () => { return [
    {
        title: i18n.global.t("heTakesCareOfHerselfAtHome"),
        value: "heTakesCareOfHerselfAtHome"
    },
    {
        title: i18n.global.t("fathershareItWithAMaidOrOthers"),
        value: "shareItWithAMaidOrOthers"
    },
    {
        title: i18n.global.t("fathertakeResponsibilityOnOthers"),
        value: "fathertakeResponsibilityOnOthers"
    }
]}


const motherCaresItem = () => { return [
    {
        title: i18n.global.t("sheTakesCareOfHerselfAtHome"),
        value: "sheTakesCareOfHerselfAtHome"
    },
    {
        title: i18n.global.t("shareItWithAMaidOrOthers"),
        value: "shareItWithAMaidOrOthers"
    },
    {
        title: i18n.global.t("takeResponsibilityOnOthers"),
        value: "takeResponsibilityOnOthers"
    }
]}

const clotheItems= () => {
    return [
        {
            title: i18n.global.t("tousledSleazy"),
            value: "tousledSleazy"
        },
        {
            title: i18n.global.t("trim"),
            value: "trim"
        },
        {
            title: i18n.global.t("clean"),
            value: "clean"
        },
        {
            title: i18n.global.t("unsuitable"),
            value: "unsuitable"
        },
        {
            title: i18n.global.t("untidy"),
            value: "untidy"
        },
        {
            title: i18n.global.t("veryMeticulousAboutDetails"),
            value: "veryMeticulousAboutDetails"
        },
        {
            title: i18n.global.t("dirty"),
            value: "dirty"
        },
        {
            title: i18n.global.t("inappropriateForAge"),
            value: "inappropriateForAge"
        },
        {
            title: i18n.global.t("seductive"),
            value: "seductive"
        }
    ]
}


const familyStatus= () => {
    return [
        {
            title: i18n.global.t("stable"),
            value: "stable"
        },
        {
            title: i18n.global.t("divorce"),
            value: "divorce"
        },
        {
            title: i18n.global.t("death_of_parent"),
            value: "death_of_parent"
        },
        {
            title: i18n.global.t("death_both_parent"),
            value: "death_both_parent"
        },
    ]
}


const economicFamily= () => {
    return [
        {
            title: i18n.global.t("good"),
            value: "good"
        },
        {
            title: i18n.global.t("medium"),
            value: "medium"
        },
        {
            title: i18n.global.t("weak"),
            value: "weak"
        },
    ]
}


const physicalHealthItems= () => {
    return [
        {
            title: i18n.global.t("good"),
            value: "good"
        },
        {
            title: i18n.global.t("medium"),
            value: "medium"
        },
        {
            title: i18n.global.t("weak"),
            value: "weak"
        },
        {
            title: i18n.global.t("birthDefect"),
            value: "birthDefect"
        },
        {
            title: i18n.global.t("physicalDisabilities"),
            value: "physicalDisabilities"
        }
    ]
}



const bodyPositionItems= () => {
    return [
        {
            title: i18n.global.t("normal"),
            value: "normal"
        },
        {
            title: i18n.global.t("erectTaut"),
            value: "erectTaut"
        },
        {
            title: i18n.global.t("slumpedOnTheSeat"),
            value: "slumpedOnTheSeat"
        },
        {
            title: i18n.global.t("backArched"),
            value: "backArched"
        },
        {
            title: i18n.global.t("strangeSituation"),
            value: "strangeSituation"
        },
        {
            title: i18n.global.t("other"),
            value: "other"
        }
    ]
}




const faceFeaturesItems= () => {
    return [
        {
            title: i18n.global.t("responsive"),
            value: "responsive"
        },
        {
            title: i18n.global.t("taut"),
            value: "taut"
        },
        {
            title: i18n.global.t("tense"),
            value: "tense"
        },
        {
            title: i18n.global.t("anxiety"),
            value: "anxiety"
        },
        {
            title: i18n.global.t("sad"),
            value: "sad"
        },
        {
            title: i18n.global.t("depressed"),
            value: "depressed"
        },
        {
            title: i18n.global.t("angry"),
            value: "angry"
        },
        {
            title: i18n.global.t("other"),
            value: "other"
        }
    ]
}


const eyeContactItems= () => {
    return [
        {
            title: i18n.global.t("normal"),
            value: "normal"
        },
        {
            title: i18n.global.t("peek"),
            value: "peek"
        },
        {
            title: i18n.global.t("avoid"),
            value: "avoid"
        },
        {
            title: i18n.global.t("staring"),
            value: "staring"
        },
        {
            title: i18n.global.t("sad"),
            value: "sad"
        }
    ]
}




const movmentBehaviourItems= () => {
    return [
        {
            title: i18n.global.t("fastMoving"),
            value: "fastMoving"
        },
        {
            title: i18n.global.t("recursive"),
            value: "recursive"
        },
        {
            title: i18n.global.t("troubled"),
            value: "troubled"
        },
        {
            title: i18n.global.t("slowMoving"),
            value: "slowMoving"
        },
        {
            title: i18n.global.t("fidgety"),
            value: "fidgety"
        },
        {
            title: i18n.global.t("abnormal"),
            value: "abnormal"
        },
        {
            title: i18n.global.t("calm"),
            value: "calm"
        },
        {
            title: i18n.global.t("shiverTremor"),
            value: "shiverTremor"
        },
        {
            title: i18n.global.t("lazamat"),
            value: "lazamat"
        },
        {
            title: i18n.global.t("increasedMovement"),
            value: "increasedMovement"
        },
        {
            title: i18n.global.t("impulsive"),
            value: "impulsive"
        },
        {
            title: i18n.global.t("negative"),
            value: "negative"
        },
        {
            title: i18n.global.t("blindObedience"),
            value: "blindObedience"
        },
        {
            title: i18n.global.t("compulsiveActions"),
            value: "compulsiveActions"
        },
        {
            title: i18n.global.t("nothing"),
            value: "nothing"
        }
    ]
}


const awarenessItems= () => {
    return [
        {
            title: i18n.global.t("conscious"),
            value: "conscious"
        },
        {
            title: i18n.global.t("volatile"),
            value: "volatile"
        },
        {
            title: i18n.global.t("hypervigilance"),
            value: "hypervigilance"
        },
        {
            title: i18n.global.t("excited"),
            value: "excited"
        },
        {
            title: i18n.global.t("drowsiness"),
            value: "drowsiness"
        },
        {
            title: i18n.global.t("confused"),
            value: "confused"
        },
        {
            title: i18n.global.t("unaware"),
            value: "unaware"
        },
        {
            title: i18n.global.t("denial"),
            value: "denial"
        },
        {
            title: i18n.global.t("clairvoyant"),
            value: "clairvoyant"
        }
    ]
}



const attentionItems= () => {
    return [
        {
            title: i18n.global.t("normal"),
            value: "normal"
        },
        {
            title: i18n.global.t("unaware"),
            value: "unaware"
        },
        {
            title: i18n.global.t("unheeding"),
            value: "unheeding"
        },
        {
            title: i18n.global.t("dispersed"),
            value: "dispersed"
        },
        {
            title: i18n.global.t("alert"),
            value: "alert"
        }
    ]
}


const focusItems= () => {
    return [
        {
            title: i18n.global.t("normal"),
            value: "normal"
        },
        {
            title: i18n.global.t("dispersed"),
            value: "dispersed"
        },
        {
            title: i18n.global.t("nonFocused"),
            value: "nonFocused"
        },
        {
            title: i18n.global.t("anxiety"),
            value: "anxiety"
        }
    ]
}




const perceptionItems= () => {
    return [
        {
            title: i18n.global.t("khadat"),
            value: "khadat"
        },
        {
            title: i18n.global.t("visual"),
            value: "visual"
        },
        {
            title: i18n.global.t("auditory"),
            value: "auditory"
        },
        {
            title: i18n.global.t("other"),
            value: "other"
        }
    ]
}


const memoryItems= () => {
    return [
        {
            title: i18n.global.t("normal"),
            value: "normal"
        },
        {
            title: i18n.global.t("intact"),
            value: "intact"
        },
        {
            title: i18n.global.t("memoryImpairment"),
            value: "memoryImpairment"
        },
        {
            title: i18n.global.t("severeDisorder"),
            value: "severeDisorder"
        },
        {
            title: i18n.global.t("fabrication"),
            value: "fabrication"
        },
        {
            title: i18n.global.t("nearbyEvents"),
            value: "nearbyEvents"
        },
        {
            title: i18n.global.t("distantEvents"),
            value: "distantEvents"
        },
        {
            title: i18n.global.t("nothing"),
            value: "nothing"
        }
    ]
}


const  moodItems= () => {
    return [
        {
            title: i18n.global.t("depressed"),
            value: "depressed"
        },
        {
            title: i18n.global.t("excitable"),
            value: "excitable"
        },
        {
            title: i18n.global.t("anxiety"),
            value: "anxiety"
        },
        {
            title: i18n.global.t("sad"),
            value: "sad"
        },
        {
            title: i18n.global.t("angry"),
            value: "angry"
        },
        {
            title: i18n.global.t("hostile"),
            value: "hostile"
        },
        {
            title: i18n.global.t("feelingElated"),
            value: "feelingElated"
        },
        {
            title: i18n.global.t("normal"),
            value: "normal"
        }
    ]
}


const conscienceItems= () => {
    return [
        {
            title: i18n.global.t("appropriateForIdeas"),
            value: "appropriateForIdeas"
        },
        {
            title: i18n.global.t("inappropriateIdeas"),
            value: "inappropriateIdeas"
        },
        {
            title: i18n.global.t("flat"),
            value: "flat"
        },
        {
            title: i18n.global.t("melancholy"),
            value: "melancholy"
        },
        {
            title: i18n.global.t("shallow"),
            value: "shallow"
        },
        {
            title: i18n.global.t("fluctuation"),
            value: "fluctuation"
        },
        {
            title: i18n.global.t("contrast"),
            value: "contrast"
        },
        {
            title: i18n.global.t("panic"),
            value: "panic"
        },
        {
            title: i18n.global.t("elationAndJoy"),
            value: "elationAndJoy"
        },
        {
            title: i18n.global.t("emotionalDecline"),
            value: "emotionalDecline"
        }
    ]
}


const retardationTypeItems= () => {
    return [
        {
            title: i18n.global.t("simpleLag"),
            value: "simpleLag"
        },
        {
            title: i18n.global.t("averageLag"),
            value: "averageLag"
        },
        {
            title: i18n.global.t("strongLag"),
            value: "strongLag"
        },
        {
            title: i18n.global.t("interstitialWeakness"),
            value: "interstitialWeakness"
        },
        {
            title: i18n.global.t("thereIsNoObstruction"),
            value: "thereIsNoObstruction"
        }
    ]
}



const disabilityItems= () => {
    return [
        {
            title: i18n.global.t("mental"),
            value: "mental"
        },
        {
            title: i18n.global.t("physical"),
            value: "physical"
        },
        {
            title: i18n.global.t("sensory"),
            value: "sensory"
        },
        {
            title: i18n.global.t("visual"),
            value: "visual"
        },
        {
            title: i18n.global.t("multipleDisabilitiesWithIntellectualImpairment"),
            value: "multipleDisabilitiesWithIntellectualImpairment"
        },
        {
            title: i18n.global.t("noDisability"),
            value: "noDisability"
        }
    ]
}

const InitiativeAndIndependenceItems= () => {
    return [
        {
            title: i18n.global.t("completelyIncapable"),
            value: "completelyIncapable"
        },
        {
            title: i18n.global.t("unableToAssist"),
            value: "unableToAssist"
        },
        {
            title: i18n.global.t("needPrimaryCare"),
            value: "needPrimaryCare"
        },
        {
            title: i18n.global.t("capableWithAssistance"),
            value: "capableWithAssistance"
        }
    ]
}

const DialogueAndSpeechItems= () => {
    return [
        {
            title: i18n.global.t("Incomprehensible"),
            value: "Incomprehensible"
        },
        {
            title: i18n.global.t("DoesNotEngageInConversation"),
            value: "DoesNotEngageInConversation"
        },
        {
            title: i18n.global.t("UnnaturalAndIncomprehensible"),
            value: "UnnaturalAndIncomprehensible"
        },
        {
            title: i18n.global.t("RespondsToDirectQuestionsOnly"),
            value: "RespondsToDirectQuestionsOnly"
        },
        {
            title: i18n.global.t("OneTopic"),
            value: "OneTopic"
        },
        {
            title: i18n.global.t("SpeaksNaturally"),
            value: "SpeaksNaturally"
        }
    ]
}


const otherNotesItems= () => {
    return [
        {
            title: i18n.global.t("SpeechDisorders"),
            value: "SpeechDisorders"
        },
        {
            title: i18n.global.t("LanguageDelay"),
            value: "LanguageDelay"
        },
        {
            title: i18n.global.t("ArticulationDeficiencies"),
            value: "ArticulationDeficiencies"
        },
        {
            title: i18n.global.t("SpeechImpediment"),
            value: "SpeechImpediment"
        },
        {
            title: i18n.global.t("Stuttering"),
            value: "Stuttering"
        },
        {
            title: i18n.global.t("LanguageImpairment"),
            value: "LanguageImpairment"
        },
        {
            title: i18n.global.t("SpeechAndLanguageDisorders"),
            value: "SpeechAndLanguageDisorders"
        }
    ]
}



const relationshipWithTheResidentsAndTheDegreeItems= () => {
    return [
        {
            title: i18n.global.t("AggressiveBehavior"),
            value: "AggressiveBehavior"
        },
        {
            title: i18n.global.t("AvoidsMostResidents"),
            value: "AvoidsMostResidents"
        },
        {
            title: i18n.global.t("GoodRelationships"),
            value: "GoodRelationships"
        },
        {
            title: i18n.global.t("FriendlyBehavior"),
            value: "FriendlyBehavior"
        },
        {
            title: i18n.global.t("StrongConnection"),
            value: "StrongConnection"
        },
        {
            title: i18n.global.t("NoCompleteAttachment"),
            value: "NoCompleteAttachment"
        },
        {
            title: i18n.global.t("LimitedConnectionWithAvoidance"),
            value: "LimitedConnectionWithAvoidance"
        }
    ]
}




const behaviorConsistencyItems= () => {
    return [
        {
            title: i18n.global.t("UnacceptableBehavior"),
            value: "UnacceptableBehavior"
        },
        {
            title: i18n.global.t("AbruptBehaviorChanges"),
            value: "AbruptBehaviorChanges"
        },
        {
            title: i18n.global.t("TemporaryBehaviorChanges"),
            value: "TemporaryBehaviorChanges"
        },
        {
            title: i18n.global.t("DisturbingHyperactivity"),
            value: "DisturbingHyperactivity"
        },
        {
            title: i18n.global.t("ADHD"),
            value: "ADHD"
        },
        {
            title: i18n.global.t("LackOfFocus"),
            value: "LackOfFocus"
        },
        {
            title: i18n.global.t("SelfHarm"),
            value: "SelfHarm"
        },
        {
            title: i18n.global.t("ViolentBehavior"),
            value: "ViolentBehavior"
        },
        {
            title: i18n.global.t("SelfArousal"),
            value: "SelfArousal"
        },
        {
            title: i18n.global.t("CausesArousalInOthers"),
            value: "CausesArousalInOthers"
        },
        {
            title: i18n.global.t("AcceptableBehavior"),
            value: "AcceptableBehavior"
        }
    ]
}


const wayOfEatingItems= () => {
    return [
        {
            title: i18n.global.t("SelfReliant"),
            value: "SelfReliant"
        },
        {
            title: i18n.global.t("DependentOnOthers"),
            value: "DependentOnOthers"
        },
        {
            title: i18n.global.t("LiquidFoodDifficultySwallowing"),
            value: "LiquidFoodDifficultySwallowing"
        },
        {
            title: i18n.global.t("NaturalFood"),
            value: "NaturalFood"
        },
        {
            title: i18n.global.t("DistinguishEdibleFood"),
            value: "DistinguishEdibleFood"
        },
        {
            title: i18n.global.t("DrinksIndependently"),
            value: "DrinksIndependently"
        }
    ]
}



const outputFunctionsItems= () => {
    return [
        {
            title: i18n.global.t("DoesNotControlFunctions"),
            value: "DoesNotControlFunctions"
        },
        {
            title: i18n.global.t("RequestsGoToBathroom"),
            value: "RequestsGoToBathroom"
        },
        {
            title: i18n.global.t("UsesBathroomIndependently"),
            value: "UsesBathroomIndependently"
        },
        {
            title: i18n.global.t("CleansHerselfAfterFinishing"),
            value: "CleansHerselfAfterFinishing"
        },
        {
            title: i18n.global.t("SelfReliant"),
            value: "SelfReliant"
        },
        {
            title: i18n.global.t("WashesDriesHandsIndependently"),
            value: "WashesDriesHandsIndependently"
        }
    ]
}


const externalBehaviourItems= () => {
    return [
        {
            title: i18n.global.t("GenerallyAnnoying"),
            value: "GenerallyAnnoying"
        },
        {
            title: i18n.global.t("GenerallyAcceptableWithRisksAndDamages"),
            value: "GenerallyAcceptableWithRisksAndDamages"
        },
        {
            title: i18n.global.t("EntertainsOthersWithMovements"),
            value: "EntertainsOthersWithMovements"
        },
        {
            title: i18n.global.t("NavigatesOuterSpaceOfTheCenter"),
            value: "NavigatesOuterSpaceOfTheCenter"
        },
        {
            title: i18n.global.t("DesiresToAttractAttention"),
            value: "DesiresToAttractAttention"
        }
    ]
}

const manifestationsOfExternalBehaviorItems = () => {
    return [
        {
            title: i18n.global.t("SometimesExhibitsDailyEvents"),
            value: "SometimesExhibitsDailyEvents"
        },
        {
            title: i18n.global.t("OccasionallyAttacksAndDamages"),
            value: "OccasionallyAttacksAndDamages"
        },
        {
            title: i18n.global.t("DisplaysUnexpectedBehaviors"),
            value: "DisplaysUnexpectedBehaviors"
        },
        {
            title: i18n.global.t("UnguidedAndChaoticBehavior"),
            value: "UnguidedAndChaoticBehavior"
        },
        {
            title: i18n.global.t("CausesChaosAndDiscomfort"),
            value: "CausesChaosAndDiscomfort"
        }
    ]
}


const ResidentActivityItems = () => {
    return [
        {
            title: i18n.global.t("RefusesParticipation"),
            value: "RefusesParticipation"
        },
        {
            title: i18n.global.t("ParticipatesChaos"),
            value: "ParticipatesChaos"
        },
        {
            title: i18n.global.t("ConstantReminders"),
            value: "ConstantReminders"
        },
        {
            title: i18n.global.t("InitiatesActivities"),
            value: "InitiatesActivities"
        },
        {
            title: i18n.global.t("CarriesOutTasks"),
            value: "CarriesOutTasks"
        },
        {
            title: i18n.global.t("DemandsManyActivities"),
            value: "DemandsManyActivities"
        },
        {
            title: i18n.global.t("WillingToParticipate"),
            value: "WillingToParticipate"
        },
        {
            title: i18n.global.t("BelievesIncapable"),
            value: "BelievesIncapable"
        }
    ]
}


const associatedFamilyItems = () => {
    return [
        {
            title: i18n.global.t("NoDesireToLeave"),
            value: "NoDesireToLeave"
        },
        {
            title: i18n.global.t("SomeDesireNotGoodEfforts"),
            value: "SomeDesireNotGoodEfforts"
        },
        {
            title: i18n.global.t("SeriousDesireAndEfforts"),
            value: "SeriousDesireAndEfforts"
        },
        {
            title: i18n.global.t("CloselyConnectedWithFamily"),
            value: "CloselyConnectedWithFamily"
        },
        {
            title: i18n.global.t("NoConnectionWithFamily"),
            value: "NoConnectionWithFamily"
        }
    ]
}

const completelyFamilyItems = () => {
    return [
        {
            title: i18n.global.t("DesiresToContemplateIt"),
            value: "DesiresToContemplateIt"
        },
        {
            title: i18n.global.t("CapableOfReintegrationWithThem"),
            value: "CapableOfReintegrationWithThem"
        },
        {
            title: i18n.global.t("UnableToTransitionCompletely"),
            value: "UnableToTransitionCompletely"
        },
        {
            title: i18n.global.t("ExitWithFollowUp"),
            value: "ExitWithFollowUp"
        },
        {
            title: i18n.global.t("MultipleExitsNotAdapted"),
            value: "MultipleExitsNotAdapted"
        }
    ]
}

const OtherAppearancesItems = () => {
    return [
        {
            title: i18n.global.t("FeelingOfPersecution"),
            value: "FeelingOfPersecution"
        },
        {
            title: i18n.global.t("TalkingToOneself"),
            value: "TalkingToOneself"
        },
        {
            title: i18n.global.t("ExcessiveDemands"),
            value: "ExcessiveDemands"
        },
        {
            title: i18n.global.t("SlowMovement"),
            value: "SlowMovement"
        },
        {
            title: i18n.global.t("InsultingOthers"),
            value: "InsultingOthers"
        },
        {
            title: i18n.global.t("ContinuousCriticisms"),
            value: "ContinuousCriticisms"
        },
        {
            title: i18n.global.t("AggressionAndResentment"),
            value: "AggressionAndResentment"
        },
        {
            title: i18n.global.t("FeelingOfNarrowness"),
            value: "FeelingOfNarrowness"
        },
        {
            title: i18n.global.t("DesireToPresentHerself"),
            value: "DesireToPresentHerself"
        },
        {
            title: i18n.global.t("DefinitionOfWeekdays"),
            value: "DefinitionOfWeekdays"
        },
        {
            title: i18n.global.t("DefinitionOfResidence"),
            value: "DefinitionOfResidence"
        },
        {
            title: i18n.global.t("CanTravelAlone"),
            value: "CanTravelAlone"
        },
        {
            title: i18n.global.t("CanPerformSimpleCommands"),
            value: "CanPerformSimpleCommands"
        },
        {
            title: i18n.global.t("GoodResponsiveness"),
            value: "GoodResponsiveness"
        },
        {
            title: i18n.global.t("KnowsNamesOfParents"),
            value: "KnowsNamesOfParents"
        },
        {
            title: i18n.global.t("KnowsPrayerTimes"),
            value: "KnowsPrayerTimes"
        },
        {
            title: i18n.global.t("EngagesInHobbies"),
            value: "EngagesInHobbies"
        }
    ]
}

export { DialogueAndSpeechItems, InitiativeAndIndependenceItems, OtherAppearancesItems, ResidentActivityItems, accommodationStatusItems, accommodationTypeItems, ageDiseaseItems, annualIncomeLevelItems, associatedFamilyItems, attentionItems, awarenessItems, babyWeightBirthItems, behaviorConsistencyItems, birthTypeItems, bodyPositionItems, caseHasProblemsItems, chronicDiseasesMotherItems, clotheItems, completelyFamilyItems, conscienceItems, disabilityItems, discoversCaseItems, durationPregnancyItems, economicFamily, emotionalGrowthItem, externalBehaviourItems, eyeContactItems, faceFeaturesItems, familyIncomeSourcesItems, familyStatus, fatherCaresItem, focusItems, giveBirthItems, growthHistoryItems, kindOfSpeechItems, linguisticFirstMonthItems, manifestationsOfExternalBehaviorItems, maritalStatusItems, memoryItems, mentalCognitiveItems, moodItems, motherCaresItem, motherDuringPregnancyItems, movementSideItems, movmentBehaviourItems, organicDiseasesItem, otherNotesItems, outputFunctionsItems, perceptionItems, personalCareItems, physicalHealthItems, relationshipFatherMotherItems, relationshipWithTheResidentsAndTheDegreeItems, retardationTypeItems, scientificLevel, skillsItems, socialAspectItems, typeLactationItems, understandCaseFatherItems, understandCaseItems, understandCaseThemItems, wayOfEatingItems, yesNoItems };

