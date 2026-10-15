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

        $teacher = minimalUserReferenceFromRelation($this->teacher);
        $physiotherapist = minimalUserReferenceFromRelation($this->physiotherapist);
        $occupationalTherapy = minimalUserReferenceFromRelation($this->occupational_therapy);
        $psychotherapist = minimalUserReferenceFromRelation($this->psychotherapist);
        $pronunciationSpeech = minimalUserReferenceFromRelation($this->pronunciation_speech);

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
            'teacher_id' => $teacher['id'] ?? null,
            'teacher' => $teacher,
            'physiotherapist' => $physiotherapist,
            'physiotherapist_id' => $physiotherapist['id'] ?? null,
            'occupational_therapy' => $occupationalTherapy,
            'occupational_therapy_id' => $occupationalTherapy['id'] ?? null,
            'psychotherapist' => $psychotherapist,
            'psychotherapist_id' => $psychotherapist['id'] ?? null,
            'pronunciation_speech_specialist' => $pronunciationSpeech,
            'pronunciation_speech_specialist_id' => $pronunciationSpeech['id'] ?? null,
            'id_or_residence_number' => $this->id_or_residence_number,
            'nationality' => $this->nationality,
            'gender' => $this->gender,
            'birthdate' => $this->birthdate,
            'age' => \Carbon\Carbon::parse($this->birthdate)->age,
            'birthdate_formated' => \Carbon\Carbon::parse($this->birthdate)->format('Y-m-d'),
            'parent_id' => $this->parent_id,
            'parents_id' => $this->parents->pluck('id'),
            'parents' => $this->parents->map(fn ($parent) => minimalUserReference($parent))->values(),
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
