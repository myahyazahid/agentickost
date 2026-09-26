<?php

namespace App\Modules\Billing\States\Invoice;

use App\Modules\Billing\Models\Invoice;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Invoice status (PRD §9.4). "Late" is not a status: it is computed from the
 * due date and the balance, so it never goes stale. Payment reversals move a
 * paid invoice back (PRD §8.10).
 *
 * @extends State<Invoice>
 */
abstract class InvoiceState extends State implements HasColor, HasIcon, HasLabel
{
    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    abstract public function getIcon(): Heroicon;

    /**
     * Whether the invoice still expects money.
     */
    public function isOpen(): bool
    {
        return false;
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Issued::class)
            ->allowTransition(Issued::class, Partial::class)
            ->allowTransition(Issued::class, Paid::class)
            ->allowTransition(Partial::class, Paid::class)
            ->allowTransition(Issued::class, Voided::class)
            ->allowTransition(Partial::class, Issued::class)
            ->allowTransition(Paid::class, Partial::class)
            ->allowTransition(Paid::class, Issued::class);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [Issued::$name, Partial::$name];
    }
}
