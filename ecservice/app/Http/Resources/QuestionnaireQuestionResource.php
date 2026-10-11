<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionnaireQuestionResource extends JsonResource
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
            'section_id' => $this->section_id ? (int) $this->section_id : null,
            'title' => $this->getNameAttribute(),
            'type' => $this->type,
            'required' => $this->required,
            'options' => $this->choiceOptions(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
