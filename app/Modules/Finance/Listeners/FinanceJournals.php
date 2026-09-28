<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Events\DepositDeducted;
use App\Modules\Finance\Events\DepositRefunded;
use App\Modules\Finance\Events\DepositTransferred;
use App\Modules\Finance\Events\ExpenseRecorded;
use App\Modules\Finance\Events\ExpenseVoided;
use App\Modules\Finance\Journal\JournalLineDraft;
use App\Modules\Finance\Journal\JournalPoster;
use App\Modules\Finance\Journal\LedgerAccounts;
use App\Modules\Lease\Models\Contract;
use Illuminate\Events\Dispatcher;

/**
 * Journals for deposits and expenses (PRD §8.14).
 */
final class FinanceJournals
{
    public function __construct(private readonly JournalPoster $journals) {}

    /**
     * Deposit kept as income, or used to pay an invoice of the contract.
     */
    public function depositDeducted(DepositDeducted $event): void
    {
        $entry = $event->entry;
        $contract = $entry->contract()->firstOrFail();
        $amount = -$entry->amount;
        $invoiceNumber = $entry->invoice()->value('number');

        $this->journals->post(
            JournalEvent::DepositDeducted,
            $entry->occurred_on,
            $invoiceNumber !== null ? "Deposit dipakai untuk tagihan {$invoiceNumber}" : "Potongan deposit: {$entry->reason}",
            $entry,
            $contract->property()->firstOrFail(),
            [
                JournalLineDraft::debit(LedgerAccounts::id(AccountSubtype::DepositLiability), $amount, $contract->id),
                $entry->invoice_id !== null
                    ? JournalLineDraft::credit(LedgerAccounts::id(AccountSubtype::Receivable), $amount, $contract->id)
                    : JournalLineDraft::credit(LedgerAccounts::id(AccountSubtype::OtherRevenue), $amount),
            ],
        );
    }

    public function depositRefunded(DepositRefunded $event): void
    {
        $entry = $event->entry;
        $contract = $entry->contract()->firstOrFail();

        $this->journals->post(
            JournalEvent::DepositRefunded,
            $entry->occurred_on,
            "Deposit kontrak {$contract->number} dikembalikan",
            $entry,
            $contract->property()->firstOrFail(),
            [
                JournalLineDraft::debit(LedgerAccounts::id(AccountSubtype::DepositLiability), -$entry->amount, $contract->id),
                JournalLineDraft::credit((string) $entry->account_id, -$entry->amount),
            ],
        );
    }

    /**
     * Moves the liability between the two contracts' sub-ledgers, each line
     * on its own contract's property.
     */
    public function depositTransferred(DepositTransferred $event): void
    {
        $entry = $event->entry;
        $from = $entry->contract()->firstOrFail();
        $to = Contract::query()->whereKey($entry->related_contract_id)->firstOrFail();
        $liability = LedgerAccounts::id(AccountSubtype::DepositLiability);

        $this->journals->post(
            JournalEvent::DepositTransferred,
            $entry->occurred_on,
            "Deposit dipindah dari kontrak {$from->number} ke ".($to->number ?? 'kontrak draf'),
            $entry,
            $from->property()->firstOrFail(),
            [
                JournalLineDraft::debit($liability, -$entry->amount, $from->id, $from->property_id),
                JournalLineDraft::credit($liability, -$entry->amount, $to->id, $to->property_id),
            ],
        );
    }

    public function expenseRecorded(ExpenseRecorded $event): void
    {
        $expense = $event->expense;

        $this->journals->post(
            JournalEvent::ExpenseRecorded,
            $expense->spent_on,
            "Pengeluaran: {$expense->description}",
            $expense,
            $expense->property()->firstOrFail(),
            [
                JournalLineDraft::debit($expense->expense_account_id, $expense->amount),
                JournalLineDraft::credit($expense->paid_from_account_id, $expense->amount),
            ],
        );
    }

    public function expenseVoided(ExpenseVoided $event): void
    {
        $expense = $event->expense;

        $this->journals->reverseAllFor(
            [$expense],
            JournalEvent::ExpenseVoided,
            $expense->property()->firstOrFail()->today(),
            "Pengeluaran dibatalkan: {$expense->description}",
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            DepositDeducted::class => 'depositDeducted',
            DepositRefunded::class => 'depositRefunded',
            DepositTransferred::class => 'depositTransferred',
            ExpenseRecorded::class => 'expenseRecorded',
            ExpenseVoided::class => 'expenseVoided',
        ];
    }
}
