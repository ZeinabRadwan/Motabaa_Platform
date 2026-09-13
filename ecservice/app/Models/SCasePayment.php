<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Models\Concerns\StoresNamedFilePath;

class SCasePayment extends Model
{
    use HasFactory, StoresNamedFilePath;

    protected $table = 'scases_payments';
    
    protected $fillable = [
        'scase_id', 
        'term_id', 
        'batch', 
        'amount', 
        'status', 
        'due_date', 
        'payment_date'
    ];

    const STATUS_UNPAID = 0;
    const STATUS_PAID = 1;

    const METHOD_CASH = 1;
    const METHOD_CHEQUE = 2;
    const METHOD_TRANSFER = 3;

    public static function statusLabels() {
        return [
            self::STATUS_PAID => __("tr.payments.Paid"),
            self::STATUS_UNPAID => __("tr.payments.Unpaid"),
        ];
    }

    public static function batchsList() {
        return [
            1 => 'First Batch',
            2 => 'Second Batch',
            3 => 'Third Batch',
            4 => 'Fourth Batch',
            5 => 'Fifth Batch'
        ];
    }

    public static function methodLables() {
        return [
            self::METHOD_CASH => __("tr.payments.cash"),
            self::METHOD_CHEQUE => __("tr.payments.cheque"),
            self::METHOD_TRANSFER => __("tr.payments.transfer"),
        ];
    }

    public function scase() {
        return $this->belongsTo(SCase::class, 'scase_id');
    }

    public function term() {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function caseFee() {
        return $this->belongsTo(SCaseFee::class, 'case_fee_id');
    }

    public function createdBy() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function batchName() {
        return self::batchsList()[$this->batch];
    }

    public function storagePath(){

        $path = "cases";
        if(!usesBunnyStorage())
            $path = "public/child_case";

        return $path."/{$this->scase_id}/payments";
    }

    public function saveFile($file) {

        $fileName = "Payment_{$this->id}.".$file->getClientOriginalExtension();
        saveFile($this->storagePath(), $fileName, $file);
        $this->persistStoredNamedPath('file_path', $fileName);
    }

    public function fileURL() {
        
        $fileName = "Payment_{$this->id}";
        $files = resolveStoredNamedFile($this->storagePath(), $fileName, $this->file_path);
        return $files ? $files['file_url'] : null;
    }

    public function deleteFile() {

        $fileName = "Payment_{$this->id}";
        deleteFile($this->storagePath(), $fileName);
        $this->persistStoredNamedPath('file_path', '');
    }

    protected function storedNamedFileColumns(): array
    {
        return ['file_path'];
    }
}
