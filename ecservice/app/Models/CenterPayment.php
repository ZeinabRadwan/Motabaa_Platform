<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\StoresNamedFilePath;

class CenterPayment extends Model
{
    use HasFactory, SoftDeletes, StoresNamedFilePath;
    protected $table = 'centers_payments';

    protected $fillable = [
        'center_id',
        'user_id',
        'name',
        'email',
        'phone_number',
        'amount',
        'package_id',
        'payment_type',
        'payment_duration',
        'date',
        'expiry_date',
        'status'
    ];

    const STATUS_UNPAID = 0;
    const STATUS_PAID = 1;

    const PAYMENT_TYPE_YEARLY   = 1;
    const PAYMENT_TYPE_MONTHLY  = 2;

    public static function statusLabels() {
        return [
            self::STATUS_PAID => __("tr.centers.Paid"),
            self::STATUS_UNPAID => __("tr.centers.Unpaid"),
        ];
    }

    public function center() {
        return $this->belongsTo(Center::class, 'center_id');
    }

    public function package() {
        return $this->belongsTo(CenterPackage::class, 'package_id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function storagePath(){

        $path = "centers";
        if(!usesBunnyStorage())
            $path = "public/centers";

        return $path."/center_{$this->center_id}/payments";
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
