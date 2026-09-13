<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ImpersonateRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (isImpersonating($this->user())) {
            return false;
        }

        return isHasRole('admin') && canAny(['admin_users', 'access_centers', 'admin_centers']);
    }

    public function rules(): array
    {
        return [];
    }

    protected function failedAuthorization()
    {
        throw ValidationException::withMessages([
            'error' => [__("validation.You don't have permission to access this page.")],
        ]);
    }
}
