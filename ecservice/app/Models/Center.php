<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\System\System;
use Carbon\Carbon;
use App\Models\Concerns\StoresNamedFilePath;

class Center extends Model
{
    use HasFactory, SoftDeletes, StoresNamedFilePath;

    protected $memoizedCenterData = [];

    protected $fillable = [
        'title',
        'title_local',
        'number_of_cases',
        'country',
        'city',
        'phone',
        'package_id',
        'commission',
        'cr_number',
        'vat_number',
        'email',
        'url',
        'status'
    ];

    const PAYMENT_TYPE_YEARLY   = 1;
    const PAYMENT_TYPE_MONTHLY  = 2;

    public static function typesLabels() {
        return [
            self::PAYMENT_TYPE_YEARLY => __("tr.centers.yearly"),
            self::PAYMENT_TYPE_MONTHLY => __("tr.centers.monthly"),
        ];
    }

    public function getNameAttribute() {

        $locale = App::getLocale();
        if ($locale === 'en' && $this->title_local != null){
            return $this->title_local;
        }
        return $this->title;
    }

    public function package() {
        return $this->belongsTo(CenterPackage::class, 'package_id');
    }

    public function users() {
        return $this->belongsToMany(User::class, 'centers_users', 'center_id', 'user_id');
    }

    public function payments() {
        return $this->hasMany(CenterPayment::class, 'center_id');
    }

    public function centerUsers() {
        return $this->hasMany(CenterUser::class, 'center_id');
    }

    public function managers() {
        if (array_key_exists('managers', $this->memoizedCenterData)) {
            return $this->memoizedCenterData['managers'];
        }

        static $managersByCenter = [];
        if (array_key_exists($this->id, $managersByCenter)) {
            return $this->memoizedCenterData['managers'] = $managersByCenter[$this->id];
        }

        $managersByCenter[$this->id] = $this->centerUsers()
        ->join('users', 'users.id', 'centers_users.user_id')
        ->whereHas('user.roles', function ($query) {
            $query->where('default_name', 'manager');
        })
        ->orderBy('centers_users.id', 'ASC')
        ->pluck('users.name', 'centers_users.user_id')
        ->toArray();

        return $this->memoizedCenterData['managers'] = $managersByCenter[$this->id];
    }

    public function cases() {
        return $this->hasMany(SCase::class, 'center_id');
    }

    public function centersStoragePath($from=null) {

        $path = "";
        if(!usesBunnyStorage($from))
            $path = "public/";

        return $path."centers";
    }

    public function storagePath($from=null) {
        return $this->centersStoragePath($from)."/center_{$this->id}";
    }

    public function setLogo($file, $currentImage=null) {

        deleteFile($this->storagePath(), "logo");
        $fileName = "logo.".$file->getClientOriginalExtension();
        saveFile($this->storagePath(), $fileName, $file, $currentImage);
        $this->memoizedCenterData = [];
        $this->persistStoredNamedPath('logo_path', $fileName);
    }

    public function urlLogo() {

        if (array_key_exists('urlLogo', $this->memoizedCenterData)) {
            return $this->memoizedCenterData['urlLogo'];
        }

        static $logoByCenter = [];
        if (array_key_exists($this->id, $logoByCenter)) {
            return $this->memoizedCenterData['urlLogo'] = $logoByCenter[$this->id];
        }

        $file = $this->logo_path === ''
            ? null
            : resolveStoredNamedFile($this->storagePath(), "logo", $this->logo_path);
        if(!isset($file['file_url']))
            $file = fetchFiles($this->centersStoragePath(), "motabaah");

        return $this->memoizedCenterData['urlLogo'] = $logoByCenter[$this->id] = $file;
    }

    public function platformURLLogo() {
        return fetchFiles($this->centersStoragePath(), "motabaah");
    }

    public function deleteLogo() {
        deleteFile($this->storagePath(), "logo");
        $this->memoizedCenterData = [];
        $this->persistStoredNamedPath('logo_path', '');
    }

    protected function storedNamedFileColumns(): array
    {
        return ['logo_path'];
    }

    public function countryName() {
        return __("countries.{$this->country}");
    }

    public function storageSize() {
        return 0;
    }

