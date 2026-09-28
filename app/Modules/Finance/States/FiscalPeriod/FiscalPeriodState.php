<?php

namespace App\Modules\Finance\States\FiscalPeriod;

use App\Modules\Finance\Models\FiscalPeriod;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * A month of the books. A closed period refuses new journals (PRD §8.11);
 * closing and reopening arrive with FR-ACC-08.
 *
 * @extends State<FiscalPeriod>
 */
abstract class FiscalPeriodState extends State implements HasColor, HasIcon, HasLabel
{
    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    abstract public function getIcon(): Heroicon;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Open::class)
            ->allowTransition(Open::class, Closed::class)
            ->allowTransition(Closed::class, Open::class);
    }
}
