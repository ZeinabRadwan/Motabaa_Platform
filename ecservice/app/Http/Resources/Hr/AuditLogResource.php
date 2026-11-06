<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'auditable_type' => $this->auditable_type,
            'auditable_type_label' => __("tr.hr.auditable_types.{$this->auditable_type}"),
            'auditable_id' => $this->auditable_id,
            'event' => $this->event,
            'event_label' => __("tr.hr.audit_events.{$this->event}"),
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'user' => minimalUserReference($this->user),
            'via_impersonation' => (bool) $this->via_impersonation,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
        ];
    }
}
