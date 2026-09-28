<?php

namespace App\Modules\Maintenance\Filament\App\Resources\Tickets\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Maintenance\Filament\App\Resources\Tickets\TicketResource;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\States\Ticket\TicketState;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Laporkan')
                ->visible(fn (): bool => User::current()->can('create', Ticket::class)),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $userId = User::current()->id;

        return [
            'terbuka' => Tab::make('Terbuka')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', TicketState::openValues())),
            'saya' => Tab::make('Tugas saya')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('assigned_user_id', $userId)->whereIn('status', TicketState::openValues())),
            'selesai' => Tab::make('Selesai atau ditolak')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', TicketState::openValues())),
            'semua' => Tab::make('Semua'),
        ];
    }
}
