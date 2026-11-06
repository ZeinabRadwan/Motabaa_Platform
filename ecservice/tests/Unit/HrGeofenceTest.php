<?php

namespace Tests\Unit;

use App\Support\Hr\HrGeofence;
use PHPUnit\Framework\TestCase;

class HrGeofenceTest extends TestCase
{
    private const SITE = ['latitude' => 24.7136, 'longitude' => 46.6753, 'radius' => 100];

    public function test_haversine_distance_matches_known_values(): void
    {
        $this->assertEqualsWithDelta(0.0, HrGeofence::distanceMeters(24.7136, 46.6753, 24.7136, 46.6753), 0.001);
        // 0.001 degree of latitude is about 111.2 m everywhere.
        $this->assertEqualsWithDelta(111.2, HrGeofence::distanceMeters(24.7136, 46.6753, 24.7146, 46.6753), 0.5);
        // Riyadh to Jeddah: about 845 km.
        $this->assertEqualsWithDelta(845_000, HrGeofence::distanceMeters(24.7136, 46.6753, 21.4858, 39.1925), 10_000);
        // Symmetric.
        $this->assertEqualsWithDelta(
            HrGeofence::distanceMeters(24.7136, 46.6753, 24.72, 46.68),
            HrGeofence::distanceMeters(24.72, 46.68, 24.7136, 46.6753),
            0.0001,
        );
    }

    public function test_required_accepts_only_a_precise_fix_inside_the_radius(): void
    {
        $inside = HrGeofence::check('required', self::SITE, $this->fix(30, 15), 100);
        $this->assertSame(HrGeofence::VERIFIED, $inside->status);
        $this->assertTrue($inside->accepted);
        $this->assertFalse($inside->flagged);
        $this->assertEqualsWithDelta(30, $inside->distanceMeters, 1);
        $this->assertSame(100, $inside->radiusMeters);

        $outside = HrGeofence::check('required', self::SITE, $this->fix(250, 15), 100);
        $this->assertSame(HrGeofence::OUTSIDE_RADIUS, $outside->status);
        $this->assertFalse($outside->accepted);

        $edge = HrGeofence::check('required', self::SITE, $this->fix(99, 10), 100);
        $this->assertSame(HrGeofence::VERIFIED, $edge->status);
    }

    public function test_imprecise_fix_cannot_verify_unless_clearly_outside(): void
    {
        $vague = HrGeofence::check('required', self::SITE, $this->fix(20, 400), 100);
        $this->assertSame(HrGeofence::LOW_ACCURACY, $vague->status);
        $this->assertFalse($vague->accepted);

        // 2 km away with a 400 m error circle: outside whatever the error.
        $far = HrGeofence::check('required', self::SITE, $this->fix(2000, 400), 100);
        $this->assertSame(HrGeofence::OUTSIDE_RADIUS, $far->status);
    }

    public function test_missing_fix_or_site_is_rejected_when_required_and_flagged_when_flexible(): void
    {
        $this->assertSame(HrGeofence::LOCATION_UNAVAILABLE, HrGeofence::check('required', self::SITE, null, 100)->status);
        $this->assertFalse(HrGeofence::check('required', self::SITE, null, 100)->accepted);
        $this->assertSame(HrGeofence::LOCATION_NOT_CONFIGURED, HrGeofence::check('required', null, $this->fix(10, 10), 100)->status);
        $this->assertFalse(HrGeofence::check('required', null, $this->fix(10, 10), 100)->accepted);

        foreach ([[self::SITE, null], [null, $this->fix(10, 10)], [self::SITE, $this->fix(500, 10)], [self::SITE, $this->fix(10, 500)]] as [$site, $fix]) {
            $check = HrGeofence::check('flexible', $site, $fix, 100);
            $this->assertTrue($check->accepted);
            $this->assertTrue($check->flagged);
        }
        $verified = HrGeofence::check('flexible', self::SITE, $this->fix(10, 10), 100);
        $this->assertTrue($verified->accepted);
        $this->assertFalse($verified->flagged);
    }

    public function test_none_never_verifies_but_reports_the_distance(): void
    {
        $check = HrGeofence::check('none', self::SITE, $this->fix(5000, 10), 100);
        $this->assertSame(HrGeofence::NOT_REQUIRED, $check->status);
        $this->assertTrue($check->accepted);
        $this->assertFalse($check->flagged);
        $this->assertEqualsWithDelta(5000, $check->distanceMeters, 5);

        $this->assertTrue(HrGeofence::check('none', null, null, 100)->accepted);
    }

    /** A fix $meters north of the site with the given accuracy. */
    private function fix(float $meters, float $accuracy): array
    {
        return ['latitude' => self::SITE['latitude'] + $meters / 111_195, 'longitude' => self::SITE['longitude'], 'accuracy' => $accuracy];
    }
}
