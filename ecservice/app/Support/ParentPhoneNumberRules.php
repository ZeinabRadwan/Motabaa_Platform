<?php

namespace App\Support;

/**
 * Pure decision rules for parent phone numbers (no DB access). ParentPhoneNumberService gathers the
 * data, calls these rules inside a transaction, and performs the writes.
 *
 * Number entries are plain arrays: ['id' => int|null, 'phone' => string, 'is_primary' => bool, 'verified' => bool].
 * Owner entries are plain arrays: ['user_id' => int, 'is_parent' => bool, 'source' => 'account_phone'|'verified_number'].
 */
class ParentPhoneNumberRules
{
    public const ERR_INVALID = 'invalid_phone';

    public const ERR_DUPLICATE = 'duplicate_in_account';

    public const ERR_CONFLICT = 'owned_by_other_account';

    public const ERR_STAFF_COLLISION = 'staff_collision';

    public const ERR_PRIMARY_REQUIRES_VERIFICATION = 'primary_requires_verification';

    public const ERR_CANNOT_REMOVE_PRIMARY = 'cannot_remove_primary';

    public const ERR_CANNOT_UNVERIFY_PRIMARY = 'cannot_unverify_primary';

    public const ERR_CANNOT_EDIT_PRIMARY_PHONE = 'cannot_edit_primary_phone';

    public const ERR_NOT_PARENT = 'not_parent_account';

    public const BACKFILL_CREATE = 'create_primary';

    public const BACKFILL_PROMOTE = 'promote_existing_row';

    public const BACKFILL_SKIP_HAS_PRIMARY = 'skip_has_primary';

    public const BACKFILL_SKIP_EMPTY = 'skip_empty_phone';

    public const BACKFILL_SKIP_PLACEHOLDER = 'skip_merged_placeholder';

    public const BACKFILL_SKIP_INVALID = 'skip_invalid_phone';

    public const BACKFILL_SKIP_CONFLICT = 'skip_verified_elsewhere';

    /**
     * Canonical Saudi mobile (9665XXXXXXXX) or null when the input is not a valid Saudi mobile.
     * Only Saudi mobiles are accepted because login, WhatsApp and duplicate detection all use that form.
     */
    public static function normalize(?string $phone): ?string
    {
        $canonical = ParentIdentity::canonicalPhone($phone);

        return $canonical !== null && preg_match('/^9665\d{8}$/', $canonical) === 1 ? $canonical : null;
    }

    public static function isMergedPlaceholder(?string $phone): bool
    {
        return $phone !== null && preg_match('/^999\d{9}$/', trim($phone)) === 1;
    }

    /**
     * @param  array<int, string>  $accountPhones  Normalized numbers already on this account (rows + users.phone)
     * @param  array<int, array{user_id: int, is_parent: bool, source: string}>  $otherOwners
     */
    public static function addError(?string $normalized, array $accountPhones, array $otherOwners): ?string
    {
        if ($normalized === null) {
            return self::ERR_INVALID;
        }
        if (in_array($normalized, $accountPhones, true)) {
            return self::ERR_DUPLICATE;
        }

        return self::ownerConflict($otherOwners);
    }

    /**
     * A number held by a staff/mixed-role account is a staff collision (same semantics as parent create);
     * held by any other account (login phone or verified number) is a conflict.
     *
     * @param  array<int, array{user_id: int, is_parent: bool, source: string}>  $otherOwners
     */
    public static function ownerConflict(array $otherOwners): ?string
    {
        if ($otherOwners === []) {
            return null;
        }
        foreach ($otherOwners as $owner) {
            if (! $owner['is_parent']) {
                return self::ERR_STAFF_COLLISION;
            }
        }

        return self::ERR_CONFLICT;
    }

    /**
     * A primary number must be verified. An unverified number can only be promoted when the admin
     * explicitly verifies it in the same action ($markVerified).
     *
     * @param  array{is_primary: bool, verified: bool}  $entry
     */
    public static function setPrimaryError(array $entry, bool $markVerified): ?string
    {
        if (! $entry['verified'] && ! $markVerified) {
            return self::ERR_PRIMARY_REQUIRES_VERIFICATION;
        }

        return null;
    }

    /**
     * @param  array{is_primary: bool}  $entry
     */
    public static function deleteError(array $entry): ?string
    {
        return $entry['is_primary'] ? self::ERR_CANNOT_REMOVE_PRIMARY : null;
    }

    /**
     * @param  array{is_primary: bool}  $entry
     */
    public static function unverifyError(array $entry): ?string
    {
        return $entry['is_primary'] ? self::ERR_CANNOT_UNVERIFY_PRIMARY : null;
    }

