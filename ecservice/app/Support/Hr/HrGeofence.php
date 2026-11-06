<?php

namespace App\Support\Hr;

use App\Models\Hr\AttendancePolicy;

/**
 * Checks a browser GPS fix against a work location's geofence and applies the GPS requirement in force. Pure (no
 * database).
 *
 *  - verified: within the radius with an accuracy no worse than the allowed maximum;
 *  - low_accuracy: the fix is too imprecise to decide (its error circle reaches the geofence);
 *  - outside_radius: beyond the radius even allowing for the reported accuracy, or beyond it with a precise fix;
 *  - location_unavailable: no coordinates (permission denied, GPS error, unsupported browser);
 *  - location_not_configured: the employee has no work location, or it has no coordinates;
 *  - not_required: GPS verification does not apply (the distance is still reported when known).
 * "required" accepts only verified attempts; "flexible" accepts every attempt and flags the unverified ones;
 * "none" accepts every attempt.
 */
final class HrGeofence
{
    public const VERIFIED = 'verified';
    public const LOW_ACCURACY = 'low_accuracy';
    public const OUTSIDE_RADIUS = 'outside_radius';
    public const LOCATION_UNAVAILABLE = 'location_unavailable';
    public const LOCATION_NOT_CONFIGURED = 'location_not_configured';
    public const NOT_REQUIRED = 'not_required';
    /** Check-out closed by the missing check-out action, without a location. */
    public const AUTO_CLOSED = 'auto_closed';
    /** Time entered or corrected by HR (manual attendance), without a location. */
    public const MANUAL = 'manual';

    private const EARTH_RADIUS_METERS = 6371008.8;

    public function __construct(
        public readonly string $status,
        public readonly bool $accepted,
        public readonly bool $flagged,
        public readonly ?float $distanceMeters = null,
        public readonly ?int $radiusMeters = null,
        public readonly ?float $accuracyMeters = null,
    ) {
    }

    /** Great-circle (haversine) distance in meters. */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($a)));
    }

    /**
     * @param  array{latitude: float, longitude: float, radius: int}|null  $site  geofence centre and radius
     * @param  array{latitude: float, longitude: float, accuracy: float}|null  $fix  browser position
     */
    public static function check(string $requirement, ?array $site, ?array $fix, int $maxAccuracyMeters): self
    {
        $distance = $site !== null && $fix !== null
            ? round(self::distanceMeters($site['latitude'], $site['longitude'], $fix['latitude'], $fix['longitude']), 2)
            : null;
        $radius = $site['radius'] ?? null;
        $accuracy = $fix['accuracy'] ?? null;
        $result = fn (string $status) => new self(
            $status,
            $status === self::VERIFIED || $requirement !== AttendancePolicy::GPS_REQUIRED,
            $status !== self::VERIFIED && $requirement === AttendancePolicy::GPS_FLEXIBLE,
            $distance,
            $radius,
            $accuracy,
        );

        if ($requirement === AttendancePolicy::GPS_NONE) {
            return new self(self::NOT_REQUIRED, true, false, $distance, $radius, $accuracy);
        }
        if ($site === null) {
            return $result(self::LOCATION_NOT_CONFIGURED);
        }
        if ($fix === null) {
            return $result(self::LOCATION_UNAVAILABLE);
        }
        if ($accuracy > $maxAccuracyMeters) {
            return $result($distance - $accuracy > $radius ? self::OUTSIDE_RADIUS : self::LOW_ACCURACY);
        }

        return $result($distance <= $radius ? self::VERIFIED : self::OUTSIDE_RADIUS);
    }
}
