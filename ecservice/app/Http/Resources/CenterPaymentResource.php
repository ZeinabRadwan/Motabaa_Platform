<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CenterPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'center_id' => $this->center_id,
            'user_id' => $this->user_id,
            'center' => $this->center,
            'user' => $this->user,
            'amount' => $this->amount,
            'package_id' => $this->package_id,
            'package_name' => $this->package->title,
            'payment_type' => $this->payment_type,
            'payment_duration' => $this->payment_duration,
            'date' => $this->date,
            'expiry_date' => $this->expiry_date,
            'status' => $this->status,
            'payment_file_url' => !usesBunnyStorage() ? $this->fileURL() : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
