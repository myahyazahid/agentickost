<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Events\CreditNoteIssued;
use App\Modules\Billing\Events\InvoiceIssued;
use App\Modules\Billing\Events\InvoiceVoided;
use App\Modules\Billing\Events\PenaltyAccrued;
use App\Modules\Billing\Events\PenaltyWaived;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Finance\Enums\AccountSubtype;
use App\Modules\Finance\Enums\JournalEvent;
use App\Modules\Finance\Journal\JournalLineDraft;
use App\Modules\Finance\Journal\JournalPoster;
use App\Modules\Finance\Journal\LedgerAccounts;
use App\Modules\Property\Enums\AllocationCategory;
use Illuminate\Events\Dispatcher;

/**
 * Journals for invoices (PRD §8.14): an issued invoice or penalty is owed by
 * the resident and earned; a credit note, void, or waiver takes that back.
 * The deposit component is left out until it is paid, since a deposit is
 * owed to the resident rather than earned (PRD §8.6).
 */
final class BillingJournals
{
    public function __construct(private readonly JournalPoster $journals) {}

    public function invoiceIssued(InvoiceIssued $event): void
    {
        $invoice = $event->invoice;

        // Opening arrears were earned before Agentic Kost; the opening balance
        // journal books them against opening equity (FR-ONB-05).
        if ($invoice->type === InvoiceType::Opening) {
            return;
        }

        $receivable = LedgerAccounts::id(AccountSubtype::Receivable);
        $lines = [];

        foreach ($invoice->items()->get() as $item) {
            /** @var InvoiceItem $item */
            if ($item->allocation_category === AllocationCategory::Deposit) {
                continue;
            }

            $lines[] = JournalLineDraft::debit($receivable, $item->amount, $invoice->contract_id);
            $lines[] = JournalLineDraft::credit(LedgerAccounts::revenueFor($item->allocation_category), $item->amount);
        }

        $this->journals->post(
            JournalEvent::InvoiceIssued,
            $invoice->issue_date ?? $invoice->property()->firstOrFail()->today(),
            "Tagihan {$invoice->number}",
            $invoice,
            $invoice->property()->firstOrFail(),
            $lines,
        );
    }

    public function penaltyAccrued(PenaltyAccrued $event): void
    {
        $penalty = $event->penalty;
        $invoice = $penalty->invoice()->firstOrFail();

        $this->journals->post(
            JournalEvent::PenaltyAccrued,
            $penalty->accrued_on,
            "Denda tagihan {$invoice->number}",
            $penalty,
            $invoice->property()->firstOrFail(),
            [
                JournalLineDraft::debit(LedgerAccounts::id(AccountSubtype::Receivable), $penalty->amount, $invoice->contract_id),
                JournalLineDraft::credit(LedgerAccounts::revenueFor(AllocationCategory::Penalty), $penalty->amount),
            ],
        );
    }

    public function penaltyWaived(PenaltyWaived $event): void
    {
        $penalty = $event->penalty;
        $invoice = $penalty->invoice()->firstOrFail();

        $this->journals->reverseAllFor(
            [$penalty],
            JournalEvent::PenaltyWaived,
            $invoice->property()->firstOrFail()->today(),
            "Denda tagihan {$invoice->number} dihapus",
        );
    }

    /**
     * A credit note on the deposit component has nothing to take back: the
     * deposit was never booked as owed.
     */
    public function creditNoteIssued(CreditNoteIssued $event): void
    {
        $note = $event->creditNote;

        if ($note->allocation_category === AllocationCategory::Deposit) {
            return;
        }

        $invoice = $note->invoice()->firstOrFail();

        $this->journals->post(
            JournalEvent::CreditNoteIssued,
            $note->issued_on,
            "Nota kredit {$note->number} untuk tagihan {$invoice->number}",
            $note,
            $invoice->property()->firstOrFail(),
            [
                JournalLineDraft::debit(LedgerAccounts::revenueFor($note->allocation_category), $note->amount),
                JournalLineDraft::credit(LedgerAccounts::id(AccountSubtype::Receivable), $note->amount, $invoice->contract_id),
            ],
        );
    }

    /**
     * Voiding waives the invoice's penalties as well, so their journals are
     * reversed with the invoice's.
     */
    public function invoiceVoided(InvoiceVoided $event): void
    {
        $invoice = $event->invoice;

        $this->journals->reverseAllFor(
            [$invoice, ...array_values($invoice->penalties()->get()->all())],
            JournalEvent::InvoiceVoided,
            $invoice->property()->firstOrFail()->today(),
            "Tagihan {$invoice->number} dibatalkan",
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            InvoiceIssued::class => 'invoiceIssued',
            PenaltyAccrued::class => 'penaltyAccrued',
            PenaltyWaived::class => 'penaltyWaived',
            CreditNoteIssued::class => 'creditNoteIssued',
            InvoiceVoided::class => 'invoiceVoided',
        ];
    }
}
