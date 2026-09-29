<?php

namespace App\Modules\Tenancy\Filament\Admin\Resources\Tenants\Pages;

use App\Modules\Access\Actions\StartImpersonation;
use App\Modules\Access\Support\Impersonation;
use App\Modules\Tenancy\Actions\ChangeTrialEnd;
use App\Modules\Tenancy\Actions\FreezeTenant;
use App\Modules\Tenancy\Actions\UnfreezeTenant;
use App\Modules\Tenancy\Filament\Admin\Resources\Tenants\TenantResource;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\TenantUsage;
use App\Support\Filament\DomainActions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * One tenant for the super admin: status, usage, owners' contacts, and the
 * support sessions opened inside it (FR-TNT-04 to FR-TNT-06).
 *
 * @extends ViewRecord<Tenant>
 */
class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return TenantUsage::query()->whereKey($key)->firstOrFail();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('status')
                        ->label('Status')
                        ->state(fn (Tenant $record) => $record->status())
                        ->badge()
                        ->helperText(fn (Tenant $record): ?string => TenantResource::statusDetail($record)),
                    TextEntry::make('owner_email')->label('Email owner')->placeholder('Belum ada owner')->copyable(),
                    TextEntry::make('created_at')->label('Mendaftar')->dateTime('j M Y H:i', fn (Tenant $record): string => $record->default_timezone),
                    TextEntry::make('frozen_reason')
                        ->label('Alasan dibekukan')
                        ->visible(fn (Tenant $record): bool => $record->isFrozen())
                        ->columnSpanFull(),
                ]),
            Section::make('Penggunaan')
                ->columnSpanFull()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('properties_count')->label('Properti')->numeric(),
                    TextEntry::make('rooms_count')->label('Kamar')->numeric(),
                    TextEntry::make('running_contracts_count')->label('Kontrak berjalan')->numeric(),
                    TextEntry::make('staff_count')->label('Pengguna aktif')->numeric(),
                ]),
            Section::make('Sesi super admin di tenant ini')
                ->description('Owner juga melihat daftar ini di panelnya.')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('impersonationLogs')
                        ->hiddenLabel()
                        ->state(fn (Tenant $record) => $record->impersonationLogs()->with('platformAdmin')->latest('started_at')->limit(20)->get())
                        ->table([
                            TableColumn::make('Mulai'),
                            TableColumn::make('Super admin'),
                            TableColumn::make('Alasan'),
                            TableColumn::make('Selesai'),
                        ])
                        ->schema([
                            TextEntry::make('started_at')->dateTime('j M Y H:i', fn (): string => $this->getRecord()->default_timezone),
                            TextEntry::make('platformAdmin.name'),
                            TextEntry::make('reason'),
                            TextEntry::make('ended_at')
                                ->dateTime('j M Y H:i', fn (): string => $this->getRecord()->default_timezone)
                                ->placeholder('Masih berjalan'),
                        ])
                        ->placeholder('Belum pernah ada sesi.'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('impersonate')
                ->label('Masuk sebagai owner')
                ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                ->visible(fn (): bool => ! $this->getRecord()->isFrozen())
                ->modalHeading('Masuk ke panel tenant ini?')
                ->modalDescription('Anda akan bekerja sebagai owner pertama tenant ini. Alasan dan setiap perubahan tercatat atas nama Anda dan terlihat oleh owner.')
                ->schema([
                    Textarea::make('reason')
                        ->label('Alasan')
                        ->placeholder('Misal: owner minta dibantu mengecek tagihan Oktober lewat WhatsApp')
                        ->required()
                        ->minLength(10)
                        ->maxLength(1000),
                ])
                ->modalSubmitActionLabel('Masuk')
                ->action(function (Action $action, array $data): void {
                    DomainActions::forAction($action, function () use ($data): void {
                        [$log, $owner] = app(StartImpersonation::class)->handle($this->getRecord(), $data);
                        app(Impersonation::class)->enter($log, $owner);
                    });

                    $this->redirect('/app');
                }),
            Action::make('changeTrial')
                ->label('Ubah akhir trial')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->fillForm(fn (): array => ['trial_ends_on' => $this->getRecord()->trial_ends_at?->timezone($this->getRecord()->default_timezone)->toDateString()])
                ->schema([
                    DatePicker::make('trial_ends_on')->label('Trial berakhir pada')->required(),
                    Textarea::make('reason')->label('Catatan')->placeholder('Misal: kost pilot, trial diperpanjang sampai akhir bulan')->maxLength(1000),
                ])
                ->modalSubmitActionLabel('Simpan')
                ->action(function (Action $action, array $data): void {
                    DomainActions::forAction($action, fn () => app(ChangeTrialEnd::class)->handle($this->getRecord(), $data));

                    Notification::make()->success()->title('Akhir trial diubah')->send();
                    $this->refreshRecord();
                }),
            Action::make('freeze')
                ->label('Bekukan')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('danger')
                ->visible(fn (): bool => ! $this->getRecord()->isFrozen())
                ->modalHeading('Bekukan tenant ini?')
                ->modalDescription('Semua pengguna tenant ini tidak bisa masuk dan tagihan otomatis berhenti. Data tetap tersimpan dan tenant bisa diaktifkan lagi.')
                ->schema([
                    Textarea::make('reason')->label('Alasan')->required()->minLength(5)->maxLength(1000),
                ])
                ->modalSubmitActionLabel('Bekukan')
                ->action(function (Action $action, array $data): void {
                    DomainActions::forAction($action, fn () => app(FreezeTenant::class)->handle($this->getRecord(), $data));

                    Notification::make()->success()->title('Tenant dibekukan')->send();
                    $this->refreshRecord();
                }),
            Action::make('unfreeze')
                ->label('Aktifkan lagi')
                ->icon(Heroicon::OutlinedLockOpen)
                ->visible(fn (): bool => $this->getRecord()->isFrozen())
                ->requiresConfirmation()
                ->modalHeading('Aktifkan tenant ini lagi?')
                ->modalDescription('Pengguna tenant bisa masuk lagi dan tagihan otomatis berjalan pada jadwal berikutnya.')
                ->modalSubmitActionLabel('Aktifkan')
                ->action(function (Action $action): void {
                    DomainActions::forAction($action, fn () => app(UnfreezeTenant::class)->handle($this->getRecord()));

                    Notification::make()->success()->title('Tenant aktif lagi')->send();
                    $this->refreshRecord();
                }),
        ];
    }

    private function refreshRecord(): void
    {
        $this->record = $this->resolveRecord($this->getRecord()->getKey());
    }
}
