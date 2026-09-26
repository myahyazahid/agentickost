<?php

namespace App\Support\Filament;

use App\Support\Money\Rupiah;
use Filament\Tables\Columns\TextColumn;

final class MoneyColumn
{
    public static function make(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->formatStateUsing(fn (mixed $state): ?string => is_numeric($state) ? Rupiah::format((int) $state) : null)
            ->alignEnd();
    }
}
