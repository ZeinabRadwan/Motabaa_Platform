<?php

namespace App\Http\Requests\Hr;

use App\Support\Hr\AttendancePunchInput;
use App\Support\Hr\LocationReading;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

/**
 * Location samples queued by a registered mobile device (up to 50 per request). Timestamps must carry a timezone
 * offset; `sent_at` (device clock when sending) must be within MAX_CLOCK_SKEW_SECONDS of the server so the samples'
 * device timestamps can be trusted. Every sample has a client id (idempotency key) and either a fix or an error.
 */
class MobileLocationBatchRequest extends HrFormRequest
{
    public const MAX_SAMPLES = 50;

    public const MAX_CLOCK_SKEW_SECONDS = 300;

    private const TIMESTAMP = 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/';

    public function rules(): array
    {
        $errors = array_merge(AttendancePunchInput::LOCATION_ERRORS, LocationReading::MOBILE_LOCATION_ERRORS);

        return [
            'sent_at' => ['required', 'string', self::TIMESTAMP],
            'locations' => ['required', 'array', 'min:1', 'max:'.self::MAX_SAMPLES],
            'locations.*.client_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/', 'distinct'],
            'locations.*.recorded_at' => ['required', 'string', self::TIMESTAMP],
            'locations.*.source' => ['required', 'string', 'in:foreground,background'],
            'locations.*.latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:locations.*.longitude,locations.*.accuracy'],
            'locations.*.longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:locations.*.latitude,locations.*.accuracy'],
            'locations.*.accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000', 'required_with:locations.*.latitude,locations.*.longitude'],
            'locations.*.location_error' => ['nullable', 'string', 'in:'.implode(',', $errors), 'required_without:locations.*.latitude'],
            'locations.*.is_mocked' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $skew = abs(CarbonImmutable::parse($this->input('sent_at'))->getTimestamp() - now()->getTimestamp());
            if ($skew > self::MAX_CLOCK_SKEW_SECONDS) {
                $validator->errors()->add('sent_at', __('validation.hr.device_clock_invalid', ['minutes' => intdiv(self::MAX_CLOCK_SKEW_SECONDS, 60)]));
            }
            foreach ((array) $this->input('locations') as $index => $sample) {
                if (isset($sample['latitude'], $sample['longitude']) && (float) $sample['latitude'] === 0.0 && (float) $sample['longitude'] === 0.0) {
                    $validator->errors()->add("locations.{$index}.latitude", __('validation.hr.attendance_invalid_coordinates'));
                }
            }
        });
    }

    /** @return LocationReading[] */
    public function readings(?int $deviceId): array
    {
        $number = fn (array $sample, string $key) => isset($sample[$key]) && $sample[$key] !== '' ? (float) $sample[$key] : null;
        $userAgent = $this->userAgent() !== null ? mb_substr($this->userAgent(), 0, 255) : null;

        return array_map(fn (array $sample) => new LocationReading(
            source: $sample['source'] === 'background' ? 'mobile_background' : 'mobile_foreground',
            recordedAt: CarbonImmutable::parse($sample['recorded_at']),
            latitude: $number($sample, 'latitude'),
            longitude: $number($sample, 'longitude'),
            accuracy: $number($sample, 'accuracy'),
            locationError: $sample['location_error'] ?? null,
            isMocked: filter_var($sample['is_mocked'] ?? false, FILTER_VALIDATE_BOOLEAN),
            clientId: $sample['client_id'],
            deviceId: $deviceId,
            ip: $this->ip(),
            userAgent: $userAgent,
        ), array_values((array) $this->input('locations')));
    }
}
