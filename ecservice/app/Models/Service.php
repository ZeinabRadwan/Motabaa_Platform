<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class Service extends Model
{
    use HasFactory;


    protected $appends = ['name'];

    
    public function getNameAttribute()
    {
        $locale = App::getLocale();
        if ($locale === 'en' && $this->name_en != null){
            return $this->name_en;
        }
        return $this->name_ar;
    }


    public function cases()
    {
        return $this->belongsToMany(SCase::class, 'scase_service');
    }
}
