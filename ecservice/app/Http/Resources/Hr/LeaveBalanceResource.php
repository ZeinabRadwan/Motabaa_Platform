<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->leaveType;

        return [
            'id' => $this->id,
            'year' => $this->year,
            'employee' => $this->relationLoaded('employee') ? EmployeeReference::make($this->employee) : null,
            'leave_type' => $type ? [
                'id' => $type->id,
                'code' => $type->code,
                'name' => $type->name,
                'color' => $type->color,
                'is_paid' => $type->is_paid,
                'balance_mode' => $type->balance_mode,
            ] : null,
            'entitled_days' => $this->entitled_days,
            'carried_over_days' => $this->carried_over_days,
            'adjusted_days' => $this->adjusted_days,
            'total_days' => $this->total(),
            'used_days' => $this->used_days,
            'pending_days' => $this->pending_days,
            'available_days' => $this->available(),
        ];
    }
}
