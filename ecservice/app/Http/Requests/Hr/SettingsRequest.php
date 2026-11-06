<?php

namespace App\Http\Requests\Hr;

use App\Support\Hr\HrSettingsRegistry;

class SettingsRequest extends HrFormRequest
{
    public function rules(): array
    {
        return HrSettingsRegistry::validationRules('settings');
    }
}
