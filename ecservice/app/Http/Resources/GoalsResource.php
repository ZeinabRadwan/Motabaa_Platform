<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use App\Http\Resources\Concerns\ListResourceMode;

class GoalsResource extends JsonResource
{
    use ListResourceMode;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hideStaffSessionFields = isParentUser($request->user());

        if ($this->listOnly) {
            $assessmentParent = $this->custom_general_goal
                ? ['title' => $this->custom_general_goal]
                : ($this->assesment?->parent ? ['title' => $this->assesment->parent->assessment_title] : null);
            $assessmentFirstField = $this->custom_first_feild
                ? ['title' => $this->custom_first_feild]
                : (($first = $this->assesment?->getFirstFeild()) ? ['title' => $first->assessment_title] : null);

            $payload = [
                'id' => $this->id,
                'case_id' => $this->case_id,
                'case' => $this->case ? [
                    'id' => $this->case->id,
                    'name' => $this->case->name,
                ] : null,
                'title' => $this->goal_title,
                'term_id' => $this->term_id,
                'assessment_id' => $this->assessment_id,
                'assessment_parent' => $assessmentParent,
                'assessment_first_feild' => $assessmentFirstField,
                'assessment_evaluation_method' => $this->assesment?->getEvaluationMethod(),
                'assesment_evaluation_power' => $this->assesment?->getPowerEvaluationMethod(),
                'custom_general_goal' => $this->custom_general_goal,
                'custom_first_feild' => $this->custom_first_feild,
                'started_session' => $this->started_session,
                'sessions_count' => (int) ($this->category === 'independent'
                    ? ($this->evaluation_steps_count ?? 0)
                    : ($this->started_sessions_count ?? 0)),
                'late' => Carbon::now()->isAfter($this->date_to),
                'ended_session' => $this->ended_session,
                'is_ended_session' => $this->ended_session ? Carbon::parse($this->ended_session)->isAfter($this->last_started_session) : false,
                'value' => $this->value,
                'evaluation_value' => $this->evaluation_value ? $this->evaluation_value : '',
                'date_from' => $this->date_from,
                'date_to' => $this->date_to,
                'generalization' => $this->generalization,
                'standard' => $this->standard,
                'deleted_at' => $this->deleted_at,
            ];

            return self::stripStaffSessionFieldsForParent($payload, $hideStaffSessionFields);
        }

        $payload = [
            'id' => $this->id,
            'case_id' => $this->case_id,
            'case' => $this->case,
            'title' => $this->goal_title,
            'description' => $this->description,
            'term_id' => $this->term_id,
            'assessment_id' => $this->assessment_id,
            'assesment' => $this->assesment ? new AssessmentsResource($this->assesment) : null,
            'assessment_parent' => $this->custom_general_goal ? ['title'=>$this->custom_general_goal] : ($this->assesment?->parent ? new AssessmentsResource($this->assesment->parent) : null),
            'assessment_first_feild' => $this->custom_first_feild ? ['title'=>$this->custom_first_feild] : (($first = $this->assesment?->getFirstFeild()) ? new AssessmentsResource($first) : null),
            'assessment_evaluation_method' => $this->assesment?->getEvaluationMethod(),
            'assesment_evaluation_power' => $this->assesment?->getPowerEvaluationMethod(),
            'category' => $this->category,

            'custom_general_goal' => $this->custom_general_goal,
            'custom_first_feild' => $this->custom_first_feild,
            
            'started_session'=> $this->started_session,
            'last_started_session'=> $this->last_started_session,
            'sessions_count' => (int) ($this->category === 'independent'
                ? ($this->evaluation_steps_count ?? 0)
                : ($this->started_sessions_count ?? 0)),
            'late_session'=> $this->started_session ? Carbon::parse($this->started_session)->isAfter($this->date_to) : false,
            'late'=> Carbon::now()->isAfter($this->date_to),
            'ended_session'=> $this->ended_session,
            'is_ended_session'=> $this->ended_session ? Carbon::parse($this->ended_session)->isAfter($this->last_started_session) : false,
            'value' => $this->value,
            'evaluation_value' => $this->evaluation_value ? $this->evaluation_value : '',
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'generalization' => $this->generalization,
            'standard' => $this->standard,
            'deleted_at' => $this->deleted_at,
        ];

        return self::stripStaffSessionFieldsForParent($payload, $hideStaffSessionFields);
    }

    private static function stripStaffSessionFieldsForParent(array $payload, bool $hide): array
    {
        if (!$hide) {
            return $payload;
        }

        foreach (['sessions_count', 'started_session', 'last_started_session', 'ended_session', 'is_ended_session', 'late_session'] as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }
}
