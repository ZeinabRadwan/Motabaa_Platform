<?php

namespace App\Support\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeChange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes hr_employee_changes rows. Referenced records (branch, department, ...) are stored as id plus a
 * bilingual name snapshot.
 */
final class HrEmployeeChangeLog
{
    public static function record(
        Employee $employee,
        string $type,
        mixed $old,
        mixed $new,
        CarbonInterface|string $effectiveDate,
        ?string $reason = null,
        ?Model $source = null,
        array $labels = [],
        string $action = EmployeeChange::ACTION_SET
    ): EmployeeChange {
        return EmployeeChange::create([
            'center_id' => $employee->center_id,
            'employee_id' => $employee->id,
            'change_type' => $type,
            'action' => $action,
            'old_value' => self::scalar($old),
            'new_value' => self::scalar($new),
            'old_label' => isset($labels['old']) ? self::encodeLabel($labels['old']) : null,
            'new_label' => isset($labels['new']) ? self::encodeLabel($labels['new']) : null,
            'effective_date' => $effectiveDate instanceof CarbonInterface ? $effectiveDate->toDateString() : substr($effectiveDate, 0, 10),
            'reason' => $reason !== null && $reason !== '' ? mb_substr($reason, 0, 500) : null,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'changed_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    /** Bilingual snapshot of a model using name_ar / name_en, or a user / employee name. */
    public static function snapshot(?Model $model): ?array
    {
        if ($model === null) {
            return null;
        }

        if ($model->getAttribute('name_ar') !== null) {
            return ['ar' => (string) $model->name_ar, 'en' => (string) ($model->name_en ?: $model->name_ar)];
        }

        if ($model instanceof Employee) {
            $name = (string) ($model->user?->name ?? '');

            return ['ar' => $name, 'en' => $name];
        }

        $name = (string) $model->getAttribute('name');

        return ['ar' => $name, 'en' => $name];
    }

    private static function encodeLabel(mixed $label): ?string
    {
        if ($label === null) {
            return null;
        }

        return is_array($label) ? json_encode($label, JSON_UNESCAPED_UNICODE) : (string) $label;
    }

    private static function scalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
