<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;

class SCaseFee extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'scases_fees';
    
    protected $fillable = [
        'case_id', 
        'term_id', 
        'amount', 
        'notes', 
        'created_by'
    ];

    const STATUS_UNPAID = 0;
    const STATUS_PAID = 1;

    public static function statusLabels() {
        return [
            self::STATUS_UNPAID => __("tr.payments.Paid"),
            self::STATUS_PAID => __("tr.payments.Unpaid"),
        ];
    }

    public function case() {
        return $this->belongsTo(SCase::class, 'case_id');
    }

    public function term() {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function payments()
    {
        return $this->hasMany(SCasePayment::class, 'case_fee_id');
    }

    public function services()
    {
        $locale = App::getLocale();
        return $this->hasMany(ScaseFeeService::class, 'case_fee_id', 'id')
        ->join('services', 'services.id', 'scases_fees_services.service')
        ->select('services.id', 'scases_fees_services.case_fee_id', 'services.name_'.$locale.' as name');
    }

    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function servicesNames() {
        $services = "";
        $locale = App::getLocale();
        foreach ($this->services as $value) {
            $services .= $value->name.", ";
        }
        $services = rtrim($services, ', ');
        return $services;
    }

    public function paidPayments() {
        if (array_key_exists('paid_payments_sum', $this->attributes)) {
            return (float) ($this->attributes['paid_payments_sum'] ?? 0);
        }

        return (float) SCasePayment::where('scase_id', $this->case_id)
        ->where('term_id', $this->term_id)
        ->where('case_fee_id', $this->id)
        ->where('status', SCasePayment::STATUS_PAID)
        ->sum('amount');
    }
}
