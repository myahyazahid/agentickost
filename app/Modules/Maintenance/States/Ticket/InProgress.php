<?php

namespace App\Modules\Maintenance\States\Ticket;

use Filament\Support\Icons\Heroicon;

final class InProgress extends TicketState
{
    /** @var string */
    public static $name = 'in_progress';

    public function getLabel(): string
    {
        return 'Dikerjakan';
    }

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedWrenchScrewdriver;
    }

    public function isOpen(): bool
    {
        return true;
    }
}
