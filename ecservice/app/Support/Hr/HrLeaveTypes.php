<?php

namespace App\Support\Hr;

use App\Models\Hr\LeaveType;
use Illuminate\Database\QueryException;

/**
 * System leave types every center gets on first use (Saudi Labor Law defaults). Centers edit their rules,
 * deactivate them or add their own types; system types keep their code and cannot be deleted.
 */
final class HrLeaveTypes
{
    public const DEFAULTS = [
        'annual' => [
            'name_ar' => 'إجازة سنوية', 'name_en' => 'Annual leave', 'category' => 'annual', 'is_paid' => true,
            'balance_mode' => LeaveType::BALANCE_ANNUAL, 'days_per_year' => null, 'carry_over_max_days' => 0,
            'max_days_per_request' => null, 'day_counting' => LeaveType::COUNT_WORKING_DAYS,
            'requires_attachment' => false, 'requires_hr_approval' => false, 'color' => '#2E7D32',
        ],
        'sick' => [
            'name_ar' => 'إجازة مرضية', 'name_en' => 'Sick leave', 'category' => 'sick', 'is_paid' => true,
            'balance_mode' => LeaveType::BALANCE_FIXED, 'days_per_year' => 30, 'carry_over_max_days' => 0,
            'max_days_per_request' => null, 'day_counting' => LeaveType::COUNT_CALENDAR_DAYS,
            'requires_attachment' => true, 'requires_hr_approval' => true, 'color' => '#C62828',
        ],
        'unpaid' => [
            'name_ar' => 'إجازة بدون راتب', 'name_en' => 'Unpaid leave', 'category' => 'unpaid', 'is_paid' => false,
            'balance_mode' => LeaveType::BALANCE_NONE, 'days_per_year' => null, 'carry_over_max_days' => 0,
            'max_days_per_request' => 20, 'day_counting' => LeaveType::COUNT_WORKING_DAYS,
            'requires_attachment' => false, 'requires_hr_approval' => true, 'color' => '#6D4C41',
        ],
        'marriage' => [
            'name_ar' => 'إجازة زواج', 'name_en' => 'Marriage leave', 'category' => 'marriage', 'is_paid' => true,
            'balance_mode' => LeaveType::BALANCE_NONE, 'days_per_year' => null, 'carry_over_max_days' => 0,
            'max_days_per_request' => 5, 'day_counting' => LeaveType::COUNT_WORKING_DAYS,
            'requires_attachment' => true, 'requires_hr_approval' => true, 'color' => '#AD1457',
        ],
        'bereavement' => [
            'name_ar' => 'إجازة وفاة', 'name_en' => 'Bereavement leave', 'category' => 'bereavement', 'is_paid' => true,
            'balance_mode' => LeaveType::BALANCE_NONE, 'days_per_year' => null, 'carry_over_max_days' => 0,
            'max_days_per_request' => 5, 'day_counting' => LeaveType::COUNT_WORKING_DAYS,
            'requires_attachment' => false, 'requires_hr_approval' => true, 'color' => '#455A64',
        ],
        'paternity' => [
            'name_ar' => 'إجازة مولود', 'name_en' => 'Paternity leave', 'category' => 'paternity', 'is_paid' => true,
            'balance_mode' => LeaveType::BALANCE_NONE, 'days_per_year' => null, 'carry_over_max_days' => 0,
            'max_days_per_request' => 3, 'day_counting' => LeaveType::COUNT_WORKING_DAYS,
            'requires_attachment' => true, 'requires_hr_approval' => true, 'color' => '#1565C0',
        ],
        'maternity' => [
            'name_ar' => 'إجازة وضع', 'name_en' => 'Maternity leave', 'category' => 'maternity', 'is_paid' => true,
            'balance_mode' => LeaveType::BALANCE_NONE, 'days_per_year' => null, 'carry_over_max_days' => 0,
            'max_days_per_request' => 84, 'day_counting' => LeaveType::COUNT_CALENDAR_DAYS,
            'requires_attachment' => true, 'requires_hr_approval' => true, 'color' => '#8E24AA',
        ],
        'hajj' => [
            'name_ar' => 'إجازة حج', 'name_en' => 'Hajj leave', 'category' => 'hajj', 'is_paid' => true,
            'balance_mode' => LeaveType::BALANCE_NONE, 'days_per_year' => null, 'carry_over_max_days' => 0,
            'max_days_per_request' => 15, 'day_counting' => LeaveType::COUNT_CALENDAR_DAYS,
            'requires_attachment' => false, 'requires_hr_approval' => true, 'color' => '#00838F',
        ],
    ];

    public static function ensureDefaults(int $centerId): void
    {
        $existing = LeaveType::withTrashed()->forCenter($centerId)->whereIn('code', array_keys(self::DEFAULTS))->pluck('code')->all();
        $order = 0;
        foreach (self::DEFAULTS as $code => $attributes) {
            $order++;
            if (in_array($code, $existing, true)) {
                continue;
            }
            try {
                LeaveType::create($attributes + [
                    'center_id' => $centerId,
                    'code' => $code,
                    'is_system' => true,
                    'allow_self_request' => true,
                    'sort_order' => $order,
                    'is_active' => true,
                ]);
            } catch (QueryException $e) {
                // Created concurrently by another request: the unique (center, code) index keeps one row.
                if (! LeaveType::withTrashed()->forCenter($centerId)->where('code', $code)->exists()) {
                    throw $e;
                }
            }
        }
    }
}
