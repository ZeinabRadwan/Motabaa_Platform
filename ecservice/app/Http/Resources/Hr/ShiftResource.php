<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\Shift;
use App\Support\Hr\HrShiftMath;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shift with the timing in force today; `rotation_slots` resolves the cycle days for display.
 */
class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Shift $shift */
        $shift = $this->resource;
        $rotating = $shift->isRotating();
        $start = $rotating ? null : substr((string) $shift->start_time, 0, 5);
        $end = $rotating ? null : substr((string) $shift->end_time, 0, 5);

        return [
            'id' => $shift->id,
            'code' => $shift->code,
            'name' => $shift->name,
            'name_ar' => $shift->name_ar,
            'name_en' => $shift->name_en,
            'shift_type' => $shift->shift_type,
            'shift_type_label' => Shift::typeLabels()[$shift->shift_type] ?? $shift->shift_type,
            'color' => $shift->color,
            'branch_id' => $shift->branch_id,
            'branch_name' => $shift->branch?->name,
            'is_rotating' => $rotating,
            'start_time' => $start,
            'end_time' => $end,
            'time_range' => $shift->timeRange(),
            'is_overnight' => ! $rotating && HrShiftMath::isOvernight($start, $end),
            'span_minutes' => $rotating ? null : HrShiftMath::spanMinutes($start, $end),
            'break_start_time' => $shift->break_start_time ? substr((string) $shift->break_start_time, 0, 5) : null,
            'break_minutes' => $shift->break_minutes,
            'expected_minutes' => $shift->expected_minutes,
            'effective_expected_minutes' => $rotating ? null : HrShiftMath::expectedMinutes($start, $end, (int) $shift->break_minutes, $shift->expected_minutes),
            'grace_minutes' => $shift->grace_minutes,
            'early_leave_grace_minutes' => $shift->early_leave_grace_minutes,
            'working_days' => $shift->working_days_list,
            'rest_days' => $shift->rest_days_list,
            'rotation_pattern' => $rotating ? ($shift->rotation_pattern ?? []) : [],
            'rotation_slots' => $rotating ? $this->slots($shift) : [],
            'cycle_length' => $shift->cycleLength(),
            'is_active' => $shift->is_active,
            'employees_count' => $this->whenCounted('currentAssignments'),
            'scheduled_version' => $this->whenLoaded('scheduledVersion', fn () => $shift->scheduledVersion
                ? new ShiftVersionResource($shift->scheduledVersion)
                : null),
            'deleted_at' => $shift->deleted_at,
            'created_at' => $shift->created_at,
            'updated_at' => $shift->updated_at,
        ];
    }

    private function slots(Shift $shift): array
    {
        $pattern = $shift->rotation_pattern ?? [];
        $ids = array_values(array_unique(array_filter($pattern, fn ($slot) => $slot !== null)));
        $shifts = Shift::withTrashed()->whereIn('id', $ids)->get()->keyBy('id')->all();

        $slots = [];
        foreach ($pattern as $index => $slot) {
            $slotShift = $slot !== null ? ($shifts[$slot] ?? null) : null;
            $slots[] = [
                'day' => $index + 1,
                'shift_id' => $slot,
                'code' => $slotShift?->code,
                'name' => $slotShift?->name,
                'time_range' => $slotShift?->timeRange(),
                'color' => $slotShift?->color,
                'is_rest' => $slot === null,
            ];
        }

        return $slots;
    }
}
