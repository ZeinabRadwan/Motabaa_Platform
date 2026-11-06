<?php

namespace App\Rules;

use App\Support\Hr\SaudiIdentity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SaudiIdNumber implements ValidationRule
{
    public function __construct(private readonly ?string $nationality = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $reason = SaudiIdentity::validationError(is_scalar($value) ? (string) $value : null, $this->nationality);
        if ($reason !== null) {
            $fail(__('validation.hr.'.$reason));
        }
    }
}
