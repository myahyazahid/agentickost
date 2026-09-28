<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Enums\OpeningBalanceKind;
use App\Modules\Finance\Models\DepositTransaction;
use App\Modules\Finance\Models\OpeningBalance;
use App\Modules\Lease\Models\Contract;
use App\Modules\Lease\States\Contract\ContractState;
use App\Modules\Payment\Enums\CreditTransactionType;
use App\Modules\Payment\Models\CreditTransaction;
use App\Modules\Property\Models\Property;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a draft opening balance (FR-ONB-04). Nothing reaches the
 * ledgers until it is posted, so a draft can be corrected freely while the
 * owner checks it against the old books.
 */
final class SaveOpeningBalance extends Action
{
    /**
     * @param  array<string, mixed>  $input  cutoff_date and lines of {kind, contract_id|account_id, amount, note}
     */
    public function handle(array $input, ?OpeningBalance $draft = null): OpeningBalance
    {
        $draft === null
            ? $this->authorize('create', OpeningBalance::class)
            : $this->authorize('update', $draft);

        $data = $this->validate($input, [
            'cutoff_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.kind' => ['required', Rule::enum(OpeningBalanceKind::class)],
            'lines.*.contract_id' => ['nullable', 'string'],
            'lines.*.account_id' => ['nullable', 'string'],
            'lines.*.amount' => ['required', 'integer', 'min:1'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $cutoff = CarbonImmutable::parse($data['cutoff_date']);
        self::ensureNotInFuture($cutoff);

        /** @var list<array<string, mixed>> $lines */
        $lines = array_values($data['lines']);
        self::ensureValidLines($lines);

        return $this->transaction(function () use ($draft, $cutoff, $lines): OpeningBalance {
            $balance = $draft ?? new OpeningBalance;
            $balance->cutoff_date = Carbon::parse($cutoff->toDateString());
            $balance->save();

            $balance->lines()->get()->each->delete();

            foreach ($lines as $line) {
                $kind = OpeningBalanceKind::from($line['kind']);

                $balance->lines()->create([
                    'kind' => $kind,
                    'contract_id' => $kind->isPerContract() ? $line['contract_id'] : null,
                    'account_id' => $kind->isPerContract() ? null : $line['account_id'],
                    'amount' => $line['amount'],
                    'note' => $line['note'] ?? null,
                ]);
            }

            return $balance;
        });
    }

    /**
     * The cut-off date has passed in at least one property.
     */
    public static function ensureNotInFuture(CarbonImmutable $cutoff): void
    {
        $latestToday = Property::query()->get()
            ->map(fn (Property $property): CarbonImmutable => $property->today())
            ->max() ?? CarbonImmutable::today();

        if ($cutoff->greaterThan($latestToday)) {
            throw ValidationException::withMessages([
                'cutoff_date' => 'Tanggal cut-off tidak boleh di masa depan.',
            ]);
        }
    }

    /**
     * Each amount points at a running contract or a cash or bank account,
     * appears once, and is not already carried in by an earlier opening
     * balance.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public static function ensureValidLines(array $lines): void
    {
        $contracts = Contract::query()
            ->whereKey(array_filter(array_column($lines, 'contract_id')))
            ->with('room')
            ->get()
            ->keyBy('id');
        $accounts = RefundDeposit::payoutAccounts()->pluck('name', 'id');
        $seen = [];

        foreach ($lines as $line) {
            $kind = OpeningBalanceKind::from($line['kind']);

            if (! $kind->isPerContract()) {
                $name = $accounts->get($line['account_id'] ?? '') ?? self::fail('Pilih akun kas atau rekening untuk saldo kas dan bank.');
                $key = "cash|{$line['account_id']}";

                if (isset($seen[$key])) {
                    self::fail("Saldo {$name} dicatat dua kali.");
                }

                $seen[$key] = true;

                continue;
            }

            /** @var Contract|null $contract */
            $contract = $contracts->get($line['contract_id'] ?? '');

            if ($contract === null || ! in_array($contract->status->getValue(), ContractState::runningValues(), true)) {
                self::fail("Pilih kontrak yang sedang berjalan untuk {$kind->getLabel()}.");
            }

            $label = self::contractLabel($contract);
            $key = "{$kind->value}|{$contract->id}";

            if (isset($seen[$key])) {
                self::fail("{$kind->getLabel()} {$label} dicatat dua kali.");
            }

            $seen[$key] = true;

            self::ensureNotCarriedIn($kind, $contract, $label);
        }

        foreach ($contracts as $contract) {
            if (isset($seen["receivable|{$contract->id}"], $seen["credit|{$contract->id}"])) {
                self::fail(self::contractLabel($contract).' punya tunggakan dan saldo kredit sekaligus. Catat selisihnya saja.');
            }
        }
    }

    private static function ensureNotCarriedIn(OpeningBalanceKind $kind, Contract $contract, string $label): void
    {
        if ($kind === OpeningBalanceKind::Deposit && ! $contract->isImported()) {
            self::fail("{$label} dibuat langsung di KostPilot, jadi deposit-nya ditagih di tagihan pertama. Deposit awal hanya untuk kontrak yang diimpor.");
        }

        $exists = match ($kind) {
            OpeningBalanceKind::Receivable => Invoice::query()->where('contract_id', $contract->id)->where('type', InvoiceType::Opening->value)->exists(),
            OpeningBalanceKind::Deposit => DepositTransaction::query()->where('contract_id', $contract->id)->where('type', DepositTransactionType::Opening->value)->exists(),
            OpeningBalanceKind::Credit => CreditTransaction::query()->where('contract_id', $contract->id)->where('type', CreditTransactionType::Opening->value)->exists(),
            OpeningBalanceKind::Cash => false,
        };

        if ($exists) {
            self::fail("{$kind->getLabel()} {$label} sudah pernah dicatat di saldo awal sebelumnya.");
        }
    }

    public static function contractLabel(Contract $contract): string
    {
        return "kamar {$contract->room?->number} ({$contract->number})";
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['lines' => $message]);
    }
}
