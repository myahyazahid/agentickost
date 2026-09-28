<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Models\CreditNote;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Support\CreditNotes;
use App\Modules\Property\Enums\AllocationCategory;
use App\Support\Actions\Action;
use Illuminate\Validation\Rule;

/**
 * Reduces an issued invoice, paid or not, with a reason (PRD §8.10). The
 * amount is limited to what the invoice still charges in that category.
 */
final class IssueCreditNote extends Action
{
    public function __construct(private readonly CreditNotes $creditNotes) {}

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

        return $this->transaction(function () use ($invoice, $data): CreditNote {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            return $this->creditNotes->issue(
                $invoice,
                AllocationCategory::from($data['allocation_category']),
                (int) $data['amount'],
                $data['reason'],
            );
        });
    }

    /**
     * What the invoice charges in a category, less earlier credit notes on it.
     */
    public static function creditable(Invoice $invoice, AllocationCategory $category): int
    {
        return CreditNotes::creditable($invoice, $category);
    }
}
