<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CenterRequest extends FormRequest
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
            'title' => 'required',
            'title_local' => 'nullable',
            'number_of_cases' => 'required',
            'country' => 'required',
            'city' => 'required',
            'phone' => 'nullable',
            'package_id' => 'required',
            'commission' => 'nullable',
            'cr_number' => 'nullable',
            'vat_number' => 'nullable',
            'email' => 'nullable',
            'url' => 'nullable',
            'status' => 'required',
        ];

        return $rules;
    }
}
