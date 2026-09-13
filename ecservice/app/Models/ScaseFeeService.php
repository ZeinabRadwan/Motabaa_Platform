<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScaseFeeService extends Model
{
    use HasFactory;

    protected $table = 'scases_fees_services';
    public $timestamps = false;
    
    protected $fillable = [
        'id',
        'case_fee_id',
        'service',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service');
    }
}
