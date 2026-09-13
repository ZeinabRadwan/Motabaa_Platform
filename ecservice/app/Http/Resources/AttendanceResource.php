<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Attendance;

class AttendanceResource extends JsonResource
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
            'status' => $this->status,
            'case_id' => $this->case_id,
            'attendance_at' => $this->attendance_at,
            'absent_file' => $this->status == Attendance::ABSENT ? $this->urlFile() : null,
            'created_by' => $this->created_by,
            'created_by_name' => $this->createdBy ? $this->createdBy->name : ''
        ];
    }
}
