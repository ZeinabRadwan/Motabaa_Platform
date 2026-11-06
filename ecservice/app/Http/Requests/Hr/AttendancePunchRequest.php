<?php

namespace App\Http\Requests\Hr;

use App\Support\Hr\AttendancePunchInput;
use Illuminate\Validation\Validator;

/**
 * Check-in / check-out from the attendance page: the browser GPS fix (latitude, longitude, accuracy in meters)
 * or, when the browser could not provide one, the geolocation error. Any client timestamp is ignored.
 */
class AttendancePunchRequest extends HrFormRequest
{
    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude,accuracy'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude,accuracy'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000', 'required_with:latitude,longitude'],
            'location_error' => ['nullable', 'string', 'in:'.implode(',', AttendancePunchInput::LOCATION_ERRORS)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // 0,0 is what broken or spoofed location sources report; no work location is there.
        $validator->after(function (Validator $validator) {
            if ($this->filled('latitude') && $this->filled('longitude')
                && (float) $this->input('latitude') === 0.0 && (float) $this->input('longitude') === 0.0) {
                $validator->errors()->add('latitude', __('validation.hr.attendance_invalid_coordinates'));
            }
        });
    }

    public function punchInput(): AttendancePunchInput
    {
        $number = fn (string $key) => $this->filled($key) ? (float) $this->input($key) : null;

        return new AttendancePunchInput(
            latitude: $number('latitude'),
            longitude: $number('longitude'),
            accuracy: $number('accuracy'),
            locationError: $this->input('location_error'),
            ip: $this->ip(),
            userAgent: $this->userAgent() !== null ? mb_substr($this->userAgent(), 0, 255) : null,
        );
    }
}
