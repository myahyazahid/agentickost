<?php

namespace App\Modules\Finance\Filament\App\RelationManagers;

use App\Modules\Access\Models\User;
use App\Modules\Documents\Enums\AttachmentCollection;
use App\Modules\Documents\Filament\AttachmentUpload;
use App\Modules\Finance\Actions\DeductDeposit;
use App\Modules\Finance\Actions\RefundDeposit;
use App\Modules\Finance\Actions\TransferDeposit;
use App\Modules\Finance\Models\Account;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Lease\States\Contract\Draft;
use App\Support\Filament\DomainActions;
use App\Support\Filament\MoneyColumn;
use App\Support\Filament\MoneyInput;
use App\Support\Money\Rupiah;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The contract's deposit ledger (FR-DEP-01): received through its invoices,
 * then deducted, refunded, or moved by the owner.
 */
class DepositTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'depositTransactions';

    protected static ?string $title = 'Deposit';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Contract
            && User::current()->can('viewAny', DepositTransaction::class)
            && User::current()->can('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(fn (): string => 'Deposit dipegang '.Rupiah::format($this->held()).', disepakati '.Rupiah::format($this->contract()->deposit_amount).'.')
            ->columns([
                TextColumn::make('occurred_on')->label('Tanggal')->date('j M Y'),
                TextColumn::make('type')
                    ->label('Mutasi')
                    ->description(fn (DepositTransaction $record): ?string => self::detail($record)),
                MoneyColumn::make('amount')->label('Jumlah'),
            ])
            ->defaultSort('created_at')
            ->modifyQueryUsing(fn ($query) => $query->with(['relatedContract', 'invoice', 'account']))
            ->headerActions([
                $this->deductAction(),
                $this->refundAction(),
                $this->transferAction(),
            ])
            ->emptyStateHeading('Belum ada deposit')
            ->emptyStateDescription('Deposit tercatat di sini saat bagian deposit di tagihan pertama dibayar.');
    }

    private function deductAction(): Action
    {
        return Action::make('deduct')
            ->label('Potong')
            ->icon(Heroicon::OutlinedScissors)
            ->visible(fn (): bool => $this->canManage() && $this->held() > 0)
            ->modalHeading('Potong deposit')
            ->modalDescription('Bagian yang dipotong menjadi pendapatan, misalnya untuk kerusakan. Untuk melunasi tagihan, buka tagihannya lalu pilih Bayar dari deposit.')
            ->schema([
                MoneyInput::make('amount')->label('Jumlah')->required()->minValue(1),
                Textarea::make('reason')->label('Alasan')->placeholder('Misal: cat dinding rusak')->required()->minLength(5),
                AttachmentUpload::make('photos', AttachmentCollection::Photo)->label('Foto')->maxFiles(5),
            ])
            ->modalSubmitActionLabel('Potong deposit')
            ->action(function (Action $action, array $data): void {
                DomainActions::forAction($action, fn () => app(DeductDeposit::class)->handle($this->contract(), $data));
            });
    }

    private function refundAction(): Action
    {
        return Action::make('refund')
            ->label('Kembalikan')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->visible(fn (): bool => $this->canManage() && $this->held() > 0)
            ->modalHeading('Kembalikan deposit')
            ->schema([
                MoneyInput::make('amount')->label('Jumlah')->default(fn (): int => $this->held())->required()->minValue(1),
                Select::make('account_id')
                    ->label('Dibayar dari')
                    ->options(fn (): array => RefundDeposit::payoutAccounts()->pluck('name', 'id')->all())
                    ->required()
                    ->native(false),
                Textarea::make('reason')->label('Catatan')->placeholder('Misal: ditransfer ke rekening penghuni'),
            ])
            ->modalSubmitActionLabel('Kembalikan deposit')
            ->action(function (Action $action, array $data): void {
                DomainActions::forAction($action, fn () => app(RefundDeposit::class)->handle($this->contract(), $data));
            });
    }

    private function transferAction(): Action
    {
        return Action::make('transfer')
            ->label('Pindahkan')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->visible(fn (): bool => $this->canManage() && $this->held() > 0)
            ->modalHeading('Pindahkan deposit ke kontrak lain')
            ->modalDescription('Misalnya ke kontrak perpanjangan atau kontrak kamar baru.')
            ->schema([
                Select::make('to_contract_id')
                    ->label('Kontrak tujuan')
                    ->options(fn (): array => $this->transferTargets())
                    ->searchable()
                    ->required(),
                MoneyInput::make('amount')->label('Jumlah')->default(fn (): int => $this->held())->required()->minValue(1),
                Textarea::make('reason')->label('Catatan'),
            ])
            ->modalSubmitActionLabel('Pindahkan deposit')
            ->action(function (Action $action, array $data): void {
                DomainActions::forAction($action, fn () => app(TransferDeposit::class)->handle($this->contract(), $data));
            });
    }

    /**
     * @return array<string, string>
     */
    private function transferTargets(): array
    {
        $contract = $this->contract();

        return Contract::query()
            ->accessibleBy(User::current())
            ->whereKeyNot($contract->id)
            ->whereIn('status', [Draft::$name, ...ContractState::runningValues()])
            ->with(['room', 'payer'])
            ->orderByRaw('payer_id = ? DESC', [$contract->payer_id])
            ->orderByDesc('start_date')
            ->limit(200)
            ->get()
            ->mapWithKeys(fn (Contract $other): array => [
                $other->id => "Kamar {$other->room?->number}, {$other->payer?->name} (".($other->number ?? 'draf').", {$other->status->getLabel()})",
            ])
            ->all();
    }

    private static function detail(DepositTransaction $entry): ?string
    {
        $parts = array_filter([
            $entry->relatedContract !== null ? ($entry->amount < 0 ? 'Ke kontrak ' : 'Dari kontrak ').($entry->relatedContract->number ?? 'draf') : null,
            $entry->invoice?->number !== null ? "Tagihan {$entry->invoice->number}" : null,
            $entry->account instanceof Account ? "Dari {$entry->account->name}" : null,
            $entry->reason,
        ]);

        return $parts === [] ? null : implode('. ', $parts);
    }

    private function held(): int
    {
        return DepositLedger::balance($this->contract()->id);
    }

    private function canManage(): bool
    {
        return User::current()->can('manageFor', [DepositTransaction::class, $this->contract()]);
    }

    private function contract(): Contract
    {
        $owner = $this->getOwnerRecord();

        return $owner instanceof Contract ? $owner : throw new LogicException('Deposit hanya untuk kontrak.');
    }
}
