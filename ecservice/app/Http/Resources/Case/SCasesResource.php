<?php

namespace App\Http\Resources\Case;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SCasesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attendances = $this->whenLoaded('attendances', function () {
            return $this->attendances->map(function ($attendance) {
                return new \App\Http\Resources\AttendanceResource($attendance);
            });
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'beneficiary_number' => $this->beneficiary_number,
            'disability_type_ids' => $this->whenLoaded('disabilities', function () {
                return $this->disabilities->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])->values();
            }),
            'services' => $this->whenLoaded('services', function () {
                return $this->services->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])->values();
            }),
            'attendance' => $attendances,
            'parents' => $this->whenLoaded('parents', function () {
                return $this->parents->map(fn ($parent) => [
                    'id' => $parent->id,
                    'name' => $parent->name,
                ])->values();
            }),
            'picture' => $this->urlImage(),
            'deleted_at' => $this->deleted_at,
        ];
    }
}
