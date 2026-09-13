<?php

namespace App\Http\Resources\Case;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TermResource;
use App\Models\Term;

class SCaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentTerm = Term::currentForCenter($this->center_id);

        return [
            'id' => $this->id,
            'center_id' => $this->center_id,
            'name' => $this->name,
            'beneficiary_number' => $this->beneficiary_number,
            'period' => $this->period,
            'disability_type_ids' => $this->disabilities->pluck('id'),
            'disability_type_names' => $this->disabilities->pluck('name'),
            'services_provided' => $this->services->pluck('id'),
            'services' => $this->services->pluck('name'),
            'teacher_id' => $this->teacher[0]['id'] ?? null,
            'teacher' => $this->teacher[0] ?? null,
            'physiotherapist' => $this->physiotherapist[0] ?? null,
            'physiotherapist_id' => $this->physiotherapist[0]['id'] ?? null,
            'occupational_therapy' => $this->occupational_therapy[0] ?? null,
            'occupational_therapy_id' => $this->occupational_therapy[0]['id'] ?? null,
            'psychotherapist' => $this->psychotherapist[0] ?? null,
            'psychotherapist_id' => $this->psychotherapist[0]['id'] ?? null,
            'pronunciation_speech_specialist' => $this->pronunciation_speech[0] ?? null,
            'pronunciation_speech_specialist_id' => $this->pronunciation_speech[0]['id'] ?? null,
            'id_or_residence_number' => $this->id_or_residence_number,
            'nationality' => $this->nationality,
            'gender' => $this->gender,
            'birthdate' => $this->birthdate,
            'age' => \Carbon\Carbon::parse($this->birthdate)->age,
            'birthdate_formated' => \Carbon\Carbon::parse($this->birthdate)->format('Y-m-d'),
            'parent_id' => $this->parent_id,
            'parents_id' => $this->parents->pluck('id'),
            'parents' => $this->parents,
            'emergency_contact' => $this->emergency_contact,
            'phone' => $this->phone,
            'blood_type' => $this->blood_type,
            'address_city' => $this->address_city,
            'address_area' => $this->address_area,
            'address_street' => $this->address_street,
            'address_building' => $this->address_building,
            'address_number' => $this->address_number,
            'address_unit' => $this->address_unit,
            'address_zipcode' => $this->address_zipcode,
            'general_questions' => json_decode($this->general_questions, true),
            'case_study' => json_decode($this->case_study, true),
            'psychological_study' => json_decode($this->psychological_study, true),
            'picture' => $this->urlImage(),
            'terms' => $this->terms(),
            'current_term' => $currentTerm ? new TermResource($currentTerm) : null,
            'plans_types' => $this->plansTypes(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
