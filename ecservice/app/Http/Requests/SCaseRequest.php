<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SCaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'center_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'beneficiary_number' => 'nullable|string|max:255|unique:scases',
            'period' => 'required|integer',
            'disability_type_ids' => 'required',
            'services_provided' => 'required',
            'teacher_id' => 'nullable|exists:users,id',
            'physiotherapist_id' => 'nullable|exists:users,id',
            'occupational_therapy_id' => 'nullable|exists:users,id',
            'psychotherapist_id' => 'nullable|exists:users,id',
            'pronunciation_speech_specialist_id' => 'nullable|exists:users,id',
            'id_or_residence_number' => 'nullable|string|max:255|unique:scases',
            'nationality' => 'required|string',
            'birthdate' => 'required|date',
            'parent_id' => 'required',
            'emergency_contact' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'blood_type' => 'nullable|string|max:4',
            'address_city' => 'nullable|string|max:100',
            'address_area' => 'nullable|string|max:150',
            'address_street' => 'nullable|string|max:150',
            'address_building' => 'nullable|string|max:50',
            'address_number' => 'nullable|string|max:50',
            'address_unit' => 'nullable|string|max:100',
            'address_zipcode' => 'nullable|string|max:50',
            'general_questions' => 'required',
            'case_study' => 'required',
            'psychological_study' => 'required',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:10240',
        ];

        if ($this->routeIs('case.update'))
        {
            $rules['image'] = ['sometimes', 'image','mimes:jpeg,png,jpg', 'max:10240'];
            $rules['beneficiary_number']= "nullable|string|max:255|unique:scases,beneficiary_number,{$this->route('case')['id']}";
            $rules['id_or_residence_number'] ="nullable|string|max:255|unique:scases,id_or_residence_number,{$this->route('case')['id']}";
        }

        return $rules;
    }
}
