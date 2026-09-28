<?php

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Events\CreditNoteIssued;
use App\Modules\Billing\Models\CreditNote;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Billing\States\Invoice\Voided;
use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Support\DocumentNumbers;
use App\Modules\Property\Enums\AllocationCategory;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use App\Support\Money\Rupiah;
use Illuminate\Validation\ValidationException;

/**
 * Issues credit notes (PRD §8.10), for the IssueCreditNote action and for
 * other modules' actions such as a room move. Call inside a transaction
 * holding the invoice row lock.
 */
final class CreditNotes
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  Extra columns, such as room_move_id
     *
     * @throws ValidationException when the invoice is not issued or the amount is above what it charges
     */
    public function issue(Invoice $invoice, AllocationCategory $category, int $amount, string $reason, array $attributes = []): CreditNote
    {
        if ($invoice->status->equals(Draft::class, Voided::class)) {
            throw ValidationException::withMessages(['amount' => 'Nota kredit hanya untuk tagihan yang sudah terbit.']);
        }

        $creditable = self::creditable($invoice, $category);

        if ($amount > $creditable) {
            throw ValidationException::withMessages([
                'amount' => 'Nota kredit untuk '.mb_strtolower($category->getLabel()).' paling banyak '.Rupiah::format($creditable).'.',
            ]);
        }

        $property = $invoice->property()->firstOrFail();
        $today = $property->today();
        $actor = $this->actors->current();

        $note = CreditNote::create([
            'invoice_id' => $invoice->id,
            'number' => $this->numbers->next(DocumentType::CreditNote, $today, $property->code),
            'allocation_category' => $category,
            'amount' => $amount,
            'reason' => $reason,
            'issued_on' => $today,
            'created_by' => $actor->type === ActorType::User ? $actor->id : null,
            ...$attributes,
        ]);

        $invoice->credited_amount += $amount;
        InvoiceBalance::sync($invoice);

        CreditNoteIssued::dispatch($note);

        return $note;
    }

    /**
     * What the invoice charges in a category, less earlier credit notes on it.
     */
    public static function creditable(Invoice $invoice, AllocationCategory $category): int
    {
        $charged = $category === AllocationCategory::Penalty
            ? $invoice->penalty_amount
            : (int) $invoice->items()->where('allocation_category', $category->value)->sum('amount');

        $credited = (int) $invoice->creditNotes()->where('allocation_category', $category->value)->sum('amount');

        return max(0, $charged - $credited);
    }
}
