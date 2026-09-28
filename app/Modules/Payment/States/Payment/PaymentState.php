<?php

namespace App\Modules\Payment\States\Payment;

use App\Modules\Payment\Models\Payment;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Payment status (FR-PAY-02, FR-PAY-03). Only a verified payment pays
 * invoices; a wrong one is reversed, never deleted (PRD §8.10).
 *
 * @extends State<Payment>
 */
abstract class PaymentState extends State implements HasColor, HasIcon, HasLabel
{
    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    abstract public function getIcon(): Heroicon;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Verified::class)
            ->allowTransition(Pending::class, Rejected::class)
            ->allowTransition(Verified::class, Reversed::class);
    }
}