    public function statistics() {

        $center = $this;
        $statistics = (object)[];
        $statistics->goals_daily = Message::whereHas('goal.case', function ($query) use ($center) {
            $query->where('scases.center_id', $center->id);
        })
        ->whereRaw('created_at > DATE_ADD(NOW(), INTERVAL -14 DAY)')
        ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, DAY(created_at) as day, count(*) as count')
        ->whereNotNull('goal_id')
        ->groupByRaw('YEAR(created_at), MONTH(created_at), DAY(created_at)')
        ->get()
        ->toArray();

        $statistics->goals_monthly = Message::whereHas('goal.case', function ($query) use ($center) {
            $query->where('scases.center_id', $center->id);
        })
        ->whereRaw('created_at > DATE_ADD(NOW(), INTERVAL -12 MONTH)')
        ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, count(*) as count')
        ->whereNotNull('goal_id')
        ->groupByRaw('YEAR(created_at), MONTH(created_at)')
        ->get()
        ->toArray();

        $statistics->whats_app_daily = Log::where('center_id', $center->id)
        ->where('description', 'like', "%url%")
        ->whereRaw('created_at > DATE_ADD(NOW(), INTERVAL -14 DAY)')
        ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, DAY(created_at) as day, count(*) as count')
        ->groupByRaw('YEAR(created_at), MONTH(created_at), DAY(created_at)')
        ->get()
        ->toArray();

        $statistics->whats_app_monthly = Log::where('center_id', $center->id)
        ->where('description', 'like', "%url%")
        ->whereRaw('created_at > DATE_ADD(NOW(), INTERVAL -12 MONTH)')
        ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, count(*) as count')
        ->groupByRaw('YEAR(created_at), MONTH(created_at)')
        ->get()
        ->toArray();

        $statistics->whats_app_messages = Log::where('center_id', $center->id)
        ->where('description', 'like', "%url%")
        ->count();

        return $statistics;
    }

    public function lastPayment() {

        $lastPayment = $this->latestPaymentRow();
        if($lastPayment) {

            $now = Carbon::now();
            $lastPaymentDate = Carbon::parse($lastPayment->date);
            $lastPaymentEndDate = Carbon::parse($lastPayment->expiry_date);

            if($now->between($lastPaymentDate, $lastPaymentEndDate))
                return $lastPayment;
        }
        return null;
    }

    public function nextSubscription() {

        $lastPayment = $this->latestPaymentRow();
        if(!$lastPayment) return '';

        $lastPaymentEndDate = Carbon::parse($lastPayment->expiry_date);
        return $lastPaymentEndDate->addDay()->toDateString();
    }

    protected function latestPaymentRow()
    {
        if (array_key_exists('latestPaymentRow', $this->memoizedCenterData)) {
            return $this->memoizedCenterData['latestPaymentRow'];
        }

        if ($this->relationLoaded('payments')) {
            $payment = $this->payments->sort(function ($a, $b) {
                $expiry = strcmp((string) $b->expiry_date, (string) $a->expiry_date);
                if ($expiry !== 0) {
                    return $expiry;
                }

                return strcmp((string) $a->created_at, (string) $b->created_at);
            })->first();
        } else {
            $payment = $this->payments()->orderBy('expiry_date', 'DESC')->orderBy('created_at', 'ASC')->first();
        }

        $this->memoizedCenterData['latestPaymentRow'] = $payment;

        return $payment;
    }

    public function currentPaymentSum() {

        $amount = 0;
        $lastPayment = $this->lastPayment();
        if($lastPayment) {

            $lastPaymentDate = Carbon::parse($lastPayment->date);
            $lastPaymentEndDate = Carbon::parse($lastPayment->expiry_date);
            $amount = $this->payments()->whereBetween('date', [$lastPaymentDate, $lastPaymentEndDate])->sum('amount');
        }
        return $amount;
    }

