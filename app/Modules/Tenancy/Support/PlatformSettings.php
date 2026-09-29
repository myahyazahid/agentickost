<?php

namespace App\Modules\Tenancy\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Platform-wide settings a super admin can change, such as the trial length
 * (FR-TNT-03). Not tenant data. Written only through
 * UpdatePlatformSettings.
 */
final class PlatformSettings
{
    public const TRIAL_DAYS = 'trial_days';

    public static function trialDays(): int
    {
        $days = self::get(self::TRIAL_DAYS);

        return is_int($days) ? $days : (int) config('agentickost.trial_days', 14);
    }

    public static function get(string $key): mixed
    {
        $value = DB::table('platform_settings')->where('key', $key)->value('value');

        return is_string($value) ? json_decode($value, true) : null;
    }

    public static function put(string $key, mixed $value): void
    {
        DB::table('platform_settings')->upsert(
            [['id' => (string) Str::ulid(), 'key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_at'],
        );
    }
}
