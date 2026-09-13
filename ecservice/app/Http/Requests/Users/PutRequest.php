<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class PutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->routeIs('user.change_password')) {
            return canAny(['admin_users', 'access_centers', 'admin_centers']);
        }

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
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20|unique:users',
            'birthdate' => 'nullable|date',
            'id_or_residence_number' => 'nullable|integer',
            'gender' => 'nullable|integer',
            'roles' => 'nullable|required',
            'address_building' => 'nullable|string|max:50',
            'address_street' => 'nullable|string|max:150',
            'address_area' => 'nullable|string|max:150',
            'address_city' => 'nullable|string|max:100',
            'address_zipcode' => 'nullable|string|max:50',
            'address_number' => 'nullable|string|max:50',
            'address_unit' => 'nullable|string|max:100',
            'qualification' => 'nullable|string|max:100',
            'specialization' => 'nullable|string|max:100',
            'precise_specialization' => 'nullable|string|max:100',
            'current_work' => 'nullable|string|max:50',
            'nationality' => 'nullable',
            'on_center_sponsorship' => 'nullable|integer',
            'job_title' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:50',
            'work_shift' => 'nullable',
            'contract_type' => 'nullable|in:permanent,temporary,part_time',
            'hire_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'id_expiry_date' => 'nullable|date',
            'annual_leave_entitlement' => 'nullable|integer|min:0|max:365',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
            'can_login' => 'sometimes|string',
        ];

        if ($this->routeIs('user.update'))
        {
            $rules['image'] = ['nullable', 'image','mimes:jpeg,png,jpg', 'max:10240'];
            $rules['email']= "nullable|string|email|max:255|unique:users,email,{$this->route('user')['id']}";
            $rules['phone'] ="required|string|max:20|unique:users,phone,{$this->route('user')['id']}";
        }
        if ($this->routeIs('user.create'))
        {
            $rules['center_id'] = 'nullable|exists:centers,id';
            $rules['password'] = 'nullable|confirmed|min:8';
        }
        if ($this->routeIs('user.change_password'))
        {
            $rules= ['password' => 'required|confirmed|min:8'];
        }


        return $rules;
    }

    public function messages()
    {
        return [
            'center_id.required' => __("validation.You don't belong to any center"),
            'center_id.exists' => __("validation.You don't belong to any center"),
        ];
    }
}
