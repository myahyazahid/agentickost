<?php

namespace App\Support;

/**
 * Phone numbers are stored in E.164 (schema §1.10). Local Indonesian input
 * such as "0812-3456-7890" becomes "+6281234567890".
 */
final class Phone
{
    public const PATTERN = '/^\+[1-9]\d{7,14}$/';

    public static function normalize(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $input) ?? '';

        return match (true) {
            str_starts_with($digits, '+') => $digits,
            str_starts_with($digits, '62') => '+'.$digits,
            str_starts_with($digits, '0') => '+62'.substr($digits, 1),
            default => '+62'.$digits,
        };
    }

    public static function isValid(?string $phone): bool
    {
        return $phone !== null && preg_match(self::PATTERN, $phone) === 1;
    }
}
