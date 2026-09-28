<?php

namespace App\Modules\Payment\Support;

use App\Modules\Billing\Models\CreditNote;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\InvoiceItem;
use App\Modules\Billing\States\Invoice\InvoiceState;
use App\Modules\Lease\Models\Contract;
use App\Modules\Payment\Engine\OpenInvoice;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Property\Enums\AllocationCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The invoices of a contract that still expect money, and what each still
 * owes per allocation category: charged, less credit notes, less what is
 * already allocated.
 */
final class OpenInvoices
{
    /**
     * Oldest first, locked until the transaction ends (schema §14.1).
     *
     * @return array<string, Invoice>
     */
    public static function lockForContract(string $contractId): array
    {
        return Invoice::query()
            ->where('contract_id', $contractId)
            ->whereIn('status', InvoiceState::openValues())
            ->orderBy('due_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * @param  array<string, Invoice>  $invoices
     * @return list<OpenInvoice>
     */
    public static function describe(array $invoices): array
    {
        if ($invoices === []) {
            return [];
        }

        $ids = array_keys($invoices);
        $charged = self::sumsByCategory(InvoiceItem::query(), $ids);
        $credited = self::sumsByCategory(CreditNote::query(), $ids);
        $allocated = self::sumsByCategory(PaymentAllocation::query()->active(), $ids);
        $described = [];

        foreach ($invoices as $id => $invoice) {
            $outstanding = [];

            foreach (AllocationCategory::cases() as $category) {
                $key = $category->value;
                $charge = $category === AllocationCategory::Penalty ? $invoice->penalty_amount : ($charged[$id][$key] ?? 0);
                $outstanding[$key] = $charge - ($credited[$id][$key] ?? 0) - ($allocated[$id][$key] ?? 0);
            }

            $described[] = new OpenInvoice($id, CarbonImmutable::parse($invoice->due_date->toDateString()), $outstanding);
        }

        return $described;
    }

    /**
     * What one invoice still owes per category, for forms and pages.
     *
     * @return array<string, int>
     */
    public static function outstandingOf(Invoice $invoice): array
    {
        return self::describe([$invoice->id => $invoice])[0]->outstanding;
    }

    /**
     * The contract's property decides the order inside an invoice (PRD §8.5).
     *
     * @return list<AllocationCategory>
     */
    public static function order(Contract $contract): array
    {
        return $contract->property()->firstOrFail()->resolvedSettings()->allocationOrder();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $invoiceIds
     * @return array<string, array<string, int>>
     */
    private static function sumsByCategory(Builder $query, array $invoiceIds): array
    {
        $sums = [];

        $rows = $query->whereIn('invoice_id', $invoiceIds)
            ->groupBy('invoice_id', 'allocation_category')
            ->selectRaw('invoice_id, allocation_category, SUM(amount) AS total')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $sums[(string) $row->invoice_id][(string) $row->allocation_category] = (int) $row->total;
        }

        return $sums;
    }
}
