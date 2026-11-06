<?php

namespace App\Http\Requests\Hr;

use App\Support\Hr\HrContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorization is enforced by the `module:hr-*` route middleware; requests only validate.
 */
abstract class HrFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function centerId(): int
    {
        return HrContext::centerId($this) ?? 0;
    }

    protected function isCreate(string $routeParameter): bool
    {
        return $this->route($routeParameter) === null;
    }

    protected function requiredOnCreate(string $routeParameter): string
    {
        return $this->isCreate($routeParameter) ? 'required' : 'sometimes';
    }
}
