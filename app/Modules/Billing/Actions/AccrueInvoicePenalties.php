<?php

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Engine\PenaltyCalculator;
use App\Modules\Billing\Engine\PenaltyRules;
use App\Modules\Billing\Enums\InvoiceType;
use App\Modules\Billing\Events\PenaltyAccrued;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\PenaltyAccrual;
use App\Modules\Billing\Support\InvoiceBalance;
use App\Modules\Property\Enums\PenaltyType;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;

/**
 * Charges the late penalties an open invoice owes up to today (PRD §8.4),
 * each as its own record so the invoice lines never change. Safe to repeat.
 */
final class AccrueInvoicePenalties extends Action
{
    /**
     * @return list<PenaltyAccrual>
     */
    public function handle(Invoice $invoice): array
    {
        $this->authorize('update', $invoice);

        $property = $invoice->property()->firstOrFail();
        $settings = $property->resolvedSettings();

        // Arrears carried in at onboarding already include whatever the owner
        // charged before; adding penalties on top would count them twice.
        if ($settings->penalty_type === PenaltyType::None || $invoice->type === InvoiceType::Opening) {
            return [];
        }

        $rules = new PenaltyRules(
            $settings->penalty_type,
            $settings->grace_days,
            $settings->penalty_amount,
            $settings->penalty_percent,
            $settings->penalty_max_amount,
        );
        $today = $property->today();

        return $this->transaction(function () use ($invoice, $rules, $today): array {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $invoice->status->isOpen()) {
                return [];
            }

            $existing = $invoice->penalties()->get();
            $drafts = (new PenaltyCalculator($rules))->accrue(
                CarbonImmutable::parse($invoice->due_date->toDateString()),
                $today,
                self::principalOutstanding($invoice),
                array_values($existing->map(fn (PenaltyAccrual $penalty): CarbonImmutable => CarbonImmutable::parse($penalty->accrued_on->toDateString()))->all()),
                (int) $existing->whereNull('waived_at')->sum('amount'),
            );

            if ($drafts === []) {
                return [];
            }

            $snapshot = [
                'type' => $rules->type->value,
                'grace_days' => $rules->graceDays,
                'amount' => $rules->amount,
                'percent' => $rules->percent,
                'max_amount' => $rules->maxAmount,
            ];
            $accruals = [];

            foreach ($drafts as $draft) {
                $accruals[] = PenaltyAccrual::create([
                    'invoice_id' => $invoice->id,
                    'accrued_on' => $draft->date,
                    'amount' => $draft->amount,
                    'rule_snapshot' => $snapshot,
                ]);
                $invoice->penalty_amount += $draft->amount;
            }

            InvoiceBalance::sync($invoice);

            foreach ($accruals as $accrual) {
                PenaltyAccrued::dispatch($accrual);
            }

            return $accruals;
        });
    }

    /**
     * What is still unpaid of the invoice itself, penalties left out. Until
     * payments are allocated per category (M1.4), payments count towards
     * the invoice lines first, which matches the default order (PRD §8.5).
     */
    public static function principalOutstanding(Invoice $invoice): int
    {
        return max(0, $invoice->items_total_amount - $invoice->credited_amount - $invoice->paid_amount);
    }
}
