<?php

namespace App\Modules\Access\Filament\App\Resources\Users\Widgets;

use App\Modules\Access\Actions\CancelStaffInvitation;
use App\Modules\Access\Actions\ResendStaffInvitation;
use App\Modules\Access\Models\StaffInvitation;
use App\Support\Filament\DomainActions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Livewire\Attributes\On;

/**
 * Invitations not accepted yet, under the staff list (FR-USR-03).
 */
class PendingInvitations extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    #[On('invitations-changed')]
    public function refreshInvitations(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Undangan belum diterima')
            ->query(fn () => StaffInvitation::query()->open()->latest())
            ->columns([
                TextColumn::make('name')->label('Nama')->description(fn (StaffInvitation $record): string => $record->email),
                TextColumn::make('role')->label('Peran'),
                TextColumn::make('expires_at')
                    ->label('Status')
                    ->state(fn (StaffInvitation $record): string => $record->isExpired()
                        ? 'Kedaluwarsa'
                        : 'Berlaku sampai '.$record->expires_at->translatedFormat('j M Y'))
                    ->badge()
                    ->color(fn (StaffInvitation $record): string => $record->isExpired() ? 'warning' : 'gray'),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label('Kirim ulang')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->modalDescription('Tautan lama berhenti berlaku dan staf menerima tautan baru yang berlaku 7 hari.')
                    ->action(function (Action $action, StaffInvitation $record): void {
                        DomainActions::forAction($action, fn () => app(ResendStaffInvitation::class)->handle($record));

                        Notification::make()->success()->title("Undangan dikirim ulang ke {$record->email}")->send();
                    }),
                Action::make('cancel')
                    ->label('Batalkan')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan undangan ini?')
                    ->modalDescription('Tautan di email berhenti berlaku.')
                    ->action(function (Action $action, StaffInvitation $record): void {
                        DomainActions::forAction($action, fn () => app(CancelStaffInvitation::class)->handle($record));

                        Notification::make()->success()->title('Undangan dibatalkan')->send();
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading('Tidak ada undangan yang menunggu')
            ->emptyStateDescription('Undangan yang sudah diterima pindah ke daftar staf di atas.');
    }
}
