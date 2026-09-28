<?php

namespace App\Modules\Maintenance\States\Ticket;

use Filament\Support\Icons\Heroicon;

final class AwaitingConfirmation extends TicketState
{
    /** @var string */
    public static $name = 'awaiting_confirmation';

    public function getLabel(): string
    {
        return 'Menunggu konfirmasi';
    }

    public function getColor(): string
    {
        return 'info';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedClock;
    }

    public function isOpen(): bool
    {
        return true;
    }
}
