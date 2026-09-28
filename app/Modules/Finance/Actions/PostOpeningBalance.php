<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Billing\Support\OpeningInvoices;
use App\Modules\Finance\Enums\DepositTransactionType;
use App\Modules\Finance\Enums\OpeningBalanceKind;
use App\Modules\Finance\Events\OpeningBalancePosted;
use App\Modules\Finance\Models\JournalEntry;
use App\Modules\Finance\Models\OpeningBalance;
use App\Modules\Finance\Models\OpeningBalanceLine;
use App\Modules\Finance\States\OpeningBalance\Posted;
use App\Modules\Finance\Support\DepositLedger;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Enums\CreditTransactionType;
use App\Modules\Payment\Support\CreditLedger;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use App\Support\States\StateTransition;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Carries an opening balance into the ledgers (FR-ONB-04, FR-ONB-05), all
 * dated on the cut-off date: arrears become opening invoices that payments
 * can settle, deposits and credit open their contract ledgers, and one
 * opening journal books everything against opening equity.
 */
final class PostOpeningBalance extends Action
{
    public function __construct(
        private readonly OpeningInvoices $invoices,
        private readonly DepositLedger $deposits,
        private readonly CreditLedger $credit,
        private readonly ActorContext $actors,
    ) {}

    public function handle(OpeningBalance $openingBalance): OpeningBalance
    {
        $this->authorize('post', $openingBalance);

        return $this->transaction(function () use ($openingBalance): OpeningBalance {
            $balance = OpeningBalance::query()->whereKey($openingBalance->id)->lockForUpdate()->firstOrFail();

            if (! $balance->isDraft()) {
                throw ValidationException::withMessages(['status' => 'Saldo awal ini sudah diposting.']);
            }

            $lines = $balance->lines()->orderBy('id')->get();
            $cutoff = CarbonImmutable::parse($balance->cutoff_date->toDateString());

            SaveOpeningBalance::ensureValidLines(array_values($lines->map(fn (OpeningBalanceLine $line): array => [
                'kind' => $line->kind->value,
                'contract_id' => $line->contract_id,
                'account_id' => $line->account_id,
            ])->all()));

            foreach ($lines as $line) {
                $this->carryIn($line, $cutoff);
            }

            OpeningBalancePosted::dispatch($balance);

            $actor = $this->actors->current();
            $balance->journal_entry_id = JournalEntry::query()
                ->where('source_type', $balance->getMorphClass())
                ->where('source_id', $balance->id)
                ->value('id');
            $balance->posted_by = $actor->type === ActorType::User ? $actor->id : null;
            $balance->posted_at = now();
            StateTransition::to($balance->status, Posted::class);

            return $balance;
        });
    }

    private function carryIn(OpeningBalanceLine $line, CarbonImmutable $cutoff): void
    {
        if ($line->kind === OpeningBalanceKind::Cash) {
            return;
        }

        $contract = Contract::query()->whereKey($line->contract_id)->lockForUpdate()->firstOrFail();

        match ($line->kind) {
            OpeningBalanceKind::Receivable => $this->invoices->issue($contract, $line->amount, $cutoff, $line->note),
            OpeningBalanceKind::Deposit => $this->deposits->record($contract, DepositTransactionType::Opening, $line->amount, [
                'occurred_on' => $cutoff,
                'reason' => $line->note,
            ]),
            OpeningBalanceKind::Credit => $this->credit->record($contract, CreditTransactionType::Opening, $line->amount, [
                'occurred_on' => $cutoff,
            ]),
        };
    }
}
