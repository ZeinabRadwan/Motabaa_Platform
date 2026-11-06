<?php

namespace App\Support\Hr;

/**
 * Every HR setting: group, key, type, default and validation rules. Values that must keep history
 * (GOSI rates, salary components) are NOT settings; they live in effective-dated payroll tables.
 *
 * Defaults follow the Saudi Labor Law: Friday/Saturday weekend, 21 days annual leave rising to 30 after
 * five years of service (Article 109).
 */
class HrSettingsRegistry
{
    public const TYPE_STRING = 'string';
    public const TYPE_INT = 'int';
    public const TYPE_BOOL = 'bool';
    public const TYPE_INT_LIST = 'int_list';
    public const TYPE_DATE = 'date';

    /**
     * @return array<string, array<string, array{type: string, default: mixed, rules: array, options?: array}>>
     */
    public static function groups(): array
    {
        return [
            'general' => [
                'timezone' => [
                    'type' => self::TYPE_STRING,
                    'default' => 'Asia/Riyadh',
                    'rules' => ['string', 'timezone'],
                ],
                'weekend_days' => [
                    'type' => self::TYPE_INT_LIST,
                    'default' => [5, 6],
                    'rules' => ['array', 'max:6'],
                    'item_rules' => ['integer', 'between:0,6', 'distinct'],
                    'options' => [0, 1, 2, 3, 4, 5, 6],
                ],
                'calendar_display' => [
                    'type' => self::TYPE_STRING,
                    'default' => 'both',
                    'rules' => ['string', 'in:gregorian,hijri,both'],
                    'options' => ['gregorian', 'hijri', 'both'],
                ],
            ],
            'employees' => [
                'number_prefix' => [
                    'type' => self::TYPE_STRING,
                    'default' => 'EMP',
                    'rules' => ['string', 'max:10', 'regex:/^[A-Za-z0-9-]*$/'],
                ],
            ],
            'attendance' => [
                'late_grace_minutes' => [
                    'type' => self::TYPE_INT,
                    'default' => 15,
                    'rules' => ['integer', 'between:0,240'],
                ],
                'early_leave_grace_minutes' => [
                    'type' => self::TYPE_INT,
                    'default' => 15,
                    'rules' => ['integer', 'between:0,240'],
                ],
                // false: once the grace period is exceeded the whole delay counts; true: only the minutes after it.
                'count_after_grace' => [
                    'type' => self::TYPE_BOOL,
                    'default' => false,
                    'rules' => ['boolean'],
                ],
                // How far before a shift starts / after it ends a punch is still attributed to that shift.
                'punch_window_minutes' => [
                    'type' => self::TYPE_INT,
                    'default' => 240,
                    'rules' => ['integer', 'between:30,720'],
                ],
                'default_gps_radius_meters' => [
                    'type' => self::TYPE_INT,
                    'default' => 100,
                    'rules' => ['integer', 'between:10,5000'],
                ],
                // Website GPS check-in: a browser fix less precise than this cannot verify the employee's location.
                'max_gps_accuracy_meters' => [
                    'type' => self::TYPE_INT,
                    'default' => 100,
                    'rules' => ['integer', 'between:10,2000'],
                ],
                'allow_manual_attendance' => [
                    'type' => self::TYPE_BOOL,
                    'default' => true,
                    'rules' => ['boolean'],
                ],
                // Manual attendance: how many days back records may be entered or changed (0 = today only). Holders
                // of admin_hr-attendance-records may go further back.
                'manual_entry_max_days_back' => [
                    'type' => self::TYPE_INT,
                    'default' => 31,
                    'rules' => ['integer', 'between:0,366'],
                ],
                // Periodic location checks while an electronic session with GPS verification is open. Sessions keep
                // the interval in force when they started.
                'location_monitoring_enabled' => [
                    'type' => self::TYPE_BOOL,
                    'default' => true,
                    'rules' => ['boolean'],
                ],
                'location_check_interval_minutes' => [
                    'type' => self::TYPE_INT,
                    'default' => 30,
                    'rules' => ['integer', 'between:5,240'],
                ],
                // A newly registered mobile device sends locations only after HR approves it.
                'mobile_device_approval_required' => [
                    'type' => self::TYPE_BOOL,
                    'default' => true,
                    'rules' => ['boolean'],
                ],
            ],
            'leave' => [
                'annual_days' => [
                    'type' => self::TYPE_INT,
                    'default' => 21,
                    'rules' => ['integer', 'between:21,365'],
                ],
                'annual_days_after_five_years' => [
                    'type' => self::TYPE_INT,
                    'default' => 30,
                    'rules' => ['integer', 'between:30,365'],
                ],
            ],
            'payroll' => [
                'currency' => [
                    'type' => self::TYPE_STRING,
                    'default' => 'SAR',
                    'rules' => ['string', 'in:SAR'],
                    'options' => ['SAR'],
                ],
                'period_start_day' => [
                    'type' => self::TYPE_INT,
                    'default' => 1,
                    'rules' => ['integer', 'between:1,28'],
                ],
                // Salary, insurance, shift and employment changes cannot take effect on or before this date.
                'changes_locked_until' => [
                    'type' => self::TYPE_DATE,
                    'default' => null,
                    'rules' => ['nullable', 'date_format:Y-m-d'],
                ],
            ],
        ];
    }

    /**
     * Flat map keyed by "group.key".
     */
    public static function definitions(): array
    {
        $flat = [];
        foreach (self::groups() as $group => $keys) {
            foreach ($keys as $key => $definition) {
                $flat["{$group}.{$key}"] = $definition;
            }
        }

        return $flat;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    public static function defaults(): array
    {
        return array_map(fn ($definition) => $definition['default'], self::definitions());
    }

    /**
     * Validation rules for a nested payload: settings.{group}.{key}.
     */
    public static function validationRules(string $root = 'settings'): array
    {
        $rules = [$root => ['required', 'array']];
        foreach (self::definitions() as $key => $definition) {
            $rules["{$root}.{$key}"] = array_merge(['sometimes'], $definition['rules']);
            if (isset($definition['item_rules'])) {
                $rules["{$root}.{$key}.*"] = $definition['item_rules'];
            }
        }

        return $rules;
    }

    public static function cast(string $key, mixed $value): mixed
    {
        $type = self::definitions()[$key]['type'] ?? self::TYPE_STRING;

        return match ($type) {
            self::TYPE_INT => (int) $value,
            self::TYPE_BOOL => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_INT_LIST => array_values(array_unique(array_map('intval', (array) $value))),
            self::TYPE_DATE => $value === null || $value === '' ? null : substr((string) $value, 0, 10),
            default => $value === null ? null : (string) $value,
        };
    }

    public static function encode(string $key, mixed $value): string
    {
        return json_encode(self::cast($key, $value), JSON_UNESCAPED_UNICODE);
    }

    public static function decode(string $key, ?string $stored): mixed
    {
        if ($stored === null) {
            return self::definitions()[$key]['default'] ?? null;
        }

        return self::cast($key, json_decode($stored, true));
    }
}
