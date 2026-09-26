<?php

namespace App\Support\Database;

use BackedEnum;
use Illuminate\Support\Facades\DB;

/**
 * CHECK constraints for migrations (schema §1.3, §1.5).
 */
final class Check
{
    /**
     * Allow only the enum's values in the column.
     *
     * @param  class-string<BackedEnum>  $enum
     */
    public static function enum(string $table, string $column, string $enum, bool $nullable = false): void
    {
        $values = implode(', ', array_map(fn (BackedEnum $case): string => "'{$case->value}'", $enum::cases()));
        $condition = "{$column} IN ({$values})";

        self::add($table, "{$column}_check", $nullable ? "{$column} IS NULL OR {$condition}" : $condition);
    }

    public static function add(string $table, string $name, string $condition): void
    {
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$name} CHECK ({$condition})");
    }
}
