<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Assessment;

class GoalRequest extends FormRequest
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
            'title' => 'required|string|max:2048',
            'description' => 'sometimes|string',
            'custom_first_feild' => 'sometimes|nullable|string|max:2048',
            'custom_general_goal' => 'sometimes|nullable|string|max:2048',
            'standard' => 'nullable|string',
            'generalization' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'case_id' => 'sometimes|integer',
            'term_id' => 'sometimes|integer',
            'assessment_id' => 'sometimes|nullable|integer',
            'category' => 'sometimes|nullable|string',
        ];

        return $rules;
    }
}
