<?php

namespace Tests\Unit;

use App\Support\Hr\HrAuditLogger;
use Tests\TestCase;

class HrAuditDiffTest extends TestCase
{
    public function test_diff_returns_old_and_new_values_of_changed_attributes_only(): void
    {
        [$old, $new] = HrAuditLogger::diff(
            ['name_ar' => 'الرياض', 'city' => 'Riyadh', 'is_active' => 1],
            ['name_ar' => 'فرع الرياض', 'is_active' => 1],
        );

        $this->assertSame(['name_ar' => 'الرياض'], $old);
        $this->assertSame(['name_ar' => 'فرع الرياض'], $new);
    }

    public function test_excluded_and_bookkeeping_columns_are_dropped(): void
    {
        [$old, $new] = HrAuditLogger::diff(
            ['updated_by' => 1, 'updated_at' => '2026-01-01'],
            ['updated_by' => 2, 'updated_at' => '2026-02-01'],
            array_merge(HrAuditLogger::ALWAYS_EXCLUDED, ['updated_by'])
        );

        $this->assertSame([], $old);
        $this->assertSame([], $new);
    }

    public function test_attribute_added_for_the_first_time_has_null_old_value(): void
    {
        [$old, $new] = HrAuditLogger::diff([], ['manager_employee_id' => 7]);

        $this->assertSame(['manager_employee_id' => null], $old);
        $this->assertSame(['manager_employee_id' => 7], $new);
    }
}
