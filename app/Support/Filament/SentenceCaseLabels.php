<?php

namespace App\Support\Filament;

use Illuminate\Support\Str;

/**
 * Indonesian UI text uses sentence case ("Tipe kamar"), not Filament's
 * default Title Case ("Tipe Kamar"), in navigation, titles, and breadcrumbs.
 */
trait SentenceCaseLabels
{
    public static function getTitleCaseModelLabel(): string
    {
        return Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }
}
