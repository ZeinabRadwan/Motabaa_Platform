<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeAttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fileName = "absent_file_{$this->attendance_at}";
        return [
            'id' => $this->employees_attendance_id,
            'user_id' => $this->id,
            'user_name' => $this->name,
            'attendance_at' => $this->attendance_at,
            'from' => $this->from,
            'to' => $this->to,
            'status' => $this->employees_attendance_id && ($this->status || $this->status === 0) ? (int)$this->status : null,
            'created_by' => $this->created_by,
            'created_by_name' => $this->created_by_name ?? '',
            'user_picture' => $this->urlImage(),
            'absent_file' => ($this->from == null) ? $this->urlAttendanceFile($fileName) : null,
            'deleted_at' => $this->deleted_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
