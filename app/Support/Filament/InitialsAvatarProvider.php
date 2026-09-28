<?php

namespace App\Support\Filament;

use Filament\AvatarProviders\UiAvatarsProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament's initials avatar, drawn in the soft red of the theme (primary 700
 * on primary 100) instead of white on near-black.
 */
final class InitialsAvatarProvider extends UiAvatarsProvider
{
    public function get(Model|Authenticatable $record): string
    {
        return str(parent::get($record))
            ->before('&color=')
            ->append('&color=', self::hex(PanelTheme::PRIMARY[700]))
            ->append('&background=', self::hex(PanelTheme::PRIMARY[100]))
            ->toString();
    }

    private static function hex(string $color): string
    {
        return strtoupper(ltrim($color, '#'));
    }
}
