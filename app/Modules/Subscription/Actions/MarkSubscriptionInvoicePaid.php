<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionInvoice;
use App\Modules\Subscription\States\Subscription\Frozen;
use App\Modules\Subscription\Support\SubscriptionBilling;
use App\Modules\Tenancy\Actions\UnfreezeTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\TenantContext;
use App\Support\Actions\Action;
use App\Support\Actors\ActorContext;
use App\Support\Actors\ActorType;
use App\Support\Subscriptions\AllowedWhenReadOnly;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * A super admin confirms a subscription payment received by transfer
 * (FR-SUB-03), until the payment gateway confirms payments itself. The
 * subscription becomes active on the invoice's plan and period, and a
 * tenant frozen for not paying opens again.
 */
final class MarkSubscriptionInvoicePaid extends Action implements AllowedWhenReadOnly
{
    public function __construct(
        private readonly SubscriptionBilling $billing,
        private readonly TenantContext $tenants,
        private readonly ActorContext $actors,
        private readonly UnfreezeTenant $unfreeze,
    ) {}

    /**
     * @param  array<string, mixed>  $input  paid_at, payment_note
     */
    public function handle(SubscriptionInvoice $invoice, array $input): SubscriptionInvoice
    {
        $this->authorize('markPaid', $invoice);

        $data = $this->validate($input, [
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'payment_note' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant = Tenant::query()->findOrFail($invoice->tenant_id);

        return $this->tenants->run($tenant, fn (): SubscriptionInvoice => $this->transaction(function () use ($invoice, $data, $tenant): SubscriptionInvoice {
            $invoice = SubscriptionInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! $invoice->isUnpaid()) {
                throw ValidationException::withMessages(['paid_at' => "Tagihan {$invoice->number} sudah lunas atau dibatalkan."]);
            }

            $wasFrozen = Subscription::query()->whereKey($invoice->subscription_id)->firstOrFail()->status->equals(Frozen::class);
            $actor = $this->actors->current();

            $this->billing->settle(
                $invoice,
                CarbonImmutable::parse($data['paid_at']),
                $data['payment_note'] ?? null,
                $actor->type === ActorType::PlatformAdmin ? $actor->id : null,
            );

            if ($wasFrozen && $tenant->refresh()->isFrozen()) {
                $this->unfreeze->handle($tenant);
            }

            return $invoice;
        }));
    }
}
