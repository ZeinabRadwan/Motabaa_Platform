<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFileMeta extends Model
{
    use HasFactory;

    protected $table = 'user_file_meta';

    protected $fillable = [
        'user_id',
        'file_name',
        'file_extension',
        'document_type',
        'expiry_date',
    ];

    protected $casts = [
        'expiry_date' => 'date',
    ];

    public static function types()
    {
        return [
            'id' => __('tr.employee_documents.types.id'),
            'passport' => __('tr.employee_documents.types.passport'),
            'contract' => __('tr.employee_documents.types.contract'),
            'medical' => __('tr.employee_documents.types.medical'),
            'other' => __('tr.employee_documents.types.other'),
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
