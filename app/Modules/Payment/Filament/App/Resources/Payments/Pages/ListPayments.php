<?php

namespace App\Modules\Payment\Filament\App\Resources\Payments\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Payment\Enums\PaymentMethod;
use App\Modules\Payment\Filament\App\Resources\Payments\PaymentResource;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\States\Payment\Pending;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat pembayaran')
                ->visible(fn (): bool => User::current()->can('create', Payment::class)),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $pending = PaymentResource::getEloquentQuery()->where('status', Pending::$name)->count();

        return [
            'semua' => Tab::make('Semua'),
            'verifikasi' => Tab::make('Menunggu verifikasi')
                ->badge($pending > 0 ? $pending : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', Pending::$name)),
            'tunai' => Tab::make('Tunai')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('method', PaymentMethod::Cash->value)),
        ];
    }
}
