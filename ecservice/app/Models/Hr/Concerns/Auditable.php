<?php

namespace App\Models\Hr\Concerns;

use App\Support\Hr\HrAuditLogger;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Writes an hr_audit_logs row for every create / update / delete / restore of the model.
 * Models may define auditExcludedAttributes() to keep extra columns out of the history.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => HrAuditLogger::recordModelEvent($model, HrAuditLogger::EVENT_CREATED));
        static::updated(fn ($model) => HrAuditLogger::recordModelEvent($model, HrAuditLogger::EVENT_UPDATED));
        static::deleted(function ($model) {
            $soft = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();
            HrAuditLogger::recordModelEvent($model, $soft ? HrAuditLogger::EVENT_DELETED : HrAuditLogger::EVENT_FORCE_DELETED);
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(fn ($model) => HrAuditLogger::recordModelEvent($model, HrAuditLogger::EVENT_RESTORED));
        }
    }

    public function auditExcludedAttributes(): array
    {
        return [];
    }
}
