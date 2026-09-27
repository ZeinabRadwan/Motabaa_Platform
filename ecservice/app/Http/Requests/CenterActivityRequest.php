<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CenterActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'center_id' => 'sometimes|integer|exists:centers,id',
            'term_id' => 'required|integer|exists:terms,id',
            'role_ids' => 'sometimes|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ];
    }
}
