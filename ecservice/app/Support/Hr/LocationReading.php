<?php

namespace App\Support\Hr;

use Carbon\CarbonImmutable;

/**
 * One location sample for a periodic check: from the attendance page (server clock) or a registered mobile device
 * (device clock, validated against the server). Carries the fix, or the reason none could be obtained.
 */
final class LocationReading
{
    /** Errors a mobile device may report instead of a fix, in addition to the browser ones. */
    public const MOBILE_LOCATION_ERRORS = ['services_disabled', 'background_restricted'];

    public function __construct(
        public readonly string $source,
        public readonly ?CarbonImmutable $recordedAt = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?float $accuracy = null,
        public readonly ?string $locationError = null,
        public readonly bool $isMocked = false,
        public readonly ?string $clientId = null,
        public readonly ?int $deviceId = null,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
    ) {
    }

    public static function fromPunch(AttendancePunchInput $input): self
    {
        return new self(
            source: 'web',
            latitude: $input->latitude,
            longitude: $input->longitude,
            accuracy: $input->accuracy,
            locationError: $input->locationError,
            ip: $input->ip,
            userAgent: $input->userAgent,
        );
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
