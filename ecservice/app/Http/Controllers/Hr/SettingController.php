<?php

namespace App\Http\Controllers\Hr;

use App\Http\Requests\Hr\SettingsRequest;
use App\Support\Hr\HrSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends HrController
{
    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, fn (int $centerId) => success([
            'values' => HrSettings::grouped($centerId),
            'schema' => HrSettings::schema(),
        ]));
    }

    public function update(SettingsRequest $request): JsonResponse
    {
        return $this->inCenter($request, fn (int $centerId) => success([
            'values' => HrSettings::update($centerId, $request->validated('settings'), $request->user()),
            'schema' => HrSettings::schema(),
        ], null, __('validation.hr.saved')));
    }
}
