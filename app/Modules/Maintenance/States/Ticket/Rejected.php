<?php

namespace App\Modules\Maintenance\States\Ticket;

use Filament\Support\Icons\Heroicon;

final class Rejected extends TicketState
{
    /** @var string */
    public static $name = 'rejected';

    public function getLabel(): string
    {
        return 'Ditolak';
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedXCircle;
    }
}
