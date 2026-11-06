<?php

namespace App\Support\Hr;

/**
 * What the attendance page submits with a check-in / check-out: the browser GPS fix (or the reason it could not
 * get one) plus request metadata. The punch time is always the server's clock, never the client's.
 */
final class AttendancePunchInput
{
    public const LOCATION_ERRORS = ['permission_denied', 'position_unavailable', 'timeout', 'unsupported', 'insecure_context'];

    public function __construct(
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?float $accuracy = null,
        public readonly ?string $locationError = null,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
    ) {
    }

    /** @return array{latitude: float, longitude: float, accuracy: float}|null */
    public function fix(): ?array
    {
        if ($this->latitude === null || $this->longitude === null || $this->accuracy === null) {
            return null;
        }

        return ['latitude' => $this->latitude, 'longitude' => $this->longitude, 'accuracy' => $this->accuracy];
    }
}
