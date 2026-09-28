<?php

namespace App\Modules\Onboarding\Import;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Filament\Support\Contracts\HasLabel;
use InvalidArgumentException;

/**
 * Reads spreadsheet cells the way owners type them: amounts with dots or
 * "Rp", dates as 31/12/2026 or real date cells, and choices by their label.
 * A value that cannot be read throws InvalidArgumentException with a
 * message for the row.
 */
final class Cell
{
    public static function text(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y');
        }

        if (is_float($value) && floor($value) === $value) {
            $value = sprintf('%.0f', $value);
        }

        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    public static function amount(mixed $value, string $column): ?int
    {
        if (is_int($value) || is_float($value)) {
            if ($value < 0) {
                throw new InvalidArgumentException("{$column} tidak boleh negatif.");
            }

            return (int) round($value);
        }

        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        $digits = preg_replace('/[.,]00$/', '', preg_replace('/^rp\.?\s*/i', '', $text) ?? '') ?? '';

        if (preg_match('/^\d{1,3}([.,\s]?\d{3})*$/', $digits) !== 1) {
            throw new InvalidArgumentException("{$column} harus berupa angka rupiah, misal 1200000.");
        }

        return (int) preg_replace('/\D/', '', $digits);
    }

    public static function integer(mixed $value, string $column): ?int
    {
        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        if (preg_match('/^\d+$/', $text) !== 1) {
            throw new InvalidArgumentException("{$column} harus berupa angka bulat.");
        }

        return (int) $text;
    }

    /**
     * Dates as date cells, Excel serial numbers, or text in day-month-year
     * order (31/12/2026, 31-12-2026) or ISO order (2026-12-31).
     */
    public static function date(mixed $value, string $column): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value) || is_float($value)) {
            return CarbonImmutable::parse('1899-12-30')->addDays((int) $value)->toDateString();
        }

        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        [$year, $month, $day] = match (true) {
            preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $parts) === 1 => [(int) $parts[1], (int) $parts[2], (int) $parts[3]],
            preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4}|\d{2})$/', $text, $parts) === 1 => [
                strlen($parts[3]) === 2 ? 2000 + (int) $parts[3] : (int) $parts[3],
                (int) $parts[2],
                (int) $parts[1],
            ],
            default => [0, 0, 0],
        };

        if (! checkdate($month, $day, $year)) {
            throw new InvalidArgumentException("{$column} tidak bisa dibaca. Tulis seperti 31/12/2026.");
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Matches an enum case by its value or label, ignoring case, or by one
     * of the extra aliases given.
     *
     * @template TEnum of BackedEnum&HasLabel
     *
     * @param  class-string<TEnum>  $enum
     * @param  array<string, TEnum>  $aliases
     * @return TEnum|null
     */
    public static function choice(mixed $value, string $enum, string $column, array $aliases = []): ?BackedEnum
    {
        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        $needle = mb_strtolower($text);

        foreach ($aliases as $alias => $case) {
            if (mb_strtolower($alias) === $needle) {
                return $case;
            }
        }

        foreach ($enum::cases() as $case) {
            $label = $case->getLabel();

            if (mb_strtolower((string) $case->value) === $needle || (is_string($label) && mb_strtolower($label) === $needle)) {
                return $case;
            }
        }

        throw new InvalidArgumentException("{$column} \"{$text}\" tidak dikenal.");
    }
}
