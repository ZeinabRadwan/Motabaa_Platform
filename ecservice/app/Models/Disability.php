<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class Disability extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_en',
        'name_ar',
    ];

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
        return $this->belongsToMany(ChildCase::class, 'scase_disability', 'disability_id', 'scase_id');
    }
}
