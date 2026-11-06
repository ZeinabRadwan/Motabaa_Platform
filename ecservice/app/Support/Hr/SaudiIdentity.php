<?php

namespace App\Support\Hr;

/**
 * Saudi National ID / Iqama number: 10 digits, first digit 1 for citizens (National ID) or 2 for residents
 * (Iqama), last digit a Luhn-style check digit (digits at even positions doubled and digit-summed).
 */
final class SaudiIdentity
{
    public const SAUDI_NATIONALITY = 'SA';

    public const TYPE_NATIONAL_ID = 'national_id';
    public const TYPE_IQAMA = 'iqama';

    public static function normalize(?string $value): string
    {
        $value = strtr((string) $value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return preg_replace('/\s+/', '', $value) ?? '';
    }

    public static function hasValidChecksum(string $number): bool
    {
        if (! preg_match('/^[12]\d{9}$/', $number)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $digit = (int) $number[$i];
            if ($i % 2 === 0) {
                $doubled = $digit * 2;
                $sum += intdiv($doubled, 10) + ($doubled % 10);
            } else {
                $sum += $digit;
            }
        }

        return $sum % 10 === 0;
    }

    public static function type(string $number): ?string
    {
        return match ($number[0] ?? null) {
            '1' => self::TYPE_NATIONAL_ID,
            '2' => self::TYPE_IQAMA,
            default => null,
        };
    }

    /**
     * Validation failure reason (key under validation.hr) or null when the number is valid. With a nationality,
     * Saudi citizens must hold a National ID and everyone else an Iqama.
     */
    public static function validationError(?string $value, ?string $nationality = null): ?string
    {
        $number = self::normalize($value);
        if (! self::hasValidChecksum($number)) {
            return 'id_number_invalid';
        }

        if ($nationality !== null && $nationality !== '') {
            $expected = strtoupper($nationality) === self::SAUDI_NATIONALITY ? self::TYPE_NATIONAL_ID : self::TYPE_IQAMA;
            if (self::type($number) !== $expected) {
                return 'id_number_nationality_mismatch';
            }
        }

        return null;
    }
}
