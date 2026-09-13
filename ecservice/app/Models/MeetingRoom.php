<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;

class MeetingRoom extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'center_id',
        'created_by_id',
        'title',
        'title_local',
        'type',
    ];

    protected $appends = ['name'];

    const TYPE_GENERAL = 1;
    const TYPE_ADMINISTRATIVE = 2;
    
    public function getNameAttribute()
    {
        $locale = App::getLocale();
        if ($locale === 'en' && $this->title_local != null){
            return $this->title_local;
        }
        return $this->title;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public static function types()
    {
        return [
            self::TYPE_GENERAL => __('tr.general'),
            self::TYPE_ADMINISTRATIVE => __('tr.administrative'),
        ];
    }

    public function typeName()
    {
        $types = self::types();
        if(array_key_exists($this->type, $types))
            return $types[$this->type];

        return '';
    }
}
