import AppTextField from '@core/components/app-form-elements/AppTextField.vue';
import AppTextarea from '@core/components/app-form-elements/AppTextarea.vue';

const generalFields = [
    {
        componentType: AppTextarea,
        label: 'operational_plan.center_vision',
        vModel: 'center_vision',
        cols:"12",
        md:"12",
    },
    {
        componentType: AppTextarea,
        label: 'operational_plan.center_message',
        vModel: 'center_message',
        cols:"12",
        md:"12",
    }
];

const buildingFields = [
    {
        componentType: AppTextField,
        label: 'operational_plan.condition',
        vModel: 'condition',
        cols:"12",
        md:"4",
    },
    {
        componentType: AppTextField,
        label: 'operational_plan.type',
        vModel: 'type',
        cols:"12",
        md:"4",
    },
    {
        componentType: AppTextField,
        label: 'operational_plan.building_space',
        vModel: 'building_space',
        cols:"12",
        md:"4",
    },
    {
        componentType: AppTextField,
        label: 'operational_plan.courtyard_space',
        vModel: 'courtyard_space',
        cols:"12",
        md:"4",
    },
    {
        componentType: AppTextField,
        label: 'operational_plan.total_number_of_rooms',
        vModel: 'total_number_of_rooms',
        type: 'number',
        cols:"12",
        md:"4",
    },
    {
        componentType: AppTextField,
        label: 'operational_plan.number_of_classes',
        vModel: 'number_of_classes',
        type: 'number',
        cols:"12",
        md:"4",
    },
    {
        componentType: AppTextField,
        label: 'operational_plan.number_of_statistics_rooms',
        vModel: 'number_of_statistics_rooms',
        type: 'number',
        cols:"12",
        md:"4",
    },
    {
        componentType: AppTextField,
        label: 'operational_plan.number_of_management_rooms',
        vModel: 'number_of_management_rooms',
        type: 'number',
        cols:"12",
        md:"4",
    },
    {
        componentType: AppTextField,
        label: 'operational_plan.number_of_warehouses',
        vModel: 'number_of_warehouses',
        type: 'number',
        cols:"12",
        md:"4",
    },
    {
        componentType: AppTextField,
        label: 'operational_plan.number_of_bathrooms',
        vModel: 'number_of_bathrooms',
        type: 'number',
        cols:"12",
        md:"4",
    },
];

const swotAnalysisFields = [
    {
        componentType: AppTextarea,
        label: 'operational_plan.strength_points',
        vModel: 'strength_points',
        cols:"12",
        md:"12",
    },
    {
        componentType: AppTextarea,
        label: 'operational_plan.opportunities',
        vModel: 'opportunities',
        cols:"12",
        md:"12",
    },
    {
        componentType: AppTextarea,
        label: 'operational_plan.weak_points',
        vModel: 'weak_points',
        cols:"12",
        md:"12",
    },
    {
        componentType: AppTextarea,
        label: 'operational_plan.threats',
        vModel: 'threats',
        cols:"12",
        md:"12",
    }
];

const fieldsObj = {
    general_info: generalFields,
    building: buildingFields,
    diagnosing_reality_swot_analysis: swotAnalysisFields
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

