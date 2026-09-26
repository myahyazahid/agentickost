<?php

namespace App\Modules\Lease\Filament\App\Resources\Contracts\RelationManagers;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Actions\AddContractHold;
use App\Modules\Lease\Actions\RemoveContractHold;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\Models\ContractHold;
use App\Modules\Lease\States\Contract\Completed;
use App\Modules\Lease\States\Contract\Terminated;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyColumn;
use App\Support\Filament\MoneyInput;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Special rates while the resident is away (FR-KTR-05).
 */
class HoldsRelationManager extends RelationManager
{
    protected static string $relationship = 'holds';

    protected static ?string $title = 'Tarif khusus masa libur';

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
                TextColumn::make('start_date')
                    ->label('Periode')
                    ->date('j M Y')
                    ->description(fn (ContractHold $record): string => 'sampai '.$record->end_date->translatedFormat('j M Y')),
                MoneyColumn::make('rent_amount')->label('Sewa per periode'),
                TextColumn::make('reason')->label('Keterangan')->placeholder('-'),
            ])
            ->defaultSort('start_date')
            ->headerActions([
                Action::make('addHold')
                    ->label('Tambah masa libur')
                    ->icon(Heroicon::OutlinedPause)
                    ->visible(fn (): bool => $this->canChange())
                    ->modalDescription('Periode tagihan yang dimulai di dalam rentang ini memakai sewa khusus.')
                    ->schema([
                        DatePicker::make('start_date')->label('Mulai')->required(),
                        DatePicker::make('end_date')->label('Selesai')->required(),
                        MoneyInput::make('rent_amount')->label('Sewa per periode selama libur')->required(),
                        Textarea::make('reason')->label('Keterangan')->placeholder('Misal: libur semester, kamar tetap ditahan'),
                    ])
                    ->action(function (Action $action, array $data): void {
                        DomainActions::forAction($action, fn () => app(AddContractHold::class)->handle($this->contract(), $data));
                    }),
            ])
            ->recordActions([
                Action::make('removeHold')
                    ->label('Hapus')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ContractHold $record): bool => $this->canChange()
                        && $record->start_date->greaterThan($this->contract()->property()->firstOrFail()->today()))
                    ->action(function (Action $action, ContractHold $record): void {
                        DomainActions::forAction($action, fn () => app(RemoveContractHold::class)->handle($record));
                    }),
            ])
            ->emptyStateHeading('Tidak ada tarif khusus')
            ->emptyStateDescription('Atur sewa khusus bila penghuni pulang kampung lama tapi kamarnya tetap ditahan.');
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

        return $owner instanceof Contract ? $owner : throw new LogicException('Relasi masa libur hanya untuk kontrak.');
    }
}
