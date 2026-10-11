<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionnaireTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('targetRole');

        return [
            'id' => $this->id,
            'questionnaire_id' => $this->questionnaire_id,
            'questionnaire_title' => $this->questionnaire ? $this->questionnaire->getNameAttribute() : '',
            'term_title' => $this->term ? $this->term->getNameAttribute() : '',
            'term_id' => $this->term_id,
            'target_audience_type' => $this->resource->normalizeAudienceType($this->target_audience_type ?? null),
            'target_role_id' => $this->target_role_id ? (int) $this->target_role_id : null,
            'target_role_name' => $this->targetRole?->name,
            'title' => $this->questionnaire ? $this->questionnaire->getNameAttribute() : '',
            'starts_at' => $this->starts_at?->format('Y-m-d'),
            'ends_at' => $this->ends_at?->format('Y-m-d'),
            'is_open' => $this->resource->isOpen(),
            'allow_parent_edit_after_submit' => (bool) ($this->questionnaire?->allow_parent_edit_after_submit ?? false),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
