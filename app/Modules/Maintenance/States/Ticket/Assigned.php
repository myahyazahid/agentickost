<?php

namespace App\Modules\Maintenance\States\Ticket;

use Filament\Support\Icons\Heroicon;

final class Assigned extends TicketState
{
    /** @var string */
    public static $name = 'assigned';

    public function getLabel(): string
    {
        return 'Ditugaskan';
    }

    public function getColor(): string
    {
        return 'warning';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedUserCircle;
    }

    public function isOpen(): bool
    {
        return true;
    }
}
