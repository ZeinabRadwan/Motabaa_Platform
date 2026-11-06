<?php

namespace App\Support;

use App\Models\System\System;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Read-only duplicate detection for parent-only accounts. Does not merge or delete.
 */
class ParentDuplicateReport
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $parents = ParentAccountMergePlanner::loadEligibleParents();
        $ids = $parents->pluck('id')->map(fn ($id) => (int) $id)->all();
        $linkedByUser = ParentAccountMergePlanner::linkedStudentIdsByUser($ids);

        $groups = ParentAccountMergePlanner::buildGroups(
            $parents,
            $linkedByUser,
            ParentAccountMergePlanner::centerCountsByUser($ids),
            [],
            [ParentAccountMergePlanner::class, 'databaseContactChecks']
        );

        $byType = fn (string $type) => array_values(array_filter($groups, fn ($g) => $g['match_type'] === $type));

        return [
            'generated_at' => now()->toIso8601String(),
            'parent_account_count' => $parents->count(),
            'filters_applied' => [
                'parent_only_roles' => 'every assigned role has default_name = parent',
                'soft_deleted_excluded' => true,
                'already_merged_excluded' => true,
                'email_normalization' => 'invisible/whitespace characters removed, validated, lowercased',
                'phone_normalization' => 'Saudi mobiles → 9665XXXXXXXX',
                'additional_phone_numbers' => 'verified additional numbers count as phone keys; unverified numbers are ignored; groups linked only through an additional number need --approve',
                'name_normalization' => 'Arabic letter variants, diacritics, "عبد ال" spacing, whitespace, case',
            ],
            'email_clusters' => self::clusters($parents, fn (User $u) => ParentIdentity::emailMatchKey($u->email), 'normalized_email', $linkedByUser),
            'phone_clusters' => self::clusters($parents, fn (User $u) => array_map('strval', array_keys(ParentAccountMergePlanner::phoneKeysOf($u))), 'phone_key', $linkedByUser),
            'linked_via_additional_phone' => array_values(array_filter($groups, fn ($g) => ($g['match_source'] ?? null) === ParentAccountMergePlanner::MATCH_SOURCE_ADDITIONAL_PHONE)),
            'email_match_phone_differs' => $byType(ParentAccountMergePlanner::MATCH_EMAIL_ONLY),
            'phone_match_email_differs' => $byType(ParentAccountMergePlanner::MATCH_PHONE_ONLY),
            'email_and_phone_match' => $byType(ParentAccountMergePlanner::MATCH_EMAIL_AND_PHONE),
            'same_name_no_shared_contact' => $byType(ParentAccountMergePlanner::MATCH_NAME_ONLY),
            'candidate_groups' => $groups,
            'suggested_review_groups' => $groups,
            'conflicts' => array_values(array_filter($groups, fn ($g) => $g['skip_reasons'] !== [])),
            'email_scan_diagnostics' => self::emailScanDiagnostics($ids),
        ];
    }

    /**
     * @param  Collection<int, User>  $parents
     * @param  callable(User): (string|array<int, string>|null)  $keyOf
     * @param  array<int, array<int, int>>  $linkedByUser
     * @return array<int, array<string, mixed>>
     */
    private static function clusters(Collection $parents, callable $keyOf, string $keyName, array $linkedByUser): array
    {
        $buckets = [];
        foreach ($parents as $user) {
            foreach ((array) $keyOf($user) as $key) {
                if ($key !== null && $key !== '') {
                    $buckets[$key][] = $user;
                }
            }
        }

        $payload = [];
        foreach ($buckets as $key => $users) {
            if (count($users) < 2) {
                continue;
            }
            $payload[] = [
                $keyName => $key,
                'user_ids' => array_map(fn (User $u) => (int) $u->id, $users),
                'users' => array_map(fn (User $u) => self::summarize($u, $linkedByUser), $users),
            ];
        }

        return $payload;
    }

    /**
     * Scans every user (any role, including soft-deleted) so an empty email_clusters list can be
     * explained: which same-email accounts were excluded, and which parent emails fail validation.
     *
     * @param  array<int, int>  $eligibleParentIds
     * @return array<string, mixed>
     */
    private static function emailScanDiagnostics(array $eligibleParentIds): array
    {
        $eligible = array_flip($eligibleParentIds);
        $all = User::withTrashed()
            ->with('roles:id,default_name')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['id', 'name', 'email', 'deleted_at', 'merged_into_user_id']);

        $buckets = [];
        $unparseable = [];
        foreach ($all as $user) {
            if (ParentIdentity::normalizeEmail($user->email) === null && isset($eligible[(int) $user->id])) {
                $unparseable[] = [
                    'user_id' => (int) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_hex' => bin2hex((string) $user->email),
                    'invisible_characters' => ParentIdentity::invisibleCharacters($user->email),
                ];
            }

            $key = ParentIdentity::emailMatchKey($user->email);
            if ($key !== null) {
                $buckets[$key][] = $user;
            }
        }

        $excludedClusters = [];
        foreach ($buckets as $key => $users) {
            if (count($users) < 2) {
                continue;
            }
            $excluded = array_filter($users, fn (User $u) => ! isset($eligible[(int) $u->id]));
            if ($excluded === []) {
                continue;
            }
            $excludedClusters[] = [
                'normalized_email' => $key,
                'users' => array_map(fn (User $u) => [
                    'id' => (int) $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'invisible_characters' => ParentIdentity::invisibleCharacters($u->email),
                    'included_in_parent_clusters' => isset($eligible[(int) $u->id]),
                    'excluded_reason' => self::exclusionReason($u, $eligible),
                ], $users),
            ];
        }

        return [
            'same_email_clusters_with_excluded_accounts' => $excludedClusters,
            'parent_emails_failing_validation' => $unparseable,
        ];
    }

    /**
     * @param  array<int, int>  $eligible
     */
    private static function exclusionReason(User $user, array $eligible): ?string
    {
        if (isset($eligible[(int) $user->id])) {
            return null;
        }
        if ($user->merged_into_user_id) {
            return 'already_merged_into_user_'.$user->merged_into_user_id;
        }
        if ($user->trashed()) {
            return 'soft_deleted';
        }

        $roles = $user->roles->pluck('default_name')->all();
        if ($roles === []) {
            return 'no_roles';
        }
        if (in_array(System::USER_TYPE_PARENT_ROLE_NAME, $roles, true)) {
            return 'parent_plus_other_roles:'.implode(',', array_map(fn ($r) => $r ?? 'custom', $roles));
        }

        return 'non_parent_roles:'.implode(',', array_map(fn ($r) => $r ?? 'custom', $roles));
    }

    /**
     * @param  array<int, array<int, int>>  $linkedByUser
     * @return array<string, mixed>
     */
    private static function summarize(User $user, array $linkedByUser): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'verified_additional_phones' => array_map('strval', array_keys(array_filter(
                ParentAccountMergePlanner::phoneKeysOf($user),
                fn ($source) => $source === ParentAccountMergePlanner::PHONE_SOURCE_ADDITIONAL
            ))),
            'status' => $user->status,
            'roles' => $user->relationLoaded('roles') ? $user->roles->pluck('default_name')->filter()->values()->all() : [],
            'linked_students' => $linkedByUser[(int) $user->id] ?? [],
        ];
    }
}
