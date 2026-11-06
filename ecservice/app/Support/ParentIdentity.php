<?php

namespace App\Support;

use App\Models\System\System;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ParentIdentity
{
    /**
     * Invisible formatting marks (RLM/LRM, zero-width, BOM) and Unicode spaces often ride along when an
     * address is pasted from an RTL UI. They are not part of the address but make it fail validation.
     */
    private const INVISIBLE_PATTERN = '/[\p{Cf}\p{Z}\s]+/u';

    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $cleaned = self::stripInvisible($email);
        if ($cleaned === '') {
            return null;
        }

        $validated = LoginIdentifier::normalizedEmailOrNull($cleaned);
        if ($validated === null) {
            return null;
        }

        return mb_strtolower($validated);
    }

    /**
     * Key used to compare emails between accounts. Valid emails use normalizeEmail(); stored values that
     * fail validation still compare by their exact text (invisible characters removed, lowercased), so two
     * accounts holding the same malformed address are still grouped. Never fuzzy beyond that.
     */
    public static function emailMatchKey(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = self::normalizeEmail($email);
        if ($normalized !== null) {
            return $normalized;
        }

        $cleaned = mb_strtolower(self::stripInvisible($email));

        return $cleaned === '' ? null : 'invalid:'.$cleaned;
    }

    /**
     * Names that differ only by extra middle parts (e.g. a 4-part vs 3-part name with the same first and
     * family name). A review hint only; never sufficient for automatic merging.
     */
    public static function namesDifferOnlyByMiddleParts(?string $a, ?string $b): bool
    {
        $tokensA = preg_split('/\s+/u', (string) self::normalizeFullName($a), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokensB = preg_split('/\s+/u', (string) self::normalizeFullName($b), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokensA) < 2 || count($tokensB) < 2 || $tokensA === $tokensB) {
            return false;
        }

        [$short, $long] = count($tokensA) <= count($tokensB) ? [$tokensA, $tokensB] : [$tokensB, $tokensA];
        if ($short[0] !== $long[0] || end($short) !== end($long)) {
            return false;
        }

        $i = 0;
        foreach ($long as $token) {
            if ($i < count($short) && $token === $short[$i]) {
                $i++;
            }
        }

        return $i === count($short);
    }

    /**
     * @return array<int, string> Code points (e.g. "U+200F") of invisible characters found in the value.
     */
    public static function invisibleCharacters(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $trimmed = trim($value);
        preg_match_all('/[\p{Cf}\p{Z}\s]/u', $trimmed, $matches);

        $codes = [];
        foreach ($matches[0] as $char) {
            if ($char === ' ' && $trimmed !== '') {
                $codes[] = 'U+0020';

                continue;
            }
            $codes[] = sprintf('U+%04X', mb_ord($char, 'UTF-8'));
        }

        if ($trimmed !== $value) {
            $codes[] = 'leading_or_trailing_whitespace';
        }

        return array_values(array_unique($codes));
    }

    private static function stripInvisible(string $value): string
    {
        return preg_replace(self::INVISIBLE_PATTERN, '', $value) ?? trim($value);
    }

    /**
     * Preferred storage form for Saudi mobiles (966XXXXXXXXX); otherwise trimmed input.
     */
    public static function canonicalPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = LoginIdentifier::trimInput($phone);
        if ($trimmed === '') {
            return null;
        }

        if (LoginIdentifier::isValidSaudiMobileInput($trimmed)) {
            $candidates = LoginIdentifier::phoneMatchCandidates($trimmed);
            foreach ($candidates as $candidate) {
                if (preg_match('/^9665\d{8}$/', $candidate) === 1) {
                    return $candidate;
                }
            }
        }

        return $trimmed;
    }

    /**
     * Compare parent display names. Unifies Arabic spelling variants of the same letters
     * (hamza/alef forms, alef maqsura, taa marbuta, diacritics, tatweel) and "عبد ال…" spacing,
     * then collapses whitespace and lowercases. Never drops letters, digits or name parts.
     */
    public static function normalizeFullName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $value = preg_replace('/\p{Cf}+/u', '', $name) ?? $name;
        $value = trim(preg_replace('/[\p{Z}\s]+/u', ' ', $value) ?? $value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value) ?? $value;
        $value = strtr($value, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي',
        ]);
        $value = preg_replace('/(^|\s)عبد\s+(?=ال)/u', '$1عبد', $value) ?? $value;

        return mb_strtolower($value);
    }

    public static function normalizeIdOrResidence(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = LoginIdentifier::trimInput($value);
        if ($trimmed === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        return $digits !== '' ? $digits : mb_strtolower($trimmed);
    }

    /**
     * Synthetic phone reserved for merged-away parent rows (unique, not a valid Saudi login).
     */
    public static function mergedAccountPlaceholderPhone(int $userId): string
    {
        return '999'.str_pad((string) $userId, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Parent-only accounts (every assigned role is default_name "parent").
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function scopeParentOnlyAccounts(Builder $query): Builder
    {
        return $query
            ->whereHas('roles', fn ($roles) => $roles->where('default_name', System::USER_TYPE_PARENT_ROLE_NAME))
            ->whereDoesntHave('roles', function ($roles) {
                $roles->where('default_name', '!=', System::USER_TYPE_PARENT_ROLE_NAME)
                    ->whereNotNull('default_name');
            });
    }

    public static function roleIdsIncludeParent(?string $rolesCsv): bool
    {
        if ($rolesCsv === null || $rolesCsv === '') {
            return false;
        }

        $roleIds = array_filter(array_map('trim', explode(',', $rolesCsv)));
        if ($roleIds === []) {
            return false;
        }

        return \Spatie\Permission\Models\Role::query()
            ->whereIn('id', $roleIds)
            ->where('default_name', System::USER_TYPE_PARENT_ROLE_NAME)
            ->exists();
    }
}
