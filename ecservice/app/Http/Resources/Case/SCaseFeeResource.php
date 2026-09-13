<?php

namespace App\Http\Resources\Case;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\SCasePayment;
use Carbon\Carbon;

class SCaseFeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
      
    public function toArray(Request $request): array
    {
        $paidAmount = $this->paidPayments();
        $status = '';
        if($paidAmount == 0)
            $status ='Unpaid';
        else if($this->amount > $paidAmount)
            $status = 'Partially Paid';
        else if($this->amount == $paidAmount)
            $status = 'Paid';

        return [
            'id' => $this->id,
            'title' => $this->amount,
            'case_id' => $this->case_id,
            'case_name' => $this->case ? $this->case->name : '',
            'term_id' => $this->term_id,
            'term_name' => $this->term ? $this->term->getNameAttribute() : '',
            'services' => $this->services,
            'services_names' => $this->services ? $this->servicesNames() : '',
            'amount' => $this->amount,
            'notes' => $this->notes,
            'payments' => $this->payments,
            'paid_amount' => $paidAmount,
            'status' => $status,
            'created_by' => $this->created_by,
            'created_by_name' => $this->createdBy ? $this->createdBy->name : '',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
