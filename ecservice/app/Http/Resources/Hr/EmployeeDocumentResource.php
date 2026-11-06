<?php

namespace App\Http\Resources\Hr;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expiry = $this->expiry_date;

        return [
            'id' => $this->id,
            'category' => $this->category,
            'category_label' => __("tr.hr.document_categories.{$this->category}"),
            'document_type' => $this->document_type,
            'document_type_label' => __("tr.hr.document_types.{$this->document_type}"),
            'title' => $this->title,
            'reference_number' => $this->reference_number,
            'issue_date' => $this->issue_date?->toDateString(),
            'expiry_date' => $expiry?->toDateString(),
            'is_expired' => $expiry !== null && $expiry->lt(today()),
            'expires_soon' => $expiry !== null && $expiry->gte(today()) && $expiry->lte(today()->addDays(User::HR_ALERT_DAYS)),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'notes' => $this->notes,
            'uploaded_by' => $this->uploader?->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
