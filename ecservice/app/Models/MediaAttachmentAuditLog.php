<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaAttachmentAuditLog extends Model
{
    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected $fillable = [
        'correlation_id',
        'operation',
        'outcome',
        'stage',
        'attachable_type',
        'attachable_id',
        'context_type',
        'context_id',
        'user_id',
        'center_id',
        'original_filename',
        'stored_filename',
        'storage_path',
        'mime_type',
        'file_size',
        'failure_reason',
        'meta',
        'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public static function correlationIdFromRequest(): string
    {
        $header = request()->header('X-Request-Id') ?? request()->header('X-Correlation-Id');
        if (is_string($header) && Str::isUuid($header)) {
            return $header;
        }

        return (string) Str::uuid();
    }

    public static function record(array $attributes): self
    {
        if (! isset($attributes['correlation_id'])) {
            $attributes['correlation_id'] = self::correlationIdFromRequest();
        }
        if (! isset($attributes['created_at'])) {
            $attributes['created_at'] = now();
        }

        return self::create($attributes);
    }
}
