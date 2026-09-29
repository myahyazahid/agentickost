<?php

namespace App\Modules\Portal\Support;

use App\Modules\Tenancy\Models\Tenant;

/**
 * The tenant's accent color in the portal (FR-SUB-07), with a text color
 * that stays readable on it (WCAG AA), whatever color the owner picked.
 */
final class PortalTheme
{
    /**
     * Agentic Kost red, used when the owner has not picked a color.
     */
    public const DEFAULT_ACCENT = '#d1242a';

    public static function accent(Tenant $tenant): string
    {
        $color = $tenant->brand_color;

        return is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color) === 1 ? strtolower($color) : self::DEFAULT_ACCENT;
    }

    /**
     * White or near-black, whichever contrasts more with the accent.
     */
    public static function accentText(Tenant $tenant): string
    {
        $luminance = self::luminance(self::accent($tenant));

        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.05) ? '#ffffff' : '#0a0a0b';
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
