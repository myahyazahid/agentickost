<?php

namespace App\Modules\Payment\States\Handover;

use App\Modules\Payment\Models\StaffCashHandover;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Status of a staff cash handover (FR-PAY-08). A disputed handover stays
 * open until the owner confirms it with an explanation.
 *
 * @extends State<StaffCashHandover>
 */
abstract class HandoverState extends State implements HasColor, HasIcon, HasLabel
{
    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    abstract public function getIcon(): Heroicon;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Confirmed::class)
            ->allowTransition(Pending::class, Disputed::class)
            ->allowTransition(Disputed::class, Confirmed::class);
    }
}
