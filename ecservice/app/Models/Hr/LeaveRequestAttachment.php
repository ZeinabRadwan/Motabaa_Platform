<?php

namespace App\Models\Hr;

use App\Models\Hr\Concerns\BelongsToCenter;
use Illuminate\Database\Eloquent\Model;

/**
 * Supporting document of a leave request, on the private disk under hr/center_{center}/leave/{request}/.
 */
class LeaveRequestAttachment extends Model
{
    use BelongsToCenter;

    protected $table = 'hr_leave_request_attachments';

    protected $fillable = [
        'center_id',
        'leave_request_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }
}
