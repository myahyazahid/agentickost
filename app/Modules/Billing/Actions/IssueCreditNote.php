<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Events\CreditNoteIssued;
use App\Modules\Billing\Models\CreditNote;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Billing\States\Invoice\Voided;
use App\Modules\Billing\Support\InvoiceBalance;
use App\Modules\Documents\Enums\DocumentType;
use App\Modules\Documents\Support\DocumentNumbers;
use App\Modules\Property\Enums\AllocationCategory;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reduces an issued invoice, paid or not, with a reason (PRD §8.10). The
 * amount is limited to what the invoice still charges in that category.
 */
final class IssueCreditNote extends Action
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly ActorContext $actors,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Invoice $invoice, array $input): CreditNote
    {
        $this->authorize('credit', $invoice);

        $data = $this->validate($input, [
            'allocation_category' => ['required', Rule::enum(AllocationCategory::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $category = AllocationCategory::from($data['allocation_category']);

        return $this->transaction(function () use ($invoice, $category, $data): CreditNote {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status->equals(Draft::class, Voided::class)) {
                throw ValidationException::withMessages(['amount' => 'Nota kredit hanya untuk tagihan yang sudah terbit.']);
            }

            $creditable = self::creditable($invoice, $category);

            if ($data['amount'] > $creditable) {
                throw ValidationException::withMessages([
                    'amount' => 'Nota kredit untuk '.mb_strtolower($category->getLabel()).' paling banyak '.number_format($creditable, 0, ',', '.').'.',
                ]);
            }

            $property = $invoice->property()->firstOrFail();
            $today = $property->today();
            $actor = $this->actors->current();

            $note = CreditNote::create([
                'invoice_id' => $invoice->id,
                'number' => $this->numbers->next(DocumentType::CreditNote, $today, $property->code),
                'allocation_category' => $category,
                'amount' => $data['amount'],
                'reason' => $data['reason'],
                'issued_on' => $today,
                'created_by' => $actor->type === ActorType::User ? $actor->id : null,
            ]);

            $invoice->credited_amount += $data['amount'];
            InvoiceBalance::sync($invoice);

            CreditNoteIssued::dispatch($note);

            return $note;
        });
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
