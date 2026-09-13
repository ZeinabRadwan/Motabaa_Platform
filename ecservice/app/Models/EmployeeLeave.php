<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLeave extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'date_from',
        'date_to',
        'days',
        'status',
        'notes',
        'created_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public static function types()
    {
        return [
            'annual' => __('tr.employee_leaves.types.annual'),
            'sick' => __('tr.employee_leaves.types.sick'),
            'unpaid' => __('tr.employee_leaves.types.unpaid'),
            'emergency' => __('tr.employee_leaves.types.emergency'),
            'other' => __('tr.employee_leaves.types.other'),
        ];
    }

    public static function statuses()
    {
        return [
            'pending' => __('tr.employee_leaves.statuses.pending'),
            'approved' => __('tr.employee_leaves.statuses.approved'),
            'rejected' => __('tr.employee_leaves.statuses.rejected'),
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
