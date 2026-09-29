<?php

namespace App\Modules\Subscription\Support;

use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionInvoice;
use App\Modules\Subscription\States\Subscription\Active;
use App\Modules\Subscription\States\Subscription\Cancelled;
use App\Modules\Subscription\States\Subscription\Grace;
use App\Modules\Subscription\States\Subscription\Restricted;
use App\Modules\Subscription\States\Subscription\Trial;
use App\Modules\Subscription\States\SubscriptionInvoice\Unpaid;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Money\Rupiah;

/**
 * What the owner should know about the subscription right now, if
 * anything: the trial countdown, an unpaid invoice, or a lapsed
 * subscription. Shown on the dashboard.
 */
final readonly class SubscriptionNotice
{
    public function __construct(
        public string $heading,
        public string $description,
        public bool $urgent = false,
    ) {}

    public static function current(): ?self
    {
        $tenant = app(TenantContext::class)->tenant();
        $subscription = CurrentSubscription::get();

        return match (true) {
            $subscription->status->equals(Trial::class) => self::trial($tenant, $subscription),
            $subscription->status->equals(Grace::class) => new self(
                'Masa langganan sudah habis',
                'Bayar sebelum '.$subscription->grace_ends_at?->timezone($tenant->default_timezone)->subSecond()->translatedFormat('j F Y').' supaya akun tidak menjadi baca saja.',
                urgent: true,
            ),
            $subscription->status->equals(Restricted::class) => new self(
                'Akun dalam mode baca saja',
                'Data masih bisa dilihat dan diekspor sampai '.$subscription->read_only_since?->timezone($tenant->default_timezone)->addDays(BillingSettings::readOnlyDays())->translatedFormat('j F Y').'. Bayar tagihan langganan untuk mengubah data lagi.',
                urgent: true,
            ),
            $subscription->status->equals(Cancelled::class) => new self(
                'Langganan dihentikan',
                'Layanan berjalan sampai '.$subscription->current_period_end?->translatedFormat('j F Y').'. Setelah itu akun menjadi baca saja.',
            ),
            $subscription->status->equals(Active::class) => self::unpaidInvoice($subscription),
            default => null,
        };
    }

    private static function trial(Tenant $tenant, Subscription $subscription): ?self
    {
        $endsOn = SubscriptionBilling::trialEndsOn($tenant);

        if ($endsOn === null) {
            return null;
        }

        $invoice = self::firstUnpaid($subscription);
        $next = $invoice === null
            ? 'Pilih paket di menu Langganan supaya data tetap bisa diubah setelahnya.'
            : 'Tagihan '.$invoice->number.' sebesar '.Rupiah::format($invoice->amount).' sudah terbit.';

        return $tenant->trialDaysLeft() === null
            ? new self('Masa trial sudah berakhir', 'Trial berakhir '.$endsOn->translatedFormat('j F Y').'. '.$next, urgent: true)
            : new self("Masa trial: sisa {$tenant->trialDaysLeft()} hari", 'Trial berakhir '.$endsOn->translatedFormat('j F Y').'. '.$next);
    }

    private static function unpaidInvoice(Subscription $subscription): ?self
    {
        $invoice = self::firstUnpaid($subscription);

        return $invoice === null ? null : new self(
            "Tagihan langganan {$invoice->number} belum dibayar",
            Rupiah::format($invoice->amount).', jatuh tempo '.$invoice->due_date->translatedFormat('j F Y').'.',
        );
    }

    private static function firstUnpaid(Subscription $subscription): ?SubscriptionInvoice
    {
        return $subscription->invoices()->where('status', Unpaid::$name)->orderBy('due_date')->first();
    }
}
