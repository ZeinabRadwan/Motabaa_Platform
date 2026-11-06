<?php

namespace Tests\Unit;

use App\Support\Hr\HrSettingsRegistry as Registry;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class HrSettingsRegistryTest extends TestCase
{
    public function test_defaults_follow_saudi_labor_law(): void
    {
        $defaults = Registry::defaults();

        $this->assertSame([5, 6], $defaults['general.weekend_days'], 'Friday and Saturday');
        $this->assertSame('Asia/Riyadh', $defaults['general.timezone']);
        $this->assertSame(21, $defaults['leave.annual_days']);
        $this->assertSame(30, $defaults['leave.annual_days_after_five_years']);
        $this->assertSame('SAR', $defaults['payroll.currency']);
    }

    public function test_values_round_trip_through_storage_with_their_types(): void
    {
        $this->assertSame(20, Registry::decode('attendance.late_grace_minutes', Registry::encode('attendance.late_grace_minutes', '20')));
        $this->assertFalse(Registry::decode('attendance.allow_manual_attendance', Registry::encode('attendance.allow_manual_attendance', 'false')));
        $this->assertSame([5, 6], Registry::decode('general.weekend_days', Registry::encode('general.weekend_days', ['5', 6, 5])));
        $this->assertSame('Asia/Riyadh', Registry::decode('general.timezone', null), 'missing row falls back to default');
    }

    public function test_validation_rules_accept_valid_and_reject_invalid_nested_payloads(): void
    {
        $rules = Registry::validationRules();

        $valid = Validator::make(['settings' => [
            'general' => ['weekend_days' => [5, 6], 'timezone' => 'Asia/Riyadh'],
            'leave' => ['annual_days' => 25],
        ]], $rules);
        $this->assertFalse($valid->fails(), json_encode($valid->errors()->toArray()));

        $invalid = Validator::make(['settings' => [
            'general' => ['weekend_days' => [7], 'timezone' => 'Mars/Base'],
            'leave' => ['annual_days' => 10],
            'payroll' => ['currency' => 'USD'],
        ]], $rules);
        $this->assertEqualsCanonicalizing([
            'settings.general.weekend_days.0',
            'settings.general.timezone',
            'settings.leave.annual_days',
            'settings.payroll.currency',
        ], array_keys($invalid->errors()->toArray()));
    }
}
