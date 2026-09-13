<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Assessment;

class GoalStepsRequest extends FormRequest
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
            'goal_id' => 'required|exists:goals,id',
            'procedural_objectives' => 'required|string|max:191',
            'attempts' => 'nullable|integer',
            'successful_attempts' => 'nullable|integer',
            'performance_evaluation' => 'nullable|integer',
            'reinforcement' => 'nullable|string|max:191',
            'created_by' => 'nullable|exists:users,id',
        ];

        return $rules;
    }
}
