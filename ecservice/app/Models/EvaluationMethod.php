<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class EvaluationMethod extends Model
{
    use HasFactory;
    
    public $timestamps = false;

    protected $fillable = [
        'name',
        'name_local',
    ];

    protected $appends = ['title'];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {

            $items = json_decode($model->items);
            foreach ($items as $item) {
                
                $value = new EvaluationMethodValue();
                $value->evaluation_method_id = $model->id;
                $value->title = $item->title;
                $value->value = $item->value;
                $value->ability = $item->ability;
                $value->save();
            }
        });
    }

    
    public function getTitleAttribute()
    {
        $locale = App::getLocale();
        if ($locale === 'en' && $this->name_local != null){
            return $this->name_local;
        }
        return $this->name;
    }
}
