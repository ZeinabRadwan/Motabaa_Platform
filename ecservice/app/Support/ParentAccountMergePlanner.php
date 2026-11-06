<?php

namespace App\Support;

use App\Models\System\System;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detects duplicate parent groups (by normalized email and/or phone, confirmed by normalized name)
 * and chooses merge survivors. Read-only; ParentAccountMerger performs the writes.
 */
class ParentAccountMergePlanner
{
    public const STATUS_WOULD_MERGE = 'would_merge';

    public const STATUS_SKIPPED = 'skipped';

    public const MATCH_EMAIL_AND_PHONE = 'email_and_phone';

    public const MATCH_EMAIL_ONLY = 'email_only_phone_differs';

    public const MATCH_PHONE_ONLY = 'phone_only_email_differs';

    public const MATCH_MIXED = 'mixed';

    public const MATCH_NAME_ONLY = 'name_only_review';

    public const MATCH_SOURCE_PRIMARY = 'primary_contact';

    public const MATCH_SOURCE_ADDITIONAL_PHONE = 'additional_phone';

    public const PHONE_SOURCE_PRIMARY = 'primary_phone';

    public const PHONE_SOURCE_ADDITIONAL = 'additional_phone';

    /** Skip reasons an admin may override with --approve after confirming identity manually. */
    private const APPROVABLE_SKIP_REASONS = ['name_mismatch', 'no_shared_email_or_phone', 'additional_phone_match_requires_approval'];

