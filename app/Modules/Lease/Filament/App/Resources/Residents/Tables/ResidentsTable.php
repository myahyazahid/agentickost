<?php

namespace App\Modules\Lease\Filament\App\Resources\Residents\Tables;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Actions\RevealResidentIdentity;
use App\Modules\Lease\Models\Resident;
use App\Support\Filament\DomainActions;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ResidentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nama')
                    ->description(fn (Resident $record): ?string => $record->institution)
                    ->searchable(['full_name', 'phone'])
                    ->sortable(),
                TextColumn::make('phone')->label('WhatsApp'),
                TextColumn::make('room')
                    ->label('Kamar saat ini')
                    ->state(function (Resident $record): ?string {
                        $contract = $record->runningContract();

                        return $contract === null ? null : "{$contract->room?->number}, {$contract->property?->name}";
                    })
                    ->placeholder('Belum menyewa'),
                TextColumn::make('is_flagged')
                    ->label('Tanda')
                    ->formatStateUsing(fn (bool $state): ?string => $state ? 'Tidak disarankan' : null)
                    ->badge()
                    ->color('danger')
                    ->icon(fn (bool $state): ?Heroicon => $state ? Heroicon::OutlinedFlag : null),
            ])
            ->filters([
                TernaryFilter::make('is_flagged')->label('Tidak disarankan'),
            ])
            ->defaultSort('full_name')
            ->recordActions([
                EditAction::make(),
                Action::make('revealIdentity')
                    ->label('Lihat identitas')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Lihat data identitas?')
                    ->modalDescription('Akses ini tercatat di log audit atas nama Anda.')
                    ->modalSubmitActionLabel('Lihat')
                    ->visible(fn (Resident $record): bool => User::current()->can('viewIdentity', $record))
                    ->action(function (Action $action, Resident $record): void {
                        DomainActions::forAction($action, function () use ($record): void {
                            $identity = app(RevealResidentIdentity::class)->handle($record);

                            Notification::make()
                                ->title("Identitas {$record->full_name}")
                                ->body(($identity['identity_type'] ?? 'Identitas').': '.($identity['identity_number'] ?? 'belum diisi'))
                                ->actions(array_map(
                                    fn (array $document, int $index): Action => Action::make("document{$index}")
                                        ->label("Buka {$document['name']}")
                                        ->url($document['url'], shouldOpenInNewTab: true),
                                    $identity['documents'],
                                    array_keys($identity['documents']),
                                ))
                                ->persistent()
                                ->send();
                        });
                    }),
            ])
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->emptyStateHeading('Belum ada penghuni')
            ->emptyStateDescription('Tambahkan penghuni, lalu buat kontraknya dari menu Kontrak.');
    }
}
