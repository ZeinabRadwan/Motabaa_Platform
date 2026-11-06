<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\Auditable;
use App\Models\Hr\Concerns\BelongsToCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Employee file kept on a private disk and served only through the authorized download endpoint.
 * Soft-deleted rows keep their file so a deleted contract can still be recovered.
 */
class EmployeeDocument extends Model
{
    use Auditable, BelongsToCenter, SoftDeletes;

    protected $table = 'hr_employee_documents';

    public const CATEGORY_DOCUMENT = 'document';
    public const CATEGORY_CONTRACT = 'contract';
    public const CATEGORY_ATTACHMENT = 'attachment';

    public const TYPES_BY_CATEGORY = [
        self::CATEGORY_DOCUMENT => [
            'national_id', 'iqama', 'passport', 'visa', 'work_permit', 'driving_license',
            'qualification', 'medical', 'bank_letter', 'other',
        ],
        self::CATEGORY_CONTRACT => ['employment_contract', 'contract_amendment', 'offer_letter', 'other'],
        self::CATEGORY_ATTACHMENT => ['supporting', 'other'],
    ];

    public const MAX_UPLOAD_KB = 10240;

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

    protected $fillable = [
        'center_id',
        'employee_id',
        'category',
        'document_type',
        'title',
        'reference_number',
        'issue_date',
        'expiry_date',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'size' => 'integer',
    ];

    public static function categories(): array
    {
        return array_keys(self::TYPES_BY_CATEGORY);
    }

    public static function allTypes(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::TYPES_BY_CATEGORY))));
    }

    public function auditExcludedAttributes(): array
    {
        return ['disk', 'path'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
