<?php

namespace App\Modules\Finance\States\OpeningBalance;

use App\Modules\Finance\Models\OpeningBalance;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * A draft opening balance can still be edited; once posted it has written
 * its invoices, ledgers, and journal and never changes (PRD §8.10).
 *
 * @extends State<OpeningBalance>
 */
abstract class OpeningBalanceState extends State implements HasColor, HasIcon, HasLabel
{
    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    abstract public function getIcon(): Heroicon;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Posted::class);
    }
}
