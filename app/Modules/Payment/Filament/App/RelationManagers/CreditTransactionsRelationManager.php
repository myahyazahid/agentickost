<?php

namespace App\Modules\Payment\Filament\App\RelationManagers;

use App\Modules\Access\Models\User;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Actions\ApplyCredit;
use App\Modules\Payment\Models\CreditTransaction;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Support\CreditLedger;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyColumn;
use App\Support\Money\Rupiah;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The contract's credit balance (FR-PAY-05): overpayments, and where they
 * were used.
 */
class CreditTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'creditTransactions';

    protected static ?string $title = 'Saldo kredit';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Contract && User::current()->can('viewAny', Payment::class) && User::current()->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(fn (): string => 'Saldo kredit sekarang '.Rupiah::format(CreditLedger::balance($this->contract()->id)).'. Saldo dipakai otomatis saat tagihan berikutnya terbit.')
            ->columns([
                TextColumn::make('occurred_on')->label('Tanggal')->date('j M Y'),
                TextColumn::make('type')
                    ->label('Mutasi')
                    ->description(fn (CreditTransaction $record): ?string => $record->invoice?->number !== null
                        ? "Tagihan {$record->invoice->number}"
                        : ($record->payment?->receipt_number !== null ? "Kuitansi {$record->payment->receipt_number}" : null)),
                MoneyColumn::make('amount')->label('Jumlah'),
            ])
            ->defaultSort('created_at')
            ->modifyQueryUsing(fn ($query) => $query->with(['invoice', 'payment']))
            ->headerActions([
                Action::make('applyCredit')
                    ->label('Pakai untuk tagihan')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->visible(fn (): bool => CreditLedger::balance($this->contract()->id) > 0
                        && User::current()->can('applyCreditIn', [Payment::class, $this->contract()->property()->firstOrFail()]))
                    ->requiresConfirmation()
                    ->modalDescription('Saldo kredit dipakai untuk tagihan kontrak ini yang belum lunas, mulai dari yang paling lama.')
                    ->action(function (Action $action): void {
                        DomainActions::forAction($action, fn () => app(ApplyCredit::class)->handle($this->contract()));

                        Notification::make()->success()->title('Saldo kredit dipakai')->send();
                    }),
            ])
            ->emptyStateHeading('Belum ada saldo kredit')
            ->emptyStateDescription('Kelebihan bayar tercatat di sini dan dipakai untuk tagihan berikutnya.');
    }

    private function contract(): Contract
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Contract ? $owner : throw new LogicException('Saldo kredit hanya untuk kontrak.');
    }
}
