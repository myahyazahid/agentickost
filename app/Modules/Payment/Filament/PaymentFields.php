<?php

namespace App\Modules\Payment\Filament;

use App\Modules\Access\Models\User;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Support\CreditLedger;
use App\Modules\Property\Models\Property;
use App\Support\Filament\MoneyInput;
use App\Support\Money\Rupiah;
use Closure;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Form pieces shared by the record page and the verify dialog.
 */
final class PaymentFields
{
    /**
     * Automatic allocation, or a list of invoices and amounts (FR-PAY-04).
     *
     * @param  Closure(Get): ?string  $contractId
     * @return list<Component>
     */
    public static function allocation(Closure $contractId): array
    {
        return [
            Radio::make('allocation_mode')
                ->label('Alokasi')
                ->options([
                    'auto' => 'Otomatis: tagihan paling lama dulu',
                    'manual' => 'Pilih tagihan sendiri',
                ])
                ->default('auto')
                ->helperText('Uang yang tersisa setelah tagihan lunas disimpan sebagai saldo kredit dan dipakai untuk tagihan berikutnya.')
                ->live(),
            Repeater::make('allocations')
                ->hiddenLabel()
                ->table([
                    TableColumn::make('Tagihan'),
                    TableColumn::make('Jumlah')->width('35%'),
                ])
                ->schema([
                    Select::make('invoice_id')
                        ->options(fn (Get $get): array => self::openInvoiceOptions($contractId($get)))
                        ->required()
                        ->native(false),
                    MoneyInput::make('amount')->required()->minValue(1),
                ])
                ->defaultItems(1)
                ->addActionLabel('Tambah tagihan')
                ->visible(fn (Get $get): bool => $get('allocation_mode') === 'manual'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function allocationInput(array $data): array
    {
        return ($data['allocation_mode'] ?? 'auto') === 'manual' ? ['allocations' => array_values($data['allocations'] ?? [])] : [];
    }

    /**
     * @return array<string, string>
     */
    public static function openInvoiceOptions(?string $contractId): array
    {
        return self::openInvoices($contractId)
            ->mapWithKeys(fn (Invoice $invoice): array => [
                $invoice->id => "{$invoice->number}, jatuh tempo {$invoice->due_date->translatedFormat('j M Y')}, sisa ".Rupiah::format($invoice->balance_amount),
            ])
            ->all();
    }

    /**
     * What the contract still owes and has as credit, for the record form.
     */
    public static function contractSummary(?string $contractId): string
    {
        if ($contractId === null) {
            return 'Pilih kontrak untuk melihat tagihan yang belum lunas.';
        }

        $invoices = self::openInvoices($contractId);
        $credit = CreditLedger::balance($contractId);
        $creditNote = $credit > 0 ? ' Saldo kredit '.Rupiah::format($credit).'.' : '';

        if ($invoices->isEmpty()) {
            return 'Tidak ada tagihan yang menunggu pembayaran. Uang yang dicatat disimpan sebagai saldo kredit.'.$creditNote;
        }

        $owed = (int) $invoices->sum('balance_amount');

        return "{$invoices->count()} tagihan belum lunas, total sisa ".Rupiah::format($owed).'.'.$creditNote;
    }

    /**
     * @return Collection<int, Invoice>
     */
    private static function openInvoices(?string $contractId): Collection
    {
        return Invoice::query()
            ->accessibleBy(User::current())
            ->where('contract_id', $contractId ?? '')
            ->whereIn('status', InvoiceState::openValues())
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public static function bankAccountOptions(?string $propertyId): array
    {
        // The property comes from form state; a property the user cannot see
        // only gets the accounts shared by every property.
        if ($propertyId !== null && ! Property::query()->accessibleBy(User::current())->whereKey($propertyId)->exists()) {
            $propertyId = null;
        }

        return BankAccount::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('property_id')->orWhere('property_id', $propertyId ?? ''))
            ->orderByDesc('is_default')
            ->orderBy('provider_name')
            ->get()
            ->mapWithKeys(fn (BankAccount $account): array => [$account->id => $account->displayName()])
            ->all();
    }

    public static function defaultBankAccount(?string $propertyId): ?string
    {
        $options = self::bankAccountOptions($propertyId);

        return array_key_first($options);
    }

    /**
     * Who can have received cash: active staff of the property. Staff who
     * cannot verify only see themselves.
     *
     * @return array<string, string>
     */
    public static function receiverOptions(?string $propertyId, bool $anyone): array
    {
        $user = User::current();

        if (! $anyone) {
            return [$user->id => $user->name];
        }

        $property = Property::query()->find($propertyId);

        if ($property === null) {
            return [];
        }

        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->filter(fn (User $staff): bool => $property->isAccessibleBy($staff))
            ->mapWithKeys(fn (User $staff): array => [$staff->id => $staff->name])
            ->all();
    }

    public static function contract(?string $contractId): ?Contract
    {
        return $contractId === null ? null : Contract::query()->accessibleBy(User::current())->find($contractId);
    }
}
