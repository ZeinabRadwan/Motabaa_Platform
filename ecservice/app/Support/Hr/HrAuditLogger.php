<?php

namespace App\Support\Hr;

use App\Models\Hr\AuditLog;
use App\Models\MediaAttachmentAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HrAuditLogger
{
    public const EVENT_CREATED = 'created';
    public const EVENT_UPDATED = 'updated';
    public const EVENT_DELETED = 'deleted';
    public const EVENT_FORCE_DELETED = 'force_deleted';
    public const EVENT_RESTORED = 'restored';
    public const EVENT_APPROVED = 'approved';
    public const EVENT_REJECTED = 'rejected';
    public const EVENT_CANCELLED = 'cancelled';

    /** Bookkeeping columns that never carry business meaning. */
    public const ALWAYS_EXCLUDED = ['created_at', 'updated_at', 'deleted_at'];

    public static function recordModelEvent(Model $model, string $event): ?AuditLog
    {
        $exclude = array_merge(
            self::ALWAYS_EXCLUDED,
            $model->getHidden(),
            method_exists($model, 'auditExcludedAttributes') ? $model->auditExcludedAttributes() : []
        );

        $attributes = $model->getAttributes();

        [$old, $new] = match ($event) {
            self::EVENT_CREATED => [null, self::only($attributes, $exclude)],
            self::EVENT_UPDATED => self::diff($model->getOriginal(), $model->getChanges(), $exclude),
            self::EVENT_DELETED, self::EVENT_FORCE_DELETED => [self::only($attributes, $exclude), null],
            default => [null, null],
        };

        if ($event === self::EVENT_UPDATED && $new === []) {
            return null;
        }

        return self::record($model, $event, $old, $new);
    }

    public static function record(Model $model, string $event, ?array $old = null, ?array $new = null, ?int $centerId = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $user = auth()->user();

        return AuditLog::create([
            'center_id' => $centerId ?? ($model->getAttribute('center_id') ?: null),
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'user_id' => $user?->getKey(),
            'via_impersonation' => $user ? isImpersonating($user) : false,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            'url' => $request ? Str::limit($request->path(), 250, '') : null,
            'correlation_id' => $request ? MediaAttachmentAuditLog::correlationIdFromRequest() : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Changed attributes only, as [old, new], with excluded keys removed.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function diff(array $original, array $changes, array $exclude = []): array
    {
        $old = [];
        $new = [];
        foreach (self::only($changes, $exclude) as $key => $value) {
            $previous = $original[$key] ?? null;
            if ($previous === $value) {
                continue;
            }
            $old[$key] = $previous;
            $new[$key] = $value;
        }

        return [$old, $new];
    }

    private static function only(array $attributes, array $exclude): array
    {
        return array_diff_key($attributes, array_flip($exclude));
    }
}
