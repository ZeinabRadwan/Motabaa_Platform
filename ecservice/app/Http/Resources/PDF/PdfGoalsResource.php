<?php

namespace App\Http\Resources\PDF;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
class PdfGoalsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->goal_title,
            'term_id' => $this->term_id,
            'assessment_id' => $this->assessment_id,
            'assesment' => $this->assesment,
            'assessment_parent' => $this->custom_general_goal ? ['title'=>$this->custom_general_goal] : $this->assesment?->parent,
            'assessment_first_feild' => $this->custom_first_feild ? ['title'=>$this->custom_first_feild] : $this->assesment?->getFirstFeild(),
            'assessment_evaluation_method' => $this->assesment?->getEvaluationMethod(),
            'category' => $this->category,
            'started_session'=> $this->started_session,
            'last_started_session'=> $this->last_started_session,
            'late_session'=> Carbon::parse($this->started_session)->isAfter($this->date_to),
            'ended_session'=> $this->ended_session,
            'is_ended_session'=> $this->ended_session ? Carbon::parse($this->ended_session)->isAfter($this->last_started_session) : false,
            'value' => $this->value,
            'evaluation_value' => $this->evaluation_value,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'generalization' => $this->generalization,
            'standard' => $this->standard,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
