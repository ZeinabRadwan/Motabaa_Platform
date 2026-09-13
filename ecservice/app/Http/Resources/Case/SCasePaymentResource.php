<?php

namespace App\Http\Resources\Case;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\SCasePayment;
use Carbon\Carbon;
use App\Http\Resources\Concerns\ListResourceMode;

class SCasePaymentResource extends JsonResource
{
    use ListResourceMode;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
      
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scase_id' => $this->scase_id,
            'scase_name' => $this->scase ? $this->scase->name : '',
            'term_id' => $this->term_id,
            'term_name' => $this->term ? $this->term->getNameAttribute() : '',
            'case_fee_id' => $this->case_fee_id,
            'case_fee' => $this->when(!$this->listOnly, $this->caseFee),
            'services' => $this->caseFee ? $this->caseFee->servicesNames() : '',
            'batch' => $this->batch,
            'batch_name' => $this->batchName(),
            'amount' => $this->amount,
            'status' => $this->status,
            'status_name' => SCasePayment::statusLabels()[$this->status],
            'due_date' => $this->due_date,
            'payment_date' => $this->payment_date,
            'payment_file_url' => $this->fileURL(),
            'paid_by' => $this->paid_by,
            'method' => isset($this->methodLables()[$this->method]) ? $this->methodLables()[$this->method] : null,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_by_name' => $this->createdBy ? $this->createdBy->name : ''
        ];
    }
}