    /**
     * @param  array<int, string>  $approvedGroupKeys  Admin-reviewed group keys (e.g. "253-268") allowed to override name_mismatch only.
     * @return array<string, mixed>
     */
    public static function plan(bool $applyMode = false, array $approvedGroupKeys = []): array
    {
        $parents = self::loadEligibleParents();
        $ids = $parents->pluck('id')->map(fn ($id) => (int) $id)->all();

        $groups = self::buildGroups(
            $parents,
            self::linkedStudentIdsByUser($ids),
            self::centerCountsByUser($ids),
            $approvedGroupKeys,
            [self::class, 'databaseContactChecks']
        );

        $mergeGroups = array_values(array_filter($groups, fn ($g) => $g['merge_status'] === self::STATUS_WOULD_MERGE));
        $skippedGroups = array_values(array_filter($groups, fn ($g) => $g['merge_status'] === self::STATUS_SKIPPED));

        return [
            'generated_at' => now()->toIso8601String(),
            'mode' => $applyMode ? 'apply' : 'dry_run',
            'parent_account_count' => $parents->count(),
            'phone_verification_available' => User::hasPhoneNumbersTable(),
            'merge_groups' => $mergeGroups,
            'skipped_groups' => $skippedGroups,
            'summary' => [
                'would_merge' => count($mergeGroups),
                'skipped' => count($skippedGroups),
                'accounts_affected' => array_sum(array_map(fn ($g) => count($g['merged_account_ids']), $mergeGroups)),
            ],
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public static function loadEligibleParents(): Collection
    {
        return ParentIdentity::scopeParentOnlyAccounts(User::query())
            ->with(['roles:id,name,default_name'])
            ->when(User::hasPhoneNumbersTable(), fn ($q) => $q->with([
                'phoneNumbers' => fn ($numbers) => $numbers->whereNotNull('verified_at'),
            ]))
            ->whereNull('merged_into_user_id')
            ->orderBy('id')
            ->get([
                'id', 'name', 'email', 'phone', 'status', 'password', 'email_verified_at',
                'id_or_residence_number', 'deleted_at', 'merged_into_user_id',
            ]);
    }

    /**
     * Pure grouping/evaluation (no DB access except through $contactChecks).
     *
     * @param  Collection<int, User>  $parents
     * @param  array<int, array<int, int>>  $linkedByUser
     * @param  array<int, int>  $centerCounts
     * @param  array<int, string>  $approvedGroupKeys
     * @param  callable(Collection<int, User>, array<int, int>): array<int, string>  $contactChecks  Returns extra skip reasons.
     * @return array<int, array<string, mixed>>
     */
    public static function buildGroups(
        Collection $parents,
        array $linkedByUser,
        array $centerCounts,
        array $approvedGroupKeys,
        callable $contactChecks
    ): array {
        $approved = array_map([self::class, 'normalizeGroupKey'], $approvedGroupKeys);
        $groups = [];
        $contactGroupKeys = [];

        foreach (self::linkedComponents($parents) as $component) {
            $componentIds = self::idsOf($component);
            $componentKey = implode('-', $componentIds);

            if (in_array($componentKey, $approved, true)) {
                $groups[] = self::evaluate($component, $component, $linkedByUser, $centerCounts, $contactChecks, true);
                $contactGroupKeys[] = $componentKey;

                continue;
            }

            $produced = false;
            $byName = $component->groupBy(fn (User $u) => ParentIdentity::normalizeFullName($u->name) ?? '#'.$u->id);
            foreach ($byName as $sameName) {
                if ($sameName->count() < 2) {
                    continue;
                }
                foreach (self::linkedComponents($sameName->values()) as $candidate) {
                    $groups[] = self::evaluate($candidate, $component, $linkedByUser, $centerCounts, $contactChecks, false);
                    $contactGroupKeys[] = implode('-', self::idsOf($candidate));
                    $produced = true;
                }
            }

            if (! $produced) {
                $groups[] = self::evaluate($component, $component, $linkedByUser, $centerCounts, $contactChecks, false);
            }
        }

        foreach (self::nameOnlyGroups($parents, $contactGroupKeys) as $sameName) {
            $key = implode('-', self::idsOf($sameName));
            $groups[] = self::evaluate(
                $sameName,
                $sameName,
                $linkedByUser,
                $centerCounts,
                $contactChecks,
                in_array($key, $approved, true),
                true
            );
        }

        return $groups;
    }

    /**
     * Accounts with the same normalized full name (3+ name parts) that are not already fully covered by
     * one email/phone group. Review-only: these never qualify for automatic merging.
     *
     * @param  Collection<int, User>  $parents
     * @param  array<int, string>  $contactGroupKeys
     * @return array<int, Collection<int, User>>
     */
    private static function nameOnlyGroups(Collection $parents, array $contactGroupKeys): array
    {
        $result = [];
        $byName = $parents->groupBy(fn (User $u) => ParentIdentity::normalizeFullName($u->name) ?? '');
        foreach ($byName as $name => $users) {
            if ($name === '' || $users->count() < 2 || count(preg_split('/\s+/u', (string) $name)) < 3) {
                continue;
            }

            $ids = self::idsOf($users);
            $coveredByOneGroup = false;
            foreach ($contactGroupKeys as $key) {
                if (array_diff($ids, explode('-', $key)) === []) {
                    $coveredByOneGroup = true;

                    break;
                }
            }

            if (! $coveredByOneGroup) {
                $result[] = $users->sortBy('id')->values();
            }
        }

        return $result;
    }

    /**
     * True when two emails share a domain and their local parts differ by exactly one character edit
     * (e.g. "latyn5555" vs "latyn555"). Review hint only — they are still different addresses.
     */
    public static function emailsDifferByOneCharacter(?string $a, ?string $b): bool
    {
        $a = ParentIdentity::emailMatchKey($a);
        $b = ParentIdentity::emailMatchKey($b);
        if ($a === null || $b === null || $a === $b || ! str_contains($a, '@') || ! str_contains($b, '@')) {
            return false;
        }

        [$localA, $domainA] = explode('@', $a, 2);
        [$localB, $domainB] = explode('@', $b, 2);

        return $domainA === $domainB && levenshtein($localA, $localB) === 1;
    }

    /**
     * Phone keys of one account: its login phone (users.phone) plus every VERIFIED additional number
     * (only when the phoneNumbers relation is loaded). Unverified numbers never link accounts.
     *
     * @return array<string, string> Canonical phone => PHONE_SOURCE_PRIMARY|PHONE_SOURCE_ADDITIONAL
     */
    public static function phoneKeysOf(User $user, bool $includeAdditional = true): array
    {
        $keys = [];
        if ($primary = ParentIdentity::canonicalPhone($user->phone)) {
            $keys[$primary] = self::PHONE_SOURCE_PRIMARY;
        }
        if (! $includeAdditional || ! $user->relationLoaded('phoneNumbers')) {
            return $keys;
        }

        foreach ($user->phoneNumbers as $number) {
            if (empty($number->verified_at)) {
                continue;
            }
            $phone = ParentIdentity::canonicalPhone($number->phone);
            if ($phone !== null && ! isset($keys[$phone])) {
                $keys[$phone] = self::PHONE_SOURCE_ADDITIONAL;
            }
        }

        return $keys;
    }

    /**
     * Accounts connected through a shared normalized email or canonical phone (login phone or verified
     * additional number; pass $includeAdditional = false to link on login phones only).
     *
     * @param  Collection<int, User>  $users
     * @return array<int, Collection<int, User>>
     */
    public static function linkedComponents(Collection $users, bool $includeAdditional = true): array
    {
        $parent = [];
        $find = function (int $id) use (&$parent, &$find): int {
            if ($parent[$id] !== $id) {
                $parent[$id] = $find($parent[$id]);
            }

            return $parent[$id];
        };

        $byKey = [];
        foreach ($users as $user) {
            $id = (int) $user->id;
            $parent[$id] = $id;
            if ($email = ParentIdentity::emailMatchKey($user->email)) {
                $byKey['e:'.$email][] = $id;
            }
            foreach (array_keys(self::phoneKeysOf($user, $includeAdditional)) as $phone) {
                $byKey['p:'.$phone][] = $id;
            }
        }

        foreach ($byKey as $ids) {
            for ($i = 1; $i < count($ids); $i++) {
                $parent[$find($ids[$i])] = $find($ids[0]);
            }
        }

        $buckets = [];
        foreach ($users as $user) {
            $buckets[$find((int) $user->id)][] = $user;
        }

        $components = [];
        foreach ($buckets as $members) {
            if (count($members) >= 2) {
                $components[] = collect($members)->sortBy('id')->values();
            }
        }

        usort($components, fn ($a, $b) => (int) $a->first()->id <=> (int) $b->first()->id);

        return $components;
    }

    /**
     * @param  Collection<int, User>  $group
     * @param  Collection<int, User>  $component  Full linked component (for family-shared contact notes)
     * @param  array<int, array<int, int>>  $linkedByUser
     * @param  array<int, int>  $centerCounts
     * @return array<string, mixed>
     */
    private static function evaluate(
        Collection $group,
        Collection $component,
        array $linkedByUser,
        array $centerCounts,
        callable $contactChecks,
        bool $adminApproved,
        bool $nameOnly = false
    ): array {
        $userIds = self::idsOf($group);
        $componentIds = self::idsOf($component);
        $reasons = [];
        $skipReasons = [];
        $reviewNotes = [];

        foreach ($group as $user) {
            if (! isParentUser($user)) {
                $skipReasons[] = 'non_parent_role:user_'.$user->id;
            }
        }

        $nameKeys = $group->map(fn (User $u) => ParentIdentity::normalizeFullName($u->name))->filter()->unique()->values();
        if ($nameKeys->count() === 1 && $group->every(fn (User $u) => ParentIdentity::normalizeFullName($u->name) !== null)) {
            $reasons[] = 'matching_normalized_name';
        } else {
            $skipReasons[] = 'name_mismatch';
            $names = $group->pluck('name')->values();
            $allSubset = true;
            for ($i = 1; $i < $names->count(); $i++) {
                $same = ParentIdentity::normalizeFullName($names[0]) === ParentIdentity::normalizeFullName($names[$i]);
                if (! $same && ! ParentIdentity::namesDifferOnlyByMiddleParts($names[0], $names[$i])) {
                    $allSubset = false;
                }
            }
            if ($allSubset) {
                $reviewNotes[] = 'names_differ_only_by_middle_parts_likely_same_person';
            }
        }

        $idKeys = $group->map(fn (User $u) => ParentIdentity::normalizeIdOrResidence($u->id_or_residence_number))->filter()->unique();
        if ($idKeys->count() > 1) {
            $skipReasons[] = 'conflicting_id_or_residence_number';
        } elseif ($idKeys->count() === 1 && $group->every(fn (User $u) => ParentIdentity::normalizeIdOrResidence($u->id_or_residence_number) !== null)) {
            $reasons[] = 'matching_id_or_residence_number';
        }

        $emailDetails = $group->map(fn (User $u) => [
            'user_id' => (int) $u->id,
            'email' => $u->email,
            'normalized_email' => ParentIdentity::emailMatchKey($u->email),
            'email_valid' => ParentIdentity::normalizeEmail($u->email) !== null,
            'email_verified' => ! empty($u->email_verified_at),
            'invisible_characters' => ParentIdentity::invisibleCharacters($u->email),
        ])->values()->all();

        $phoneDetails = $group->map(fn (User $u) => [
            'user_id' => (int) $u->id,
            'phone' => $u->phone,
            'normalized_phone' => ParentIdentity::canonicalPhone($u->phone),
            'verified_additional_phones' => self::additionalPhonesOf($u),
        ])->values()->all();

        $emails = array_column($emailDetails, 'normalized_email');
        $phones = array_column($phoneDetails, 'normalized_phone');
        $sharedEmail = self::singleSharedValue($emails);
        $sharedPhone = self::singleSharedValue($phones);
        $emailDiffers = $sharedEmail === null;
        $phoneDiffers = $sharedPhone === null;

        $phoneMatchSource = $sharedPhone !== null ? self::PHONE_SOURCE_PRIMARY : null;
        if ($sharedPhone === null && ($viaAdditional = self::phoneSharedByAll($group)) !== null) {
            $sharedPhone = $viaAdditional;
            $phoneMatchSource = self::PHONE_SOURCE_ADDITIONAL;
        }

        $matchSource = self::MATCH_SOURCE_PRIMARY;
        if (! $nameOnly && ! self::connectedWithoutAdditionalPhones($group)) {
            $matchSource = self::MATCH_SOURCE_ADDITIONAL_PHONE;
            $skipReasons[] = 'additional_phone_match_requires_approval';
            $reviewNotes[] = 'linked_via_verified_additional_phone';
        }

        $matchType = match (true) {
            $nameOnly => self::MATCH_NAME_ONLY,
            $sharedEmail !== null && $sharedPhone !== null => self::MATCH_EMAIL_AND_PHONE,
            $sharedEmail !== null => self::MATCH_EMAIL_ONLY,
            $sharedPhone !== null => self::MATCH_PHONE_ONLY,
            default => self::MATCH_MIXED,
        };

        if ($sharedEmail !== null) {
            $reasons[] = 'shared_normalized_email:'.$sharedEmail;
        }
        if ($sharedPhone !== null) {
            $reasons[] = 'shared_normalized_phone:'.$sharedPhone;
        }
        if ($emailDiffers) {
            $reviewNotes[] = 'email_difference_flagged_for_review';
        }
        if ($phoneDiffers) {
            $reviewNotes[] = 'phone_difference_flagged_for_review';
        }
        foreach ($emailDetails as $detail) {
            if ($detail['invisible_characters'] !== []) {
                $reviewNotes[] = 'invisible_characters_in_email:user_'.$detail['user_id'];
            }
            if ($detail['email'] !== null && $detail['email'] !== '' && ! $detail['email_valid']) {
                $reviewNotes[] = 'email_fails_validation:user_'.$detail['user_id'];
            }
        }

        foreach ($component as $other) {
            if (! in_array((int) $other->id, $userIds, true)) {
                $reviewNotes[] = 'shares_contact_with_different_name:user_'.$other->id;
            }
        }

        if ($nameOnly) {
            $skipReasons[] = 'no_shared_email_or_phone';
            $groupUsers = $group->values();
            for ($i = 0; $i < $groupUsers->count(); $i++) {
                for ($j = $i + 1; $j < $groupUsers->count(); $j++) {
                    if (self::emailsDifferByOneCharacter($groupUsers[$i]->email, $groupUsers[$j]->email)) {
                        $reviewNotes[] = sprintf('emails_differ_by_one_character:user_%d,user_%d', $groupUsers[$i]->id, $groupUsers[$j]->id);
                    }
                }
            }
        }

        $skipReasons = array_merge($skipReasons, $contactChecks($group, $componentIds));

        $skipReasons = array_values(array_unique($skipReasons));
        if ($adminApproved && $skipReasons !== [] && array_diff($skipReasons, self::APPROVABLE_SKIP_REASONS) === []) {
            $reasons[] = 'admin_approved_override:'.implode(',', $skipReasons);
            $skipReasons = [];
        }

        $eligible = $skipReasons === [];
        $primaryId = self::choosePrimaryUserId($group, $linkedByUser, $centerCounts);

        $studentsBefore = [];
        foreach ($userIds as $uid) {
            $studentsBefore[$uid] = $linkedByUser[$uid] ?? [];
        }
        $allCaseIds = collect($studentsBefore)->flatten()->unique()->sort()->values()->all();
        $distinctSets = collect($studentsBefore)->filter()->map(fn ($c) => implode(',', $c))->unique()->count();

        return [
            'group_key' => implode('-', $userIds),
            'match_type' => $matchType,
            'confidence' => match (true) {
                ! $eligible => 'review',
                $adminApproved => 'admin_approved',
                $matchType === self::MATCH_EMAIL_AND_PHONE => 'high_strong',
                default => 'high',
            },
            'normalized_email' => $sharedEmail,
            'normalized_phone' => $sharedPhone,
            'phone_match_source' => $phoneMatchSource,
            'match_source' => $matchSource,
            'normalized_name' => $nameKeys->count() === 1 ? $nameKeys->first() : null,
            'user_ids' => $userIds,
            'primary_account_id' => $primaryId,
            'merged_account_ids' => array_values(array_filter($userIds, fn ($id) => $id !== $primaryId)),
            'confidence_reasons' => $reasons,
            'skip_reasons' => $skipReasons,
            'review_notes' => array_values(array_unique($reviewNotes)),
            'admin_approved' => $adminApproved,
            'email_details' => $emailDetails,
            'email_differs' => $emailDiffers,
            'phone_details' => $phoneDetails,
            'phone_differs' => $phoneDiffers,
            'phone_kept' => ParentIdentity::canonicalPhone($group->firstWhere('id', $primaryId)?->phone),
            'linked_students_before' => $studentsBefore,
            'linked_students_differ' => $distinctSets > 1,
            'linked_students_after_primary' => $allCaseIds,
            'merge_status' => $eligible ? self::STATUS_WOULD_MERGE : self::STATUS_SKIPPED,
            'users' => $group->map(fn (User $u) => [
                'id' => (int) $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'verified_additional_phones' => self::additionalPhonesOf($u),
                'status' => $u->status,
                'linked_student_count' => count($linkedByUser[(int) $u->id] ?? []),
            ])->values()->all(),
        ];
    }

    /**
     * Staff collisions and contacts owned by accounts outside the linked component.
     *
     * @param  Collection<int, User>  $group
     * @param  array<int, int>  $componentIds
     * @return array<int, string>
     */
    public static function databaseContactChecks(Collection $group, array $componentIds): array
    {
        $reasons = [];
        foreach ($group as $user) {
            if (ParentAccountResolver::nonParentUsersMatchingContact($user->email, $user->phone)->isNotEmpty()) {
                $reasons[] = 'staff_collision:user_'.$user->id;
            }
            foreach (self::additionalPhonesOf($user) as $additional) {
                if (ParentAccountResolver::nonParentUsersMatchingContact(null, $additional)->isNotEmpty()) {
                    $reasons[] = 'staff_collision:user_'.$user->id;
                }
            }

            $email = ParentIdentity::normalizeEmail($user->email);
            if ($email !== null) {
                $outside = User::query()
                    ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                    ->whereNotIn('id', $componentIds)
                    ->whereNull('merged_into_user_id')
                    ->exists();
                if ($outside) {
                    $reasons[] = 'email_owned_by_outside_account:user_'.$user->id;
                }
            }
        }

        return $reasons;
    }

    public static function normalizeGroupKey(string $key): string
    {
        $ids = array_map('intval', preg_split('/[^0-9]+/', $key, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        sort($ids);

        return implode('-', $ids);
    }

    /**
     * Survivor: most linked students, then most centers, then can-login, then has password, then lowest id.
     *
     * @param  Collection<int, User>  $groupUsers
     * @param  array<int, array<int, int>>  $linkedByUser
     * @param  array<int, int>  $centerCounts
     */
    public static function choosePrimaryUserId(Collection $groupUsers, array $linkedByUser, array $centerCounts): int
    {
        $ranked = $groupUsers->sort(function (User $a, User $b) use ($linkedByUser, $centerCounts) {
            $casesA = count($linkedByUser[(int) $a->id] ?? []);
            $casesB = count($linkedByUser[(int) $b->id] ?? []);
            if ($casesA !== $casesB) {
                return $casesB <=> $casesA;
            }

            $centersA = $centerCounts[(int) $a->id] ?? 0;
            $centersB = $centerCounts[(int) $b->id] ?? 0;
            if ($centersA !== $centersB) {
                return $centersB <=> $centersA;
            }

            $loginA = (int) $a->status === User::STATUS_CAN_LOGIN ? 1 : 0;
            $loginB = (int) $b->status === User::STATUS_CAN_LOGIN ? 1 : 0;
            if ($loginA !== $loginB) {
                return $loginB <=> $loginA;
            }

            $passA = $a->password ? 1 : 0;
            $passB = $b->password ? 1 : 0;
            if ($passA !== $passB) {
                return $passB <=> $passA;
            }

            return (int) $a->id <=> (int) $b->id;
        })->values();

        return (int) $ranked->first()->id;
    }

    /**
     * @return array<int, string>
     */
    private static function additionalPhonesOf(User $user): array
    {
        $phones = [];
        foreach (self::phoneKeysOf($user) as $phone => $source) {
            if ($source === self::PHONE_SOURCE_ADDITIONAL) {
                $phones[] = (string) $phone;
            }
        }

        return $phones;
    }

    /**
     * A phone key (login or verified additional) held by every member of the group, if any.
     *
     * @param  Collection<int, User>  $group
     */
    private static function phoneSharedByAll(Collection $group): ?string
    {
        $shared = null;
        foreach ($group as $user) {
            $keys = array_map('strval', array_keys(self::phoneKeysOf($user)));
            $shared = $shared === null ? $keys : array_values(array_intersect($shared, $keys));
            if ($shared === []) {
                return null;
            }
        }
        if ($shared === null) {
            return null;
        }
        sort($shared);

        return $shared[0];
    }

    /**
     * True when the group stays one linked component using only emails and login phones, i.e. the match
     * does not depend on an additional number.
     *
     * @param  Collection<int, User>  $group
     */
    private static function connectedWithoutAdditionalPhones(Collection $group): bool
    {
        $components = self::linkedComponents($group->values(), false);

        return count($components) === 1 && $components[0]->count() === $group->count();
    }

    /**
     * @param  array<int, string|null>  $values
     */
    private static function singleSharedValue(array $values): ?string
    {
        if ($values === [] || in_array(null, $values, true)) {
            return null;
        }

        $unique = array_unique($values);

        return count($unique) === 1 ? (string) reset($unique) : null;
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, int>
     */
    private static function idsOf(Collection $users): array
    {
        $ids = $users->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($ids);

        return $ids;
    }

    /**
     * @param  array<int, int>  $parentIds
     * @return array<int, array<int, int>>
     */
    public static function linkedStudentIdsByUser(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $map = [];
        foreach (DB::table('scase_user')
            ->where('relationship_type', System::USER_TYPE_PARENT)
            ->whereIn('user_id', $parentIds)
            ->get(['user_id', 'scase_id']) as $row) {
            $map[(int) $row->user_id][] = (int) $row->scase_id;
        }

        foreach ($map as $uid => $cases) {
            $cases = array_values(array_unique($cases));
            sort($cases);
            $map[$uid] = $cases;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $parentIds
     * @return array<int, int>
     */
    public static function centerCountsByUser(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $map = [];
        foreach (DB::table('centers_users')
            ->whereIn('user_id', $parentIds)
            ->selectRaw('user_id, COUNT(*) as c')
            ->groupBy('user_id')
            ->get() as $row) {
            $map[(int) $row->user_id] = (int) $row->c;
        }

        return $map;
    }
}
