<?php

namespace App\Modules\Maintenance\States\Ticket;

use Filament\Support\Icons\Heroicon;

final class Done extends TicketState
{
    /** @var string */
    public static $name = 'done';

    public function getLabel(): string
    {
        return 'Selesai';
    }

    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): Heroicon
    {
        return Heroicon::OutlinedCheckCircle;
    }
}
