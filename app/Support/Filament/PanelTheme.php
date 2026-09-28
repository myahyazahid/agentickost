<?php

namespace App\Support\Filament;

use Filament\Panel;

/**
 * Shared look of the app and admin panels: light mode only, red primary and
 * warm grays, with Instrument Sans and soft red initials avatars. Component
 * styling lives in resources/css/filament/theme.css.
 *
 * Primary 500/600 carry white button text at 4.5:1 or better, and gray 500
 * passes 4.5:1 on white and on gray 50, so Filament's contrast checks keep
 * their default text colors.
 */
final class PanelTheme
{
    public const string STYLESHEET = 'resources/css/filament/theme.css';

    public const string FONT = 'Instrument Sans';

    /**
     * @var array<int, string>
     */
    public const array PRIMARY = [
        50 => '#fdf2f2',
        100 => '#fce4e4',
        200 => '#f9cccd',
        300 => '#f4a5a8',
        400 => '#f2545b',
        500 => '#e0262c',
        600 => '#d1242a',
        700 => '#c42127',
        800 => '#9c1b20',
        900 => '#7f1a1e',
        950 => '#450a0c',
    ];

    /**
     * 50 is the off-white canvas, 100 raised surfaces, 200 hairlines, and 950
     * body text.
     *
     * @var array<int, string>
     */
    private const array GRAY = [
        50 => '#faf7f7',
        100 => '#f5f1f1',
        200 => '#e8e2e2',
        300 => '#d5cecf',
        400 => '#a39c9e',
        500 => '#767072',
        600 => '#5f595b',
        700 => '#454042',
        800 => '#232326',
        900 => '#111113',
        950 => '#0a0a0b',
    ];

    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->darkMode(false)
            ->viteTheme(self::STYLESHEET)
            ->font(self::FONT)
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->colors([
                'primary' => self::PRIMARY,
                'gray' => self::GRAY,
            ]);
    }
}
