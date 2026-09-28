<?php

namespace App\Modules\Maintenance\States\Ticket;

use Filament\Support\Icons\Heroicon;

final class Reported extends TicketState
{
    /** @var string */
    public static $name = 'new';

    public function getLabel(): string
    {
        return 'Baru';
    }

    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedInboxArrowDown;
    }

    public function isOpen(): bool
    {
        return true;
    }
}
