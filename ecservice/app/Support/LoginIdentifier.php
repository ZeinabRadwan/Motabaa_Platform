<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class LoginIdentifier
{
    /** Saudi mobile in local form: 05xxxxxxxx (10 digits). */
    private const SAUDI_LOCAL_MOBILE_PATTERN = '/^0(5\d{8})$/';

    /** Saudi mobile without leading zero: 5xxxxxxxx (9 digits). */
    private const SAUDI_MOBILE_NATIONAL_PATTERN = '/^(5\d{8})$/';

    /** Saudi mobile with country code: 9665xxxxxxxx (12 digits). */
    private const SAUDI_INTERNATIONAL_MOBILE_PATTERN = '/^966(5\d{8})$/';

    /** Canonical storage for Saudi numbers in this platform (966 + 9 digits). */
    private const SAUDI_CALLING_CODE = '966';

    public static function trimInput(string $input): string
    {
        return trim($input);
    }

    public static function normalizedEmailOrNull(string $input): ?string
    {
        $input = self::trimInput($input);
        if ($input === '') {
            return null;
        }

        return filter_var($input, FILTER_VALIDATE_EMAIL) ? $input : null;
    }

    public static function isPlausiblePhoneInput(string $input): bool
    {
        if (self::normalizedEmailOrNull($input) !== null) {
            return false;
        }

        $trimmed = self::trimInput($input);
        if ($trimmed === '' || ! preg_match('/^[\d+\s().-]+$/', $trimmed)) {
            return false;
        }

        $digits = self::digitsOnly($trimmed);
        $length = strlen($digits);

        return $length >= 9 && $length <= 15;
    }

    /** Saudi mobile: 05xxxxxxxx, 5xxxxxxxx, 9665xxxxxxxx, +9665xxxxxxxx (spaces allowed). */
    public static function isValidSaudiMobileInput(string $input): bool
    {
        if (self::normalizedEmailOrNull($input) !== null) {
            return false;
        }

        $trimmed = self::trimInput($input);
        if ($trimmed === '' || ! preg_match('/^[\d+\s().-]+$/', $trimmed)) {
            return false;
        }

        $digits = self::digitsOnly($trimmed);

        return preg_match(self::SAUDI_LOCAL_MOBILE_PATTERN, $digits) === 1
            || preg_match(self::SAUDI_MOBILE_NATIONAL_PATTERN, $digits) === 1
            || preg_match(self::SAUDI_INTERNATIONAL_MOBILE_PATTERN, $digits) === 1;
    }

    /**
     * Digit-only representation of a phone login value (optional leading + stripped).
     *
     * @return array<int, string> Candidate values that may equal users.phone
     */
    public static function phoneMatchCandidates(string $input): array
    {
        $digits = self::digitsOnly($input);
        if ($digits === '') {
            return [];
        }

        $candidates = [$digits];

        if (preg_match(self::SAUDI_LOCAL_MOBILE_PATTERN, $digits, $matches)) {
            $candidates[] = self::SAUDI_CALLING_CODE.$matches[1];
        }

        if (preg_match(self::SAUDI_MOBILE_NATIONAL_PATTERN, $digits)) {
            $candidates[] = self::SAUDI_CALLING_CODE.$digits;
            $candidates[] = '0'.$digits;
        }

        if (preg_match(self::SAUDI_INTERNATIONAL_MOBILE_PATTERN, $digits, $matches)) {
            $candidates[] = '0'.$matches[1];
        }

        $withPlus = [];
        foreach ($candidates as $candidate) {
            if (str_starts_with($candidate, self::SAUDI_CALLING_CODE)) {
                $withPlus[] = '+'.$candidate;
            }
        }

        return array_values(array_unique(array_merge($candidates, $withPlus)));
    }

    /**
     * Resolve to the exact phone string stored on the user row, or null if none / ambiguous.
     */
    public static function resolveStoredPhone(string $input): ?string
    {
        $candidates = self::phoneMatchCandidates($input);
        if ($candidates === []) {
            return null;
        }

        /** @var Collection<int, string> $stored */
        $stored = User::query()
            ->whereIn('phone', $candidates)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->pluck('phone')
            ->unique()
            ->values();

        if ($stored->count() === 1) {
            return $stored->first();
        }

        return null;
    }

    private static function digitsOnly(string $input): string
    {
        return preg_replace('/\D+/', '', self::trimInput($input)) ?? '';
    }
}
