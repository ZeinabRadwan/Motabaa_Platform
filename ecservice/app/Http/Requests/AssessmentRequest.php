<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Assessment;

class AssessmentRequest extends FormRequest
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
        $root = Assessment::ROOT_TYPE;
        $feild = Assessment::FEILD_TYPE;
        $goal = Assessment::GOAL_TYPE;
        $rules = [
            'title' => 'required|string|max:191',
            'type' => "required|in:{$root},{$feild},{$goal}",
            'parent_id' => "required_unless:type,{$root}|exists:assessments,id",
            'category' => "required_if:type,{$root}|string|max:191",
            'evaluation_method_id' => "required_if:type,{$root}|exists:evaluation_methods,id",
        ];

        return $rules;
    }
}
