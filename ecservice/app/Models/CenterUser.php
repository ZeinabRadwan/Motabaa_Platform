<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CenterUser extends Model
{
    use HasFactory;
    protected $table = 'centers_users';
    public $timestamps = false;

    protected $fillable = [
        'center_id',
        'user_id'
    ];

    public function center() {
        return $this->belongsTo(Center::class, 'center_id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
