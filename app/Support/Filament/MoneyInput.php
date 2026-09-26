<?php

namespace App\Support\Filament;

use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;

/**
 * Whole-rupiah input with thousand separators ("1.200.000"). The dots are
 * stripped before validation, so the state is a plain integer string.
 */
final class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->prefix('Rp')
            ->mask(RawJs::make("\$money(\$input, ',', '.', 0)"))
            ->stripCharacters('.')
            ->numeric()
            ->integer()
            ->minValue(0);
    }
}
