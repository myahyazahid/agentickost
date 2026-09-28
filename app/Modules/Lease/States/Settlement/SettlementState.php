<?php

namespace App\Modules\Lease\States\Settlement;

use App\Modules\Lease\Models\Settlement;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Check-out settlement (FR-SIK-05). A draft is recomputed when finalized;
 * a finalized settlement is final.
 *
 * @extends State<Settlement>
 */
abstract class SettlementState extends State implements HasColor, HasIcon, HasLabel
{
    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    abstract public function getIcon(): Heroicon;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Finalized::class);
    }
}
