<?php

namespace App\Http\Resources\Admin\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserFileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'file_name' => $this['file_name'] ?? null,
            'file_url' => $this['file_url'] ?? null,
            'file_extension' => $this['file_extension'] ?? null,
            'document_type' => $this['document_type'] ?? 'other',
            'document_type_label' => $this['document_type_label'] ?? null,
            'expiry_date' => $this['expiry_date'] ?? null,
            'is_expired' => (bool) ($this['is_expired'] ?? false),
            'is_expiring' => (bool) ($this['is_expiring'] ?? false),
        ];
    }
}
