<?php

namespace App\Modules\Lease\Filament\App\Resources\Contracts\RelationManagers;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Actions\AddResidentToContract;
use App\Modules\Lease\Actions\RemoveResidentFromContract;
use App\Modules\Lease\Filament\App\Resources\Contracts\Schemas\ContractForm;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractResident;
use App\Modules\Lease\States\Contract\Completed;
use App\Modules\Lease\States\Contract\Draft;
use App\Modules\Lease\States\Contract\Terminated;
use App\Support\Filament\DomainActions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Residents living under the contract (FR-KTR-02).
 */
class OccupantsRelationManager extends RelationManager
{
    protected static string $relationship = 'occupants';

    protected static ?string $title = 'Penghuni';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Contract && User::current()->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('resident.full_name')->label('Nama'),
                TextColumn::make('resident.phone')->label('WhatsApp'),
                TextColumn::make('is_primary')
                    ->label('Peran')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Penghuni utama' : 'Penghuni')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'primary' : 'gray'),
                TextColumn::make('joined_on')->label('Mulai tinggal')->date('j M Y'),
                TextColumn::make('left_on')->label('Keluar')->date('j M Y')->placeholder('Masih tinggal'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('resident'))
            ->headerActions([
                Action::make('addResident')
                    ->label('Tambah penghuni')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->visible(fn (): bool => $this->canChange() && $this->hasFreeBed())
                    ->schema([
                        Select::make('resident_id')
                            ->label('Penghuni')
                            ->options(fn (): array => ContractForm::residentOptions())
                            ->searchable()
                            ->required(),
                        DatePicker::make('joined_on')->label('Mulai tinggal')->default(now())->required(),
                    ])
                    ->action(function (Action $action, array $data): void {
                        DomainActions::forAction($action, fn () => app(AddResidentToContract::class)->handle($this->contract(), $data));
                    }),
            ])
            ->recordActions([
                Action::make('removeResident')
                    ->label('Keluarkan')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Kontrak tetap berjalan untuk penghuni lainnya.')
                    ->visible(fn (ContractResident $record): bool => $record->left_on === null && $this->canChange() && $this->activeCount() > 1)
                    ->schema(fn (): array => $this->contract()->status->equals(Draft::class) ? [] : [
                        DatePicker::make('left_on')->label('Keluar pada')->default(now())->required(),
                    ])
                    ->action(function (Action $action, ContractResident $record, array $data): void {
                        DomainActions::forAction($action, fn () => app(RemoveResidentFromContract::class)->handle(
                            $this->contract(),
                            $record->resident()->firstOrFail(),
                            $data,
                        ));
                    }),
            ]);
    }

    private function activeCount(): int
    {
        return $this->contract()->occupants()->whereNull('left_on')->count();
    }

    private function hasFreeBed(): bool
    {
        return $this->activeCount() < ($this->contract()->room()->first()->capacity ?? 1);
    }

    private function canChange(): bool
    {
        $contract = $this->contract();

        return ! $contract->status->equals(Completed::class, Terminated::class)
            && User::current()->can('update', $contract);
    }

    private function contract(): Contract
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Contract ? $owner : throw new LogicException('Relasi penghuni hanya untuk kontrak.');
    }
}
