<?php

namespace App\Modules\Reports\Support;

use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\States\Invoice\Draft;
use App\Modules\Billing\States\Invoice\Voided;
use App\Modules\Payment\Models\Payment;
use App\Modules\Payment\Models\PaymentAllocation;
use App\Modules\Payment\States\Payment\Verified;
use App\Modules\Property\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The two pilot metrics of PRD §15.1 for the current tenant, over a range of
 * due dates or verification dates. Run inside a tenant context.
 *
 * - On-time payment: of the rent invoices that fell due in the range (and
 *   are past due today), the share paid in full on or before the due date.
 *   Money counts on the day it was paid, credit and deposit on the day they
 *   were used. Opening arrears are left out.
 * - Verification time: the median time from recording a payment to its
 *   verification, for payments that waited in the verification queue.
 *   Payments recorded by someone who may verify are verified in the same
 *   step and are left out, since nothing waited.
 */
final class PilotMetrics
{
    /**
     * @return array{due: int, on_time: int, percent: int|null}
     */
    public static function onTimePayment(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $invoices = Invoice::query()
            ->where('type', InvoiceType::Rent->value)
            ->whereNotIn('status', [Draft::$name, Voided::$name])
            ->whereDate('due_date', '>=', $from->toDateString())
            ->whereDate('due_date', '<=', $to->toDateString())
            ->with('property')
            ->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->property instanceof Property
                && $invoice->due_date->lessThan($invoice->property->today()));

        $allocations = PaymentAllocation::query()
            ->whereIn('invoice_id', $invoices->modelKeys())
            ->whereNull('reversed_at')
            ->with(['payment', 'creditTransaction', 'depositTransaction'])
            ->get()
            ->groupBy('invoice_id');

        $onTime = $invoices->filter(function (Invoice $invoice) use ($allocations): bool {
            $owed = $invoice->items_total_amount - $invoice->credited_amount;
            $dueDate = $invoice->due_date->toDateString();
            $timezone = $invoice->property instanceof Property ? $invoice->property->timezone->value : 'Asia/Jakarta';

            /** @var Collection<int, PaymentAllocation> $paid */
            $paid = $allocations->get($invoice->id, collect());
            $paidByDueDate = $paid
                ->filter(fn (PaymentAllocation $allocation): bool => self::paidOn($allocation, $timezone) <= $dueDate)
                ->sum('amount');

            return $owed > 0 && $paidByDueDate >= $owed;
        })->count();

        $due = $invoices->count();

        return [
            'due' => $due,
            'on_time' => $onTime,
            'percent' => $due > 0 ? (int) round($onTime / $due * 100) : null,
        ];
    }

    /**
     * @return array{verified: int, median_seconds: int|null}
     */
    public static function verificationTime(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $waits = Payment::query()
            ->where('status', Verified::$name)
            ->whereNotNull('verified_at')
            ->whereBetween('verified_at', [$from->startOfDay()->utc(), $to->endOfDay()->utc()])
            ->get(['created_at', 'verified_at'])
            // Verified in the same step it was recorded: it never waited.
            ->map(fn (Payment $payment): int => (int) $payment->created_at->diffInSeconds($payment->verified_at))
            ->filter(fn (int $seconds): bool => $seconds > 2)
            ->sort()
            ->values();

        return [
            'verified' => $waits->count(),
            'median_seconds' => $waits->isEmpty() ? null : (int) $waits->median(),
        ];
    }

    private static function paidOn(PaymentAllocation $allocation, string $timezone): string
    {
        return match (true) {
            $allocation->payment !== null => $allocation->payment->paid_at->timezone($timezone)->toDateString(),
            $allocation->creditTransaction !== null => $allocation->creditTransaction->occurred_on->toDateString(),
            $allocation->depositTransaction !== null => $allocation->depositTransaction->occurred_on->toDateString(),
            default => '9999-12-31',
        };
    }
}