    public function paymentAfterDiscount($data) {

        $package = CenterPackage::find($data->selected_package);
        $package->is_changed = false;
        $package->current_package = CenterPackage::find($this->package_id);
        $package->current_package->date = null;
        $package->current_package->expiry_date = null;

        $numberOfCases = 50;
        $paymentType = self::PAYMENT_TYPE_MONTHLY;
        $paymentDuration = 1;

        if($this->number_of_cases > 0)
            $numberOfCases = $this->number_of_cases;

        $now = Carbon::now();
        $lastPayment = $this->payments()->orderBy('expiry_date', 'DESC')->orderBy('created_at', 'ASC')->first();
        if($lastPayment) {

            $paymentType = $lastPayment->payment_type;
            $paymentDuration = $lastPayment->payment_duration;

            if($now->between($lastPayment->date, $lastPayment->expiry_date)) {

                $package->current_package->date = $lastPayment->date;
                $package->current_package->expiry_date = $lastPayment->expiry_date;
            }
        }

        $package->current_package->number_of_cases = $numberOfCases;
        $package->current_package->payment_type = $paymentType;
        $package->current_package->payment_type_name = self::typesLabels()[$paymentType];
        $package->current_package->payment_duration = $paymentDuration;

        if($data->selected_package != $this->package_id && $lastPayment) {
            $package->is_changed = true;
        }

        $package->amount_for_every_case = 0;
        $originalAmount = 0;
        $packageAmount = 0;
        if($data->payment_type == self::PAYMENT_TYPE_YEARLY) {
            $packageAmount = $package->yearly_amount;
        }
        else if($data->payment_type == self::PAYMENT_TYPE_MONTHLY) {
            $packageAmount = $package->monthly_amount;
        }

        if($data->payment_duration > 1)
            $packageAmount *= $data->payment_duration;

        $package->amount_for_every_case = $packageAmount;

        
        if($data->number_of_cases > 0) {

            $numberOfCases = $data->number_of_cases;
            $packageAmount *= $data->number_of_cases;
        }

        if($packageAmount > 0) {

            $originalAmount = $packageAmount;
            $discount = Discount::where('number_of_cases', '<=', $numberOfCases)->orderBy('id', 'DESC')->first();
            if(!$package->is_changed) {
                if($discount) {
                    $packageAmount -= ($packageAmount * $discount->percentage) / 100;
                }
                $package->amount_for_every_case = $packageAmount;
            }
            else {

                if($discount) {
                    $packageAmount -= ($packageAmount * $discount->percentage) / 100;
                }
                $package->amount_for_every_case = $packageAmount;

                $amount = $this->currentPaymentSum();
                if($packageAmount > $amount)
                    $packageAmount -= $amount;
                else
                    $packageAmount = 0;
            }
        }

        if($numberOfCases > 0)
            $package->amount_for_every_case /= $numberOfCases;

        if($data->payment_type > 0)
            $paymentType = $data->payment_type;

        if($data->payment_duration > 0)
            $paymentDuration = $data->payment_duration;

        $package->amount = round($packageAmount, 2);
        $package->original_amount = round($originalAmount, 2);
        $package->amount_for_every_case = round($package->amount_for_every_case, 2);
        $package->number_of_cases = $numberOfCases;
        $package->payment_type = $paymentType;
        $package->payment_duration = $paymentDuration;

        return $package;
    }

    public function canPay() {

        $lastPayment = $this->lastPayment();
        if(!$lastPayment)
            return true;

        $now = Carbon::now();
        $lastPaymentDate = Carbon::parse($lastPayment->date);
        $lastPaymentEndDate = Carbon::parse($lastPayment->expiry_date);

        if($now->diffInDays($lastPaymentEndDate)<=5)
            return true;

        if(!$now->between($lastPaymentDate, $lastPaymentEndDate))
            return true;

        return false;
    }

    public function isPaid() {

        $now = Carbon::now();
        if(!$this->paid_service)
            return true;

        if($this->package->identifier == 'trial') {
            if($now->diffInDays($this->created_at) <= 14)
                return true;
            return false;
        }

        if($this->lastPayment())
            return true;

        return false;
    }

    public function paymentRecord() {

        $lastPayment = $this->lastPayment();
        if(!$lastPayment) {

            $payment = new CenterPayment();
            $payment->center_id = $this->id;
            $payment->user_id = 0;
            $payment->name = '';
            $payment->email = '';
            $payment->phone_number = '';
            $payment->amount = $this->package->monthly_amount;
            $payment->package_id = $this->package->id * $this->number_of_cases;
            $payment->date = Carbon::now();
            $payment->status = 0;
            $payment->save();
        }

        return 'Added Successfully';
    }
}