    /**
     * Changing the digits of the primary would silently change the login identifier; use set-primary instead.
     *
     * @param  array{phone: string, is_primary: bool}  $entry
     */
    public static function updateError(array $entry, ?string $newNormalized, array $accountPhones, array $otherOwners): ?string
    {
        if ($newNormalized === null) {
            return self::ERR_INVALID;
        }
        if ($newNormalized === $entry['phone']) {
            return null;
        }
        if ($entry['is_primary']) {
            return self::ERR_CANNOT_EDIT_PRIMARY_PHONE;
        }

        return self::addError($newNormalized, array_values(array_diff($accountPhones, [$entry['phone']])), $otherOwners);
    }

    /**
     * Returns the entries with exactly one primary (the target). The target must already be verified.
     *
     * @param  array<int, array{id: int|null, phone: string, is_primary: bool, verified: bool}>  $entries
     * @return array<int, array{id: int|null, phone: string, is_primary: bool, verified: bool}>
     */
    public static function withPrimary(array $entries, int $targetId): array
    {
        $found = false;
        foreach ($entries as $i => $entry) {
            $isTarget = (int) $entry['id'] === $targetId;
            $found = $found || $isTarget;
            $entries[$i]['is_primary'] = $isTarget;
        }
        if (! $found) {
            throw new \InvalidArgumentException("Phone number {$targetId} does not belong to this account.");
        }

        return $entries;
    }

    /**
     * @param  array<int, array{is_primary: bool}>  $entries
     */
    public static function primaryCount(array $entries): int
    {
        return count(array_filter($entries, fn ($e) => (bool) $e['is_primary']));
    }

    /**
     * Which of a merged-away duplicate's numbers move to the surviving account. Moved numbers always arrive
     * unverified and non-primary: the survivor keeps its own credentials and login phone.
     *
     * @param  array<int, array{phone: string|null, label?: string|null}>  $duplicateNumbers
     * @param  array<int, string>  $primaryPhones  Normalized numbers already on the survivor
     * @param  array<string, bool>  $ownedElsewhere  Normalized number => true when held by an account outside the merge
     * @return array{transfer: array<int, array{phone: string, label: string|null}>, skipped: array<int, array{phone: string|null, reason: string}>}
     */
    public static function mergeTransferPlan(array $duplicateNumbers, array $primaryPhones, array $ownedElsewhere): array
    {
        $transfer = [];
        $skipped = [];
        $seen = array_fill_keys($primaryPhones, true);

        foreach ($duplicateNumbers as $number) {
            $raw = $number['phone'] ?? null;
            if (self::isMergedPlaceholder($raw)) {
                $skipped[] = ['phone' => $raw, 'reason' => 'merged_placeholder'];

                continue;
            }
            $normalized = self::normalize($raw);
            if ($normalized === null) {
                $skipped[] = ['phone' => $raw, 'reason' => 'invalid_phone'];

                continue;
            }
            if (isset($seen[$normalized])) {
                $skipped[] = ['phone' => $normalized, 'reason' => in_array($normalized, $primaryPhones, true) ? 'already_on_primary' : 'duplicate_in_batch'];

                continue;
            }
            if (! empty($ownedElsewhere[$normalized])) {
                $skipped[] = ['phone' => $normalized, 'reason' => 'owned_by_other_account'];

                continue;
            }

            $seen[$normalized] = true;
            $transfer[] = ['phone' => $normalized, 'label' => $number['label'] ?? null];
        }

        return ['transfer' => $transfer, 'skipped' => $skipped];
    }

    /**
     * Backfill decision for one parent's legacy users.phone.
     *
     * @param  array<int, array{phone: string, is_primary: bool}>  $existingRows
     */
    public static function backfillDecision(?string $accountPhone, array $existingRows, bool $verifiedElsewhere): string
    {
        if (self::primaryCount($existingRows) > 0) {
            return self::BACKFILL_SKIP_HAS_PRIMARY;
        }
        if ($accountPhone === null || trim($accountPhone) === '') {
            return self::BACKFILL_SKIP_EMPTY;
        }
        if (self::isMergedPlaceholder($accountPhone)) {
            return self::BACKFILL_SKIP_PLACEHOLDER;
        }
        $normalized = self::normalize($accountPhone);
        if ($normalized === null) {
            return self::BACKFILL_SKIP_INVALID;
        }
        if ($verifiedElsewhere) {
            return self::BACKFILL_SKIP_CONFLICT;
        }
        foreach ($existingRows as $row) {
            if ($row['phone'] === $normalized) {
                return self::BACKFILL_PROMOTE;
            }
        }

        return self::BACKFILL_CREATE;
    }
}
