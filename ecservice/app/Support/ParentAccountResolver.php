<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserPhoneNumber;
use Illuminate\Support\Collection;

/**
 * Resolves whether a new parent signup should reuse an existing parent-only account.
 */
class ParentAccountResolver
{
    public const REASON_EMAIL = 'normalized_email';

    public const REASON_PHONE = 'normalized_phone';

    public const REASON_EMAIL_AND_PHONE = 'email_and_phone';

    /**
     * @return array{
     *   existing_user_id: int|null,
     *   conflict: bool,
     *   conflict_reason: string|null,
     *   email_user_ids: array<int, int>,
     *   phone_user_ids: array<int, int>,
     *   match_reason: string|null
     * }
     */
    public static function resolveForContact(?string $email, ?string $phone, ?int $excludeUserId = null): array
    {
        $emailUsers = self::parentUsersByEmail($email, $excludeUserId);
        $phoneUsers = self::parentUsersByPhone($phone, $excludeUserId);

        $emailIds = $emailUsers->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $phoneIds = $phoneUsers->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $emailUnique = count($emailIds) === 1 ? $emailIds[0] : null;
        $phoneUnique = count($phoneIds) === 1 ? $phoneIds[0] : null;

        if (count($emailIds) > 1) {
            return self::result(null, true, 'multiple_accounts_share_email', $emailIds, $phoneIds, null);
        }

        if (count($phoneIds) > 1) {
            return self::result(null, true, 'multiple_accounts_share_phone', $emailIds, $phoneIds, null);
        }

        if ($emailUnique !== null && $phoneUnique !== null && $emailUnique !== $phoneUnique) {
            return self::result(null, true, 'email_and_phone_point_to_different_accounts', $emailIds, $phoneIds, null);
        }

        if ($emailUnique !== null && $phoneUnique !== null && $emailUnique === $phoneUnique) {
            return self::result($emailUnique, false, null, $emailIds, $phoneIds, self::REASON_EMAIL_AND_PHONE);
        }

        if ($emailUnique !== null) {
            return self::result($emailUnique, false, null, $emailIds, $phoneIds, self::REASON_EMAIL);
        }

        if ($phoneUnique !== null) {
            return self::result($phoneUnique, false, null, $emailIds, $phoneIds, self::REASON_PHONE);
        }

        return self::result(null, false, null, $emailIds, $phoneIds, null);
    }

    /**
     * @return Collection<int, User>
     */
    public static function parentUsersByEmail(?string $email, ?int $excludeUserId = null): Collection
    {
        $normalized = ParentIdentity::normalizeEmail($email);
        if ($normalized === null) {
            return collect();
        }

        $query = ParentIdentity::scopeParentOnlyAccounts(User::query())
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalized]);

        if ($excludeUserId) {
            $query->where('users.id', '!=', $excludeUserId);
        }

        return $query->get(['users.id', 'users.name', 'users.email', 'users.phone']);
    }

    /**
     * @return Collection<int, User>
     */
    public static function parentUsersByPhone(?string $phone, ?int $excludeUserId = null): Collection
    {
        $candidates = LoginIdentifier::phoneMatchCandidates((string) ($phone ?? ''));
        if ($candidates === []) {
            return collect();
        }

        $verified = User::hasPhoneNumbersTable() ? ParentPhoneNumberRules::normalize($phone) : null;

        $query = ParentIdentity::scopeParentOnlyAccounts(User::query())
            ->where(function ($q) use ($candidates, $verified) {
                $q->whereIn('users.phone', $candidates);
                if ($verified !== null) {
                    $q->orWhereIn('users.id', UserPhoneNumber::query()
                        ->select('user_id')
                        ->where('phone', $verified)
                        ->whereNotNull('verified_at'));
                }
            });

        if ($excludeUserId) {
            $query->where('users.id', '!=', $excludeUserId);
        }

        return $query->get(['users.id', 'users.name', 'users.email', 'users.phone']);
    }

    public static function findReusableParentForNewCase(?string $email, ?string $phone): ?User
    {
        $resolution = self::resolveForContact($email, $phone);
        if ($resolution['conflict'] || ! $resolution['existing_user_id']) {
            return null;
        }

        return User::find($resolution['existing_user_id']);
    }

    /**
     * Staff or mixed-role accounts that own the same contact (must not be reused as parent).
     *
     * @return Collection<int, User>
     */
    public static function nonParentUsersMatchingContact(?string $email, ?string $phone): Collection
    {
        $matched = collect();

        $normalizedEmail = ParentIdentity::normalizeEmail($email);
        if ($normalizedEmail !== null) {
            $byEmail = User::query()
                ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
                ->get(['id', 'name', 'email', 'phone']);

            foreach ($byEmail as $user) {
                if (! isParentUser($user)) {
                    $matched->push($user);
                }
            }
        }

        $candidates = LoginIdentifier::phoneMatchCandidates((string) ($phone ?? ''));
        if ($candidates !== []) {
            $byPhone = User::query()
                ->whereIn('phone', $candidates)
                ->get(['id', 'name', 'email', 'phone']);

            foreach ($byPhone as $user) {
                if (! isParentUser($user) && ! $matched->contains('id', $user->id)) {
                    $matched->push($user);
                }
            }
        }

        return $matched->values();
    }

    /**
     * @param  array<int, int>  $emailIds
     * @param  array<int, int>  $phoneIds
     * @return array{
     *   existing_user_id: int|null,
     *   conflict: bool,
     *   conflict_reason: string|null,
     *   email_user_ids: array<int, int>,
     *   phone_user_ids: array<int, int>,
     *   match_reason: string|null
     * }
     */
    private static function result(
        ?int $existingUserId,
        bool $conflict,
        ?string $conflictReason,
        array $emailIds,
        array $phoneIds,
        ?string $matchReason
    ): array {
        return [
            'existing_user_id' => $existingUserId,
            'conflict' => $conflict,
            'conflict_reason' => $conflictReason,
            'email_user_ids' => $emailIds,
            'phone_user_ids' => $phoneIds,
            'match_reason' => $matchReason,
        ];
    }
}
