<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class QuestionnaireTask extends Model
{
    use HasFactory, SoftDeletes;

    public const AVAILABILITY_TIMEZONE = 'Asia/Riyadh';

    public const AUDIENCE_PARENT = 'parent';

    public const AUDIENCE_ROLE = 'role';

    protected $table = 'questionnaires_tasks';
    
    protected $fillable = [
        'questionnaire_id',
        'term_id',
        'starts_at',
        'ends_at',
        'target_audience_type',
        'target_role_id',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function questionnaire() {
        return $this->belongsTo(Questionnaire::class, 'questionnaire_id');
    }

    public function term() {
        return $this->belongsTo(Term::class, 'term_id');
    }

    public function targetRole()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'target_role_id');
    }

    public function isParentAudience(): bool
    {
        return $this->normalizeAudienceType($this->target_audience_type) === self::AUDIENCE_PARENT;
    }

    public function isRoleAudience(): bool
    {
        return $this->normalizeAudienceType($this->target_audience_type) === self::AUDIENCE_ROLE;
    }

    public function normalizeAudienceType(?string $value): string
    {
        return $value === self::AUDIENCE_ROLE ? self::AUDIENCE_ROLE : self::AUDIENCE_PARENT;
    }

    public function allowsParentEditAfterSubmit(): bool
    {
        $this->loadMissing('questionnaire');

        return (bool) ($this->questionnaire?->allow_parent_edit_after_submit ?? false);
    }

    /**
     * Open when today's calendar date (Saudi) falls within starts_at..ends_at inclusive.
     * Uses raw DATE values from the database (no datetime / timezone drift).
     */
    public function isOpen(): bool
    {
        $window = $this->availabilityWindow();
        if ($window['starts_at'] === null || $window['ends_at'] === null) {
            return false;
        }

        if ($window['starts_at'] > $window['ends_at']) {
            return false;
        }

        return $window['today'] >= $window['starts_at'] && $window['today'] <= $window['ends_at'];
    }

    /**
     * @return array{starts_at: ?string, ends_at: ?string, today: string, timezone: string}
     */
    public function availabilityWindow(): array
    {
        return [
            'starts_at' => $this->availabilityDateString($this->getRawOriginal('starts_at') ?? $this->starts_at),
            'ends_at' => $this->availabilityDateString($this->getRawOriginal('ends_at') ?? $this->ends_at),
            'today' => Carbon::now(self::AVAILABILITY_TIMEZONE)->format('Y-m-d'),
            'timezone' => self::AVAILABILITY_TIMEZONE,
        ];
    }

    private function availabilityDateString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        $raw = trim((string) $value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $matches)) {
            return $matches[1];
        }

        try {
            return Carbon::parse($raw, self::AVAILABILITY_TIMEZONE)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 12-hour clock with localized morning/evening label (e.g. Arabic صباحاً / مساءً).
     */
    public static function formatSubmissionDisplayTime(Carbon $moment): string
    {
        $moment = $moment->copy()->timezone(self::AVAILABILITY_TIMEZONE);
        $isPm = (int) $moment->format('G') >= 12;
        $clock = $moment->format('g:i');
        $period = $isPm
            ? __('tr.questionnaires.submission_time_pm')
            : __('tr.questionnaires.submission_time_am');

        return $clock.' '.$period;
    }
}
