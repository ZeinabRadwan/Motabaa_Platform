<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;

class MeetingRoom extends Model
{
    use HasFactory, SoftDeletes;

    public const AUDIENCE_GENERAL = 'general';

    public const AUDIENCE_PARENTS = 'parents';

    public const AUDIENCE_LINK_INVITE = 'link_invite';

    protected $fillable = [
        'center_id',
        'created_by_id',
        'title',
        'title_local',
        'type',
        'audience_type',
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

    public function assignedParents()
    {
        return $this->belongsToMany(User::class, 'meeting_room_parents', 'meeting_room_id', 'user_id')
            ->withTimestamps();
    }

    public function meetingUrl(): string
    {
        return 'https://meet.jit.si/tahiledu.com.'.$this->id.'?lang='.app()->getLocale();
    }

    public function audienceTypeName(): string
    {
        return match ($this->audience_type ?? self::AUDIENCE_GENERAL) {
            self::AUDIENCE_PARENTS => __('tr.meeting_rooms.audience_parents_label'),
            self::AUDIENCE_LINK_INVITE => __('tr.meeting_rooms.audience_link_invite_label'),
            default => __('tr.meeting_rooms.audience_general_label'),
        };
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
