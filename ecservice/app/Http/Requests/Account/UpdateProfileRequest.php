<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => 'required|string|max:255',
            'email' => "nullable|string|email|max:255|unique:users,email,{$userId}",
            'phone' => "required|string|max:20|unique:users,phone,{$userId}",
            'birthdate' => 'nullable|date',
            'id_or_residence_number' => 'nullable|integer',
            'gender' => 'nullable|integer',
            'nationality' => 'nullable',
            'address_building' => 'nullable|string|max:50',
            'address_street' => 'nullable|string|max:150',
            'address_area' => 'nullable|string|max:150',
            'address_city' => 'nullable|string|max:100',
            'address_zipcode' => 'nullable|string|max:50',
            'address_number' => 'nullable|string|max:50',
            'address_unit' => 'nullable|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
            'current_image' => 'nullable|string',
        ];
    }
}
