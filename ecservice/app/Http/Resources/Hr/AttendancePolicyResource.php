<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\AttendancePolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Attendance policy with the rules in force today. scheduled_version is the next rule change (when loaded);
 * employees_count counts employed employees whose configuration uses the policy today (when counted); has_history
 * tells whether any configuration ever referenced it (when loaded).
 */
class AttendancePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AttendancePolicy $policy */
        $policy = $this->resource;

        return array_merge([
            'id' => $policy->id,
            'code' => $policy->code,
            'name' => $policy->name,
            'name_ar' => $policy->name_ar,
            'name_en' => $policy->name_en,
            'description' => $policy->description,
        ], self::rules($policy), [
            'current_version_id' => $policy->current_version_id,
            'scheduled_version' => $this->when(
                $policy->relationLoaded('scheduledVersion'),
                fn () => $policy->scheduledVersion ? new AttendancePolicyVersionResource($policy->scheduledVersion) : null
            ),
            'employees_count' => $this->when($policy->current_configs_count !== null, fn () => (int) $policy->current_configs_count),
            'has_history' => $this->when($policy->attendance_configs_exists !== null, fn () => (bool) $policy->attendance_configs_exists),
            'is_active' => $policy->is_active,
            'deleted_at' => $policy->deleted_at,
            'created_at' => $policy->created_at,
            'updated_at' => $policy->updated_at,
        ]);
    }

    /** Rule values with their labels (shared with AttendancePolicyVersionResource). */
    public static function rules($model): array
    {
        $labels = AttendancePolicy::optionLabels();
        $rules = $model->ruleValues();

        return $rules + [
            'late_counting_label' => $labels['counting_modes'][$rules['late_counting']] ?? $rules['late_counting'],
            'late_action_label' => $labels['deviation_actions'][$rules['late_action']] ?? $rules['late_action'],
            'early_leave_counting_label' => $labels['counting_modes'][$rules['early_leave_counting']] ?? $rules['early_leave_counting'],
            'early_leave_action_label' => $labels['deviation_actions'][$rules['early_leave_action']] ?? $rules['early_leave_action'],
            'missing_checkout_action_label' => $labels['missing_checkout_actions'][$rules['missing_checkout_action']] ?? $rules['missing_checkout_action'],
            'duplicate_punch_action_label' => $labels['duplicate_actions'][$rules['duplicate_punch_action']] ?? $rules['duplicate_punch_action'],
            'gps_requirement_label' => $labels['gps_requirements'][$rules['gps_requirement']] ?? $rules['gps_requirement'],
        ];
    }
}
