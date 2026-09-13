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
        return [
            'id' => $this->id,
            'questionnaire_id' => $this->questionnaire_id,
            'questionnaire_title' => $this->questionnaire ? $this->questionnaire->getNameAttribute() : '',
            'term_title' => $this->term ? $this->term->getNameAttribute() : '',
            'term_id' => $this->term_id,
            'title' => $this->questionnaire ? $this->questionnaire->getNameAttribute() : '',
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
