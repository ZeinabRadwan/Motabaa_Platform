<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class PhoneNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return canAny(['edit_parents', 'admin_parents']);
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('user.phone_numbers.store') => [
                'phone' => 'required|string|max:30',
                'label' => 'nullable|string|max:50',
                'verified' => 'sometimes|boolean',
            ],
            $this->routeIs('user.phone_numbers.update') => [
                'phone' => 'sometimes|nullable|string|max:30',
                'label' => 'nullable|string|max:50',
            ],
            $this->routeIs('user.phone_numbers.primary') => [
                'mark_verified' => 'sometimes|boolean',
            ],
            $this->routeIs('user.phone_numbers.verify') => [
                'verified' => 'required|boolean',
            ],
            default => [],
        };
    }
}
