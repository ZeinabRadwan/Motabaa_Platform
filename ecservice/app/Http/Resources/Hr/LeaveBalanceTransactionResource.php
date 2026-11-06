<?php

namespace App\Http\Resources\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\LeaveBalanceTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveBalanceTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'kind_label' => Employee::labels(LeaveBalanceTransaction::KINDS, 'tr.hr.leave_transaction_kinds')[$this->kind] ?? $this->kind,
            'days' => $this->days,
            'reason' => $this->reason,
            'leave_request_id' => $this->leave_request_id,
            'created_by' => minimalUserReference($this->creator),
            'created_at' => $this->created_at,
        ];
    }
}
