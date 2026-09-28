<?php

namespace App\Modules\Maintenance\States\Ticket;

use App\Modules\Maintenance\Models\Ticket;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Maintenance ticket status (PRD §9.5). A resolved ticket waits for
 * confirmation and can be reopened if the repair did not hold.
 *
 * @extends State<Ticket>
 */
abstract class TicketState extends State implements HasColor, HasIcon, HasLabel
{
    abstract public function getLabel(): string;

    abstract public function getColor(): string;

    abstract public function getIcon(): Heroicon;

    /**
     * Whether the ticket still needs work or a decision.
     */
    public function isOpen(): bool
    {
        return false;
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Reported::class)
            ->allowTransition(Reported::class, Assigned::class)
            ->allowTransition(Assigned::class, InProgress::class)
            ->allowTransition(InProgress::class, AwaitingConfirmation::class)
            ->allowTransition(AwaitingConfirmation::class, Done::class)
            ->allowTransition(Reported::class, Rejected::class)
            ->allowTransition(AwaitingConfirmation::class, InProgress::class);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [Reported::$name, Assigned::$name, InProgress::$name, AwaitingConfirmation::$name];
    }
}
