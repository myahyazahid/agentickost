<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Models\SubscriptionInvoice;
use App\Modules\Subscription\States\SubscriptionInvoice\Voided;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\States\StateTransition;
use App\Support\Subscriptions\AllowedWhenReadOnly;
use Illuminate\Validation\ValidationException;

/**
 * Withdraws an unpaid subscription invoice issued by mistake.
 */
final class VoidSubscriptionInvoice extends Action implements AllowedWhenReadOnly
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function handle(SubscriptionInvoice $invoice): SubscriptionInvoice
    {
        $this->authorize('void', $invoice);

        return $this->tenants->run(Tenant::query()->findOrFail($invoice->tenant_id), fn (): SubscriptionInvoice => $this->transaction(function () use ($invoice): SubscriptionInvoice {
            $invoice = SubscriptionInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $invoice->isUnpaid()) {
                throw ValidationException::withMessages(['status' => 'Hanya tagihan yang belum dibayar yang bisa dibatalkan.']);
            }

            $invoice->voided_at = now();
            StateTransition::to($invoice->status, Voided::class);

            return $invoice;
        }));
    }
}
