<?php

namespace App\Models\Hr\Concerns;

/**
 * Timing columns shared by hr_shifts (cache of the version in force today) and hr_shift_versions.
 * Day lists use the general.weekend_days numbering (0 = Sunday ... 6 = Saturday), stored as sorted CSV.
 */
trait HasShiftTiming
{
    public const TIMING_FIELDS = [
        'start_time', 'end_time', 'break_start_time', 'break_minutes', 'expected_minutes',
        'grace_minutes', 'early_leave_grace_minutes', 'working_days', 'rest_days', 'rotation_pattern',
    ];

    public function initializeHasShiftTiming(): void
    {
        $this->mergeCasts([
            'break_minutes' => 'integer',
            'expected_minutes' => 'integer',
            'grace_minutes' => 'integer',
            'early_leave_grace_minutes' => 'integer',
            'rotation_pattern' => 'array',
        ]);
    }

    /** @return array<int, int> */
    public function getWorkingDaysListAttribute(): array
    {
        return self::decodeDays($this->attributes['working_days'] ?? null);
    }

    /** @return array<int, int> */
    public function getRestDaysListAttribute(): array
    {
        return self::decodeDays($this->attributes['rest_days'] ?? null);
    }

    /**
     * Timing values in a comparable form (times as H:i, day lists as CSV, cycle slots as int|null).
     */
    public function timingAttributes(): array
    {
        $values = [];
        foreach (self::TIMING_FIELDS as $field) {
            $values[$field] = $this->getAttribute($field);
        }

        return self::normalizeTiming($values);
    }

    public static function normalizeTiming(array $values): array
    {
        $time = fn ($value) => $value === null || $value === '' ? null : substr((string) $value, 0, 5);
        $int = fn ($value) => $value === null || $value === '' ? null : (int) $value;
        $days = fn ($value) => is_array($value) ? self::encodeDays($value) : ($value === '' ? null : $value);
        $pattern = $values['rotation_pattern'] ?? null;

        return [
            'start_time' => $time($values['start_time'] ?? null),
            'end_time' => $time($values['end_time'] ?? null),
            'break_start_time' => $time($values['break_start_time'] ?? null),
            'break_minutes' => (int) ($values['break_minutes'] ?? 0),
            'expected_minutes' => $int($values['expected_minutes'] ?? null),
            'grace_minutes' => $int($values['grace_minutes'] ?? null),
            'early_leave_grace_minutes' => $int($values['early_leave_grace_minutes'] ?? null),
            'working_days' => $days($values['working_days'] ?? null),
            'rest_days' => $days($values['rest_days'] ?? null),
            'rotation_pattern' => is_array($pattern) && $pattern !== []
                ? array_map(fn ($slot) => $slot === null || $slot === '' ? null : (int) $slot, array_values($pattern))
                : null,
        ];
    }

    /** @return array<int, int> */
    public static function decodeDays(?string $raw): array
    {
        return $raw === null || $raw === '' ? [] : array_map('intval', explode(',', $raw));
    }

    /** @param  array<int, int|string>|null  $days */
    public static function encodeDays(?array $days): ?string
    {
        if (! $days) {
            return null;
        }
        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);

        return implode(',', $days);
    }
}
