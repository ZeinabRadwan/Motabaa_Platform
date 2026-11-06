<?php

namespace App\Http\Requests\Hr;

use App\Support\Hr\LocationReading;

/**
 * Periodic location check from the attendance page: the browser fix or its geolocation error, plus a client id so
 * a request retried after a network failure is not stored twice. The server clock is used.
 */
class LocationCheckRequest extends AttendancePunchRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'client_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }

    public function reading(): LocationReading
    {
        $input = $this->punchInput();

        return new LocationReading(
            source: 'web',
            latitude: $input->latitude,
            longitude: $input->longitude,
            accuracy: $input->accuracy,
            locationError: $input->locationError,
            clientId: $this->input('client_id'),
            ip: $input->ip,
            userAgent: $input->userAgent,
        );
    }
}
