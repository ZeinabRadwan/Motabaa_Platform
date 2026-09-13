<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;


class Term extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'title',
        'starts_at',
        'ends_at',
        'periods_count',
        'assign_all_users',
        'first_evaluation_at',
        'second_evaluation_at',
        'third_evaluation_at',
        'final_evaluation_at',
    ];

    protected $casts = [
        'assign_all_users' => 'boolean',
    ];

    protected $resolvedAssignedUsersCache = false;

    public function periods()
    {
        return $this->hasMany(TermPeriod::class)->orderBy('period_index');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'term_user');
    }

    public static function hasUserAssignmentTable(): bool
    {
        static $cached = false;
        if ($cached) {
            return true;
        }

        $cached = Schema::hasTable('term_user');

        return $cached;
    }

    public static function hasAssignAllUsersColumn(): bool
    {
        static $cached = null;
        if ($cached === null) {
            $cached = Schema::hasColumn((new static)->getTable(), 'assign_all_users');
        }

        return $cached;
    }

    public function assignsAllUsers(): bool
    {
        return self::hasAssignAllUsersColumn() && (bool) $this->assign_all_users;
    }

    public static function assignableStaffQuery($center)
    {
        return User::query()
            ->where('status', User::STATUS_CAN_LOGIN)
            ->whereHas('centers', function ($query) use ($center) {
                $query->where('centers.id', $center);
            })
            ->whereDoesntHave('roles', function ($query) {
                $query->where('default_name', 'parent');
            });
    }

    public static function userIsAssignableStaff($user, $center): bool
    {
        if (!$user || !$center) {
            return false;
        }

        return self::assignableStaffQuery($center)->where('users.id', $user->id)->exists();
    }

    public function resolveAssignedUsers()
    {
        if ($this->resolvedAssignedUsersCache !== false) {
            return $this->resolvedAssignedUsersCache;
        }

        if ($this->assignsAllUsers()) {
            $this->resolvedAssignedUsersCache = self::assignableStaffQuery($this->center_id)
                ->with('roles:id,name,default_name')
                ->orderBy('users.name')
                ->get();

            return $this->resolvedAssignedUsersCache;
        }

        $this->resolvedAssignedUsersCache = $this->relationLoaded('users') ? $this->users : collect();

        return $this->resolvedAssignedUsersCache;
    }

    public static function hasPeriodsTable(): bool
    {
        static $cached = null;
        if ($cached === null) {
            $cached = Schema::hasTable('term_periods');
        }

        return $cached;
    }

    public static function hasPeriodsCountColumn(): bool
    {
        static $cached = null;
        if ($cached === null) {
            $cached = Schema::hasColumn((new static)->getTable(), 'periods_count');
        }

        return $cached;
    }

    public static function withPeriods()
    {
        $query = static::query();
        if (self::hasPeriodsTable()) {
            $query->with('periods');
        }

        return $query;
    }

    public function periodsCount(): int
    {
        if (self::hasPeriodsCountColumn()) {
            $count = (int) $this->periods_count;
            if ($count > 0) {
                return $count;
            }
        }

        if (self::hasPeriodsTable()) {
            $related = $this->relationLoaded('periods') ? $this->periods->count() : $this->periods()->count();
            if ($related > 0) {
                return $related;
            }
        }

        $legacy = count(array_filter([
            $this->first_evaluation_at,
            $this->second_evaluation_at,
            $this->third_evaluation_at,
            $this->final_evaluation_at,
        ]));

        return $legacy > 0 ? $legacy : 4;
    }

    public function lastPeriodIndex(): int
    {
        return max($this->periodsCount() - 1, 0);
    }

    public function evaluationDates(): array
    {
        $dates = [];
        if (self::hasPeriodsTable()) {
            if ($this->relationLoaded('periods') && $this->periods->count()) {
                $dates = $this->periods->pluck('evaluation_at')->all();
            } else {
                $dates = $this->periods()->pluck('evaluation_at')->filter()->values()->all();
            }
        }

        if (!count($dates)) {
            $dates = array_filter([
                $this->first_evaluation_at,
                $this->second_evaluation_at,
                $this->third_evaluation_at,
                $this->final_evaluation_at,
            ]);
        }

        return array_values(array_filter(array_map(function ($date) {
            if ($date instanceof \DateTimeInterface) {
                return $date->format('Y-m-d');
            }

            return $date ? (string) $date : null;
        }, $dates)));
    }

    public function periodDate(int $index): ?string
    {
        $dates = $this->evaluationDates();
        $date = $dates[$index] ?? null;
        if ($date) {
            return (string) $date;
        }

        return $this->ends_at;
    }

    public function previousPeriodDate(int $index): ?string
    {
        if ($index <= 0) {
            return $this->starts_at;
        }

        return $this->periodDate($index - 1);
    }

    public function syncPeriods(array $dates): void
    {
        $dates = array_values(array_filter($dates, fn ($date) => !empty($date)));

        if (self::hasPeriodsTable()) {
            $this->periods()->delete();

            foreach ($dates as $index => $date) {
                $this->periods()->create([
                    'period_index' => $index,
                    'evaluation_at' => $date,
                ]);
            }
        }

        if (self::hasPeriodsCountColumn()) {
            $this->periods_count = max(count($dates), 1);
        }

        $this->first_evaluation_at = $dates[0] ?? null;
        $this->second_evaluation_at = $dates[1] ?? null;
        $this->third_evaluation_at = $dates[2] ?? null;
        $this->final_evaluation_at = $dates[count($dates) - 1] ?? null;
        $this->save();
    }

    public function periodTitle($index): string
    {
        $index = (int) $index;
        if ($index === $this->lastPeriodIndex()) {
            return __('tr.goals.final_period');
        }

        return __('tr.goals.period_n', ['n' => $index + 1]);
    }

    public function getNameAttribute()
    {
        $locale = App::getLocale();
        if ($locale === 'en' && $this->title_local != null){
            return $this->title_local;
        }
        return $this->title;
    }

    public static function overlapping($centerId, $startsAt, $endsAt, $ignoreId = 0)
    {
        return self::query()
            ->where('center_id', $centerId)
            ->when($ignoreId > 0, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereDate('starts_at', '<', $endsAt)
            ->whereDate('ends_at', '>', $startsAt)
            ->orderBy('starts_at')
            ->first();
    }

    public static function canViewPastTerms($user = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user) {
            return false;
        }

        return isHasRole('admin', $user);
    }

    public static function isCenterManager($user = null, $centerId = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user || !isHasRole('manager', $user)) {
            return false;
        }

        if ($centerId) {
            return $user->isInCenter($centerId);
        }

        return true;
    }

    public static function canManageAllCenterTerms($user = null, $centerId = null): bool
    {
        $user = $user ?: auth()->user();
        if (!$user) {
            return false;
        }

        if (self::canViewPastTerms($user)) {
            return true;
        }

        return self::isCenterManager($user, $centerId);
    }

    public static function userIsAssignedToTerm($user, $termId, $term = null): bool
    {
        if (!$user || !$termId || !self::hasUserAssignmentTable()) {
            return false;
        }

        if ($user->terms()->where('term_user.term_id', $termId)->exists()) {
            return true;
        }

        $term = $term ?: self::withTrashed()->find($termId);

        return $term
            && $term->assignsAllUsers()
            && self::userIsAssignableStaff($user, $term->center_id);
    }

    public static function userCanAccessTerm($user, $centerId, $termId): bool
    {
        $user = $user ?: auth()->user();
        if (!$user || !$termId) {
            return false;
        }

        return self::requestMemo(
            'motabaa.term_access',
            ($user->id ?? 'guest').':'.($centerId ?? 'none').':'.$termId,
            fn () => self::resolveUserCanAccessTerm($user, $centerId, $termId)
        );
    }

    protected static function resolveUserCanAccessTerm($user, $centerId, $termId): bool
    {

        $term = self::withTrashed()->find($termId);
        if (!$term) {
            return false;
        }

        if ($centerId && (int) $term->center_id !== (int) $centerId && !self::canViewPastTerms($user)) {
            return false;
        }

        if (self::canManageAllCenterTerms($user, $term->center_id)) {
            return true;
        }

        if ($term->trashed()) {
            return false;
        }

        if (self::userIsAssignedToTerm($user, $term->id, $term)) {
            return true;
        }

        if (isHasRole('parent', $user)) {
            $current = self::currentForCenter($term->center_id);

            return $current && (int) $current->id === (int) $term->id;
        }

        return false;
    }

    public static function forbiddenResponse()
    {
        return response()->json(['errors' => ['error' => [__('validation.terms.Term not accessible')]]], 403);
    }

    /**
     * @return array{ok: bool, term_id: int|null}
     * term_id null = no extra filter (platform admin, all terms)
     * term_id 0 = no visible term, query should return empty
     */
    public static function visibleTermId($user, $centerId, $requestedTermId = null): array
    {
        $requested = ($requestedTermId !== null && $requestedTermId !== '')
            ? (int) $requestedTermId
            : null;

        return self::requestMemo(
            'motabaa.visible_term',
            ($user->id ?? 'guest').':'.($centerId ?? 'none').':'.($requested === null ? 'null' : $requested),
            fn () => self::resolveVisibleTermId($user, $centerId, $requested)
        );
    }

    protected static function resolveVisibleTermId($user, $centerId, $requested): array
    {

        if (self::canViewPastTerms($user)) {
            return ['ok' => true, 'term_id' => $requested];
        }

        if ($requested !== null) {
            if (!self::userCanAccessTerm($user, $centerId, $requested)) {
                return ['ok' => false, 'term_id' => null];
            }

            return ['ok' => true, 'term_id' => $requested];
        }

        $current = self::currentForCenter($centerId);
        if (!$current) {
            return ['ok' => true, 'term_id' => 0];
        }

        if (self::userCanAccessTerm($user, $centerId, $current->id)) {
            return ['ok' => true, 'term_id' => (int) $current->id];
        }

        return ['ok' => true, 'term_id' => 0];
    }

    public static function abortIfInaccessible($user, $centerId, $termId)
    {
        if ($termId === null || $termId === '' || (int) $termId === 0) {
            return null;
        }

        $result = self::visibleTermId($user, $centerId, $termId);
        if (!$result['ok']) {
            return self::forbiddenResponse();
        }

        return null;
    }

    public static function applyVisibleTerm($query, $user, $centerId, $requestedTermId = null, $column = 'term_id')
    {
        $result = self::visibleTermId($user, $centerId, $requestedTermId);
        if (!$result['ok']) {
            return false;
        }

        if ($result['term_id'] === 0) {
            $query->whereRaw('0 = 1');

            return true;
        }

        if ($result['term_id'] !== null) {
            $query->where($column, $result['term_id']);
        }

        return true;
    }

    public static function resolveCenterId($user, $requestedCenterId = null)
    {
        if ($requestedCenterId && ($user->isInCenter($requestedCenterId) || self::canViewPastTerms($user))) {
            return $requestedCenterId;
        }

        if ($user->centers && count($user->centers)) {
            return $user->centers[0]->id;
        }

        return $requestedCenterId ?: null;
    }

    public static function currentForCenter($centerId): ?self
    {
        if (!$centerId) {
            return null;
        }

        return self::requestMemo(
            'motabaa.current_term',
            (string) $centerId,
            function () use ($centerId) {
                $now = Carbon::now()->toDateString();

                return self::withPeriods()
                    ->where('center_id', $centerId)
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>=', $now)
                    ->orderByDesc('id')
                    ->first();
            }
        );
    }

    protected static function requestMemo(string $bag, string $key, callable $resolver)
    {
        try {
            $request = request();
        } catch (\Throwable $e) {
            return $resolver();
        }

        if (!$request) {
            return $resolver();
        }

        $store = $request->attributes->get($bag, []);
        if (array_key_exists($key, $store)) {
            return $store[$key];
        }

        $store[$key] = $resolver();
        $request->attributes->set($bag, $store);

        return $store[$key];
    }

    public static function currentTerm()
    {
        $now = Carbon::now()->toDateString();
        $currentTerm = self::withPeriods()->where('starts_at', '<=', $now)
        ->where('ends_at', '>=', $now)
        ->first();
        return $currentTerm;
    }

    public function scopeVisibleToUser($query, $user = null, $centerId = null)
    {
        $user = $user ?: auth()->user();
        if (self::canManageAllCenterTerms($user, $centerId)) {
            return $query;
        }

        if (!$user) {
            return $query->whereRaw('0 = 1');
        }

        if (isHasRole('parent', $user)) {
            $currentTerm = self::currentForCenter($centerId);
            if (!$currentTerm) {
                return $query->whereRaw('0 = 1');
            }

            return $query->where($query->getModel()->getTable().'.id', $currentTerm->id);
        }

        if (!self::hasUserAssignmentTable()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function ($assignedQuery) use ($user, $centerId) {
            $assignedQuery->whereHas('users', function ($assigned) use ($user) {
                $assigned->where('users.id', $user->id);
            });

            if (self::hasAssignAllUsersColumn() && self::userIsAssignableStaff($user, $centerId)) {
                $assignedQuery->orWhere('assign_all_users', true);
            }
        });
    }
}
