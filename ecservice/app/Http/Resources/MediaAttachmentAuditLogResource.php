<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaAttachmentAuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attachableShort = $this->attachable_type
            ? class_basename($this->attachable_type)
            : null;

        return [
            'id' => $this->id,
            'correlation_id' => $this->correlation_id,
            'operation' => $this->operation,
            'outcome' => $this->outcome,
            'stage' => $this->stage,
            'attachable_type' => $attachableShort,
            'attachable_id' => $this->attachable_id,
            'context_type' => $this->context_type,
            'context_id' => $this->context_id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'center_id' => $this->center_id,
            'original_filename' => $this->original_filename,
            'stored_filename' => $this->stored_filename,
            'storage_path' => $this->storage_path,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'failure_reason' => $this->failure_reason,
            'meta' => $this->meta,
            'created_at' => $this->created_at,
        ];
    }
}
