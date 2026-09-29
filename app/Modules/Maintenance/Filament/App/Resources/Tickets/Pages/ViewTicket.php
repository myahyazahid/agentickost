<?php

namespace App\Modules\Maintenance\Filament\App\Resources\Tickets\Pages;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Filament\AttachmentUpload;
use App\Modules\Finance\Support\SpendingAccounts;
use App\Modules\Maintenance\Actions\AssignTicket;
use App\Modules\Maintenance\Actions\CommentOnTicket;
use App\Modules\Maintenance\Actions\ConfirmTicket;
use App\Modules\Maintenance\Actions\RejectTicket;
use App\Modules\Maintenance\Actions\ReopenTicket;
use App\Modules\Maintenance\Actions\ResolveTicket;
use App\Modules\Maintenance\Actions\StartTicketWork;
use App\Modules\Maintenance\Filament\App\Resources\Tickets\TicketResource;
use App\Modules\Maintenance\Models\Ticket;
use App\Modules\Maintenance\States\Ticket\Assigned;
use App\Modules\Maintenance\States\Ticket\AwaitingConfirmation;
use App\Modules\Maintenance\States\Ticket\InProgress;
use App\Modules\Maintenance\States\Ticket\Reported;
use App\Modules\Maintenance\Support\TicketCharges;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyInput;
use App\Support\Money\Rupiah;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @extends ViewRecord<Ticket>
 */
class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->assignAction(),
            $this->startAction(),
            $this->markResolvedAction(),
            $this->confirmAction(),
            ActionGroup::make([
                $this->commentAction(),
                $this->reopenAction(),
                $this->rejectAction(),
            ])->label('Lainnya')->button()->color('gray'),
        ];
    }

    private function assignAction(): Action
    {
        return Action::make('assign')
            ->label(fn (): string => $this->ticket()->assigned_user_id === null ? 'Tugaskan' : 'Alihkan')
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (): bool => $this->ticket()->status->equals(Reported::class, Assigned::class, InProgress::class) && $this->can('manage'))
            ->schema([
                Select::make('assigned_user_id')
                    ->label('Staf')
                    ->options(fn (): array => $this->staffOptions())
                    ->default(fn (): ?string => $this->ticket()->assigned_user_id)
                    ->required()
                    ->native(false),
            ])
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(AssignTicket::class)->handle($this->ticket(), $data), 'Tiket ditugaskan'));
    }

    private function startAction(): Action
    {
        return Action::make('start')
            ->label('Mulai kerjakan')
            ->icon(Heroicon::OutlinedPlay)
            ->visible(fn (): bool => $this->ticket()->status->equals(Assigned::class) && $this->can('work'))
            ->action(fn (Action $action) => $this->run($action, fn () => app(StartTicketWork::class)->handle($this->ticket()), 'Tiket sedang dikerjakan'));
    }

    private function markResolvedAction(): Action
    {
        return Action::make('resolve')
            ->label('Selesai diperbaiki')
            ->icon(Heroicon::OutlinedCheck)
            ->visible(fn (): bool => $this->ticket()->status->equals(InProgress::class) && $this->can('work'))
            ->modalDescription('Owner atau manajer mengonfirmasi hasilnya. Biaya dicatat sebagai pengeluaran saat dikonfirmasi.')
            ->schema([
                Textarea::make('note')->label('Yang dikerjakan')->placeholder('Misal: karet keran diganti')->required(),
                AttachmentUpload::make('photos', AttachmentCollection::After)->label('Foto sesudah')->maxFiles(5),
                MoneyInput::make('cost_amount')->label('Biaya')->default(0)->live(),
                Select::make('paid_from_account_id')
                    ->label('Dibayar dari')
                    ->options(fn (): array => SpendingAccounts::paidFrom(User::current())->pluck('name', 'id')->all())
                    ->visible(fn (Get $get): bool => (int) $get('cost_amount') > 0)
                    ->required(fn (Get $get): bool => (int) $get('cost_amount') > 0)
                    ->native(false),
                Toggle::make('charge_to_resident')
                    ->label('Tagihkan ke penghuni')
                    ->helperText('Bila kerusakan disebabkan penghuni. Tagihannya terbit saat tiket dikonfirmasi.')
                    ->visible(fn (Get $get): bool => (int) $get('cost_amount') > 0 && TicketCharges::residentContract($this->ticket()) !== null),
            ])
            ->modalSubmitActionLabel('Simpan')
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(ResolveTicket::class)->handle($this->ticket(), $data), 'Menunggu konfirmasi'));
    }

    private function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label('Konfirmasi selesai')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->visible(fn (): bool => $this->ticket()->status->equals(AwaitingConfirmation::class) && $this->can('manage'))
            ->modalHeading('Konfirmasi perbaikan selesai?')
            ->modalDescription(fn (): string => $this->ticket()->cost_amount > 0
                ? 'Biaya '.Rupiah::format($this->ticket()->cost_amount).' dicatat sebagai pengeluaran perbaikan'.($this->ticket()->charge_to_resident ? ' dan ditagihkan ke penghuni.' : '.')
                : 'Tiket ditutup tanpa biaya.')
            ->schema([
                Textarea::make('note')->label('Catatan'),
            ])
            ->modalSubmitActionLabel('Konfirmasi')
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(ConfirmTicket::class)->handle($this->ticket(), $data), 'Tiket selesai'));
    }

    private function reopenAction(): Action
    {
        return Action::make('reopen')
            ->label('Buka kembali')
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (): bool => $this->ticket()->status->equals(AwaitingConfirmation::class) && $this->can('manage'))
            ->schema([
                Textarea::make('reason')->label('Alasan')->placeholder('Misal: keran masih menetes')->required()->minLength(5),
            ])
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(ReopenTicket::class)->handle($this->ticket(), $data), 'Tiket dibuka kembali'));
    }

    private function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Tolak')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (): bool => $this->ticket()->status->equals(Reported::class) && $this->can('manage'))
            ->schema([
                Textarea::make('reason')->label('Alasan')->placeholder('Misal: sudah dilaporkan di tiket lain')->required()->minLength(5),
            ])
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(RejectTicket::class)->handle($this->ticket(), $data), 'Tiket ditolak'));
    }

    private function commentAction(): Action
    {
        return Action::make('comment')
            ->label('Tambah catatan')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->visible(fn (): bool => $this->can('comment'))
            ->schema([
                Textarea::make('note')->label('Catatan')->required(),
            ])
            ->action(fn (Action $action, array $data) => $this->run($action, fn () => app(CommentOnTicket::class)->handle($this->ticket(), $data), 'Catatan ditambahkan'));
    }

    /**
     * @return array<string, string>
     */
    private function staffOptions(): array
    {
        $property = $this->ticket()->property()->firstOrFail();

        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->filter(fn (User $user): bool => $property->isAccessibleBy($user))
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
            ->all();
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    private function run(Action $action, Closure $callback, string $success): void
    {
        DomainActions::forAction($action, $callback);

        $this->getRecord()->refresh();

        Notification::make()->success()->title($success)->send();
    }

    private function ticket(): Ticket
    {
        return $this->getRecord();
    }

    private function can(string $ability): bool
    {
        return User::current()->can($ability, $this->ticket());
    }
}
