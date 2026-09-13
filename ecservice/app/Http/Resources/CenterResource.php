<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Concerns\ListResourceMode;

class CenterResource extends JsonResource
{
    use ListResourceMode;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        if ($this->listOnly) {
            return [
                'id' => $this->id,
                'title' => $this->getNameAttribute(),
                'country' => $this->country,
                'city' => $this->city,
                'package_name' => $this->package?->title,
                'status' => $this->status,
                'logo' => $this->urlLogo(),
                'deleted_at' => $this->deleted_at,
            ];
        }

        $managers = $this->managers();
        return [
            'id' => $this->id,
            'title' => $this->getNameAttribute(),
            'number_of_cases' => $this->number_of_cases,
            'country' => $this->country,
            'city' => $this->city,
            'phone' => $this->phone,
            'package_id' => $this->package_id,
            'package_name' => $this->package->title,
            'commission' => $this->commission,
            'cr_number' => $this->cr_number,
            'vat_number' => $this->vat_number,
            'email' => $this->email,
            'url' => $this->url,
            'status' => $this->status,
            'managers' => $managers,
            'managers_id' => $managers ? array_values(array_flip($managers)) : null,
            'logo' => $this->urlLogo(),
            'n_cases' => null,
            'n_staff' => null,
            'storage_size' => null,
            'can_pay' => $this->canPay(),
            'is_paid' => $this->isPaid(),
            'next_subscription_date' => $this->nextSubscription(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
